<?php declare(strict_types=1);

namespace App\TelegramCustomWrapper\Events\Special;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\BetterLocationCollection;
use App\BetterLocation\GooglePlaceApi;
use App\BetterLocation\Service\MapyCzService;
use App\Chat;
use App\Config;
use App\Icons;
use App\Repository\ChatEntity;
use App\TelegramCustomWrapper\BetterLocationMessageSettings;
use App\TelegramCustomWrapper\Events\Command\StartCommand;
use App\TelegramCustomWrapper\ProcessedMessageResult;
use App\TelegramCustomWrapper\TelegramHelper;
use App\Utils\Formatter;
use Tracy\Debugger;
use Tracy\ILogger;
use unreal4u\TelegramAPI\Telegram;
use unreal4u\TelegramAPI\Telegram\Methods\AnswerInlineQuery;
use unreal4u\TelegramAPI\Telegram\Types\Inline;
use unreal4u\TelegramAPI\Telegram\Types\InputMessageContent\Text;

class InlineQueryEvent extends Special
{
	/**
	 * How many favourite locations will be shown?
	 *
	 * @TODO info to user, if he has more saved location than is now shown
	 */
	const MAX_FAVOURITES = 10;

	/**
	 * Instance of Chat for this user's private chat (only for lazy load)
	 *
	 * @internal Only for lazy load, use $this->getMessageSettings() instead
	 * @see getMessageSettings()
	 */
	private ?Chat $_userPrivateChatEntity = null;

	public function getMessageSettings(): BetterLocationMessageSettings
	{
		$messageSettings = parent::getMessageSettings();
		$messageSettings->showAddress($this->getUserPrivateChatEntity()->settingsShowAddress());
		return $messageSettings;
	}

	private function getUserPrivateChatEntity(): Chat
	{
		if ($this->_userPrivateChatEntity === null) {
			$this->_userPrivateChatEntity = new Chat($this->getFromId(), ChatEntity::CHAT_TYPE_PRIVATE, $this->getFromDisplayname());
		}
		return $this->_userPrivateChatEntity;
	}

	public function handleWebhookUpdate()
	{
		$answerInlineQuery = new AnswerInlineQuery();
		$answerInlineQuery->inline_query_id = $this->update->inline_query->id;
		$answerInlineQuery->cache_time = Config::TELEGRAM_INLINE_CACHE;

		$queryInput = GooglePlaceApi::normalizeInput($this->update->inline_query->query);

		if ($this->update->inline_query->location) {
			$this->user->setLastKnownLocation($this->update->inline_query->location->latitude, $this->update->inline_query->location->longitude);
		}

		if (empty($queryInput)) {
			// If user agrees to share location, and is using device, where is possible to get location (typically mobile devices)
			if ($this->update->inline_query->location instanceof Telegram\Types\Location) {
				$betterLocation = BetterLocation::fromLatLon($this->update->inline_query->location->latitude, $this->update->inline_query->location->longitude);
				$headerMessage = sprintf('%s Current location', Icons::CURRENT_LOCATION);
				$betterLocation->setPrefixMessage($headerMessage);
				$answerInlineQuery->addResult($this->getInlineQueryResult($betterLocation, $headerMessage));
			} else if ($this->user->getLastKnownLocation() instanceof BetterLocation) {
				$lastKnownLocation = clone $this->user->getLastKnownLocation();
				$now = new \DateTimeImmutable();
				$diff = $now->getTimestamp() - $this->user->getLastKnownLocationDatetime()->getTimestamp();
				if ($diff <= 600) { // if last update was just few minutes ago, behave just like current location @TODO time border move to config
					$lastKnownLocation->setPrefixMessage(sprintf('%s Current location', Icons::CURRENT_LOCATION));
					$lastKnownLocation->setDescription(null);
				}
				$inlineText = sprintf('%s (%s ago)', $lastKnownLocation->getPrefixMessage(), Formatter::seconds($diff, true));
				$answerInlineQuery->addResult($this->getInlineQueryResult($lastKnownLocation, $inlineText));
			}

			// Show list of favourites
			$index = 0;
			foreach ($this->user->getFavourites() as $favourite) {
				if ($index++ < self::MAX_FAVOURITES) {
					$answerInlineQuery->addResult($this->getInlineQueryResult($favourite));
				}
			}
			if (count($answerInlineQuery->getResults()) === 0) {
				$answerInlineQuery->switch_pm_text = 'Search location (coordinates, link, etc)';
				$answerInlineQuery->switch_pm_parameter = 'inline-empty';
			}
		} else if (preg_match(sprintf('/^%s %s (-?[0-9]{1,2}\.[0-9]{1,6}) (-?[0-9]{1,3}\.[0-9]{1,6}) (.+)$/', StartCommand::FAVOURITE, StartCommand::FAVOURITE_RENAME), $queryInput, $matches)) {
			$newName = strip_tags($matches[3]);
			$newNameCommandDecoded = TelegramHelper::InlineTextEncode(
				sprintf('%s %s %F %F %s', StartCommand::FAVOURITE, StartCommand::FAVOURITE_RENAME, $matches[1], $matches[2], $newName)
			);
			if (mb_strlen($newNameCommandDecoded) > 64) {
				$answerInlineQuery->switch_pm_text = sprintf('New name is too long.');
				$answerInlineQuery->switch_pm_parameter = TelegramHelper::InlineTextEncode(
					sprintf('%s %s %s', StartCommand::FAVOURITE, StartCommand::FAVOURITE_ERROR, StartCommand::FAVOURITE_ERROR_TOO_LONG)
				);
			} else {
				$answerInlineQuery->switch_pm_text = sprintf('%s Rename to "%s"', Icons::CHANGE, $newName);
				$answerInlineQuery->switch_pm_parameter = $newNameCommandDecoded;
			}
//		} else if (preg_match(sprintf('/^%s %s (-?[0-9]{1,2}\.[0-9]{1,6}) (-?[0-9]{1,3}\.[0-9]{1,6})$/', StartCommand::FAVOURITE, StartCommand::FAVOURITE_DELETE), $queryInput, $matches)) {
//			list(, $lat, $lon) = $matches;
//			$lat = floatval($lat);
//			$lon = floatval($lon);
//			$answerInlineQuery->switch_pm_text = sprintf('%s Delete %s,%s', \Icons::DELETE, $lat, $lon);
//			$answerInlineQuery->switch_pm_parameter = TelegramHelper::InlineTextEncode(sprintf('%s %s %F %F', StartCommand::FAVOURITE, StartCommand::FAVOURITE_DELETE, $lat, $lon));
		} else {
			$entities = TelegramHelper::generateEntities($queryInput);
			try {
				$collection = BetterLocationCollection::fromTelegramMessage($queryInput, $entities);
				if ($collection->count() > 1 && $this->getUserPrivateChatEntity()->getSendNativeLocation() === false) {
					// There can be only one location if sending native location
					$answerInlineQuery->addResult($this->getAllLocationsInlineQueryResult($collection));
				}
				foreach ($collection->getLocations() as $betterLocation) {
					$answerInlineQuery->addResult($this->getInlineQueryResult($betterLocation));
				}

				// only if there is no match from previous processing
				if (mb_strlen($queryInput) >= Config::GOOGLE_SEARCH_MIN_LENGTH && count($answerInlineQuery->getResults()) === 0 && Config::isGooglePlaceApi()) {
					$googleCollection = GooglePlaceApi::search($queryInput, $this->getFrom()->language_code, $this->user->getLastKnownLocation());
					foreach ($googleCollection as $betterLocation) {
						$answerInlineQuery->addResult($this->getInlineQueryResult($betterLocation));
					}
				}

				if (count($answerInlineQuery->getResults()) === 0) {
					$answerInlineQuery->switch_pm_text = 'No valid location found...';
					$answerInlineQuery->switch_pm_parameter = 'inline-notfound';
				}
			} catch (\Throwable $exception) {
				Debugger::log($exception, ILogger::EXCEPTION);
				$answerInlineQuery->switch_pm_text = 'Error occured while processing. Try again later.';
				$answerInlineQuery->switch_pm_parameter = 'inline-exception';
			}
		}
		$this->run($answerInlineQuery);
	}

	public function hasMessage(): bool
	{
		return false;
	}

	public function getMessage(): Telegram\Types\Message
	{
		throw new \Exception(sprintf('Type %s doesn\'t support getMessage().', static::class));
	}

	public function getFrom(): Telegram\Types\User
	{
		return $this->update->inline_query->from;
	}

	private function getInlineQueryResult(BetterLocation $betterLocation, string $inlineTitle = null): Inline\Query\Result\Location|Inline\Query\Result\Article
	{
		if ($this->getUserPrivateChatEntity()->getSendNativeLocation()) {
			return $this->getInlineQueryResultNativeLocation($betterLocation, $inlineTitle);
		} else {
			return $this->getInlineQueryResultArticle($betterLocation, $inlineTitle);
		}
	}

	/**
	 * Prepare formatted text showing distance between user's last location and provided location. Returns empty string if
	 * cannot be calculated (eg. user's location is unknown)
	 */
	private function addDistanceText(BetterLocation $betterLocation): string
	{
		if ($usersLastLocation = $this->user->getLastKnownLocation()) {
			return sprintf(
				' (%s away)',
				Formatter::distance($usersLastLocation->getCoordinates()->distance($betterLocation->getCoordinates()))
			);
		} else {
			return '';
		}
	}

	private function getInlineQueryResultArticle(BetterLocation $betterLocation, string $inlineTitle = null): Inline\Query\Result\Article
	{
		$inlineQueryResult = new Inline\Query\Result\Article();
		$inlineQueryResult->id = rand(100000, 999999);
		if (is_null($inlineTitle)) {
			$inlineTitle = $betterLocation->getInlinePrefixMessage() ?? $betterLocation->getPrefixMessage();
			$inlineTitle .= $this->addDistanceText($betterLocation);
		}
		$inlineQueryResult->title = strip_tags($inlineTitle);
		$inlineQueryResult->description = $betterLocation->key();

		if ($this->showAddress()) {
			$betterLocation->generateAddress();
			if ($betterLocation->hasAddress()) {
				$inlineQueryResult->description .= sprintf(' (%s)', $betterLocation->getAddress());
			}
		}

		$processedCollection = $this->singleLocationToMessageResult($betterLocation);

		$inlineQueryResult->thumb_url = MapyCzService::getScreenshotLink($betterLocation->getLat(), $betterLocation->getLon());
		$inlineQueryResult->reply_markup = $processedCollection->getMarkup(1);
		$inlineQueryResult->input_message_content = new Text();
		$inlineQueryResult->input_message_content->message_text = $processedCollection->getText();
		$inlineQueryResult->input_message_content->parse_mode = 'HTML';
		$inlineQueryResult->input_message_content->disable_web_page_preview = !$this->getUserPrivateChatEntity()->settingsPreview();
		return $inlineQueryResult;
	}

	private function getInlineQueryResultNativeLocation(BetterLocation $betterLocation, string $inlineTitle = null): Inline\Query\Result\Location
	{
		$inlineQueryResult = new Inline\Query\Result\Location();
		$inlineQueryResult->id = rand(100000, 999999);
		if (is_null($inlineTitle)) {
			$inlineTitle = $betterLocation->getInlinePrefixMessage() ?? $betterLocation->getPrefixMessage();
			$inlineTitle .= $this->addDistanceText($betterLocation);
		}

		$processedCollection = $this->singleLocationToMessageResult($betterLocation);

		$inlineQueryResult->latitude = $betterLocation->getLat();
		$inlineQueryResult->longitude = $betterLocation->getLon();
		$inlineQueryResult->title = strip_tags($inlineTitle);
		$inlineQueryResult->thumb_url = MapyCzService::getScreenshotLink($betterLocation->getLat(), $betterLocation->getLon());
		$inlineQueryResult->reply_markup = $processedCollection->getMarkup(1);
		return $inlineQueryResult;
	}

	private function getAllLocationsInlineQueryResult(BetterLocationCollection $collection): Inline\Query\Result\Article
	{
		$processedCollection = new ProcessedMessageResult($collection, $this->getMessageSettings());
		$processedCollection->process(true);

		$inlineQueryResult = new Inline\Query\Result\Article();
		$inlineQueryResult->id = rand(100000, 999999);
		$inlineQueryResult->title = sprintf('%s Multiple locations', Icons::LOCATION);
		$inlineQueryResult->description = sprintf('Send all %d locations listed below as one message', count($collection->getLocations()));
		$inlineQueryResult->reply_markup = $processedCollection->getMarkup(1);
		// @TODO workaround until resolving https://github.com/DJTommek/better-location/issues/2 (Secure public access)
		$inlineQueryResult->thumb_url = 'https://raw.githubusercontent.com/DJTommek/better-location/master/asset/map-icon-bot%20v1.png';

		$inlineQueryResult->input_message_content = new Text();
		$inlineQueryResult->input_message_content->message_text = $processedCollection->getText();
		$inlineQueryResult->input_message_content->parse_mode = 'HTML';
		$inlineQueryResult->input_message_content->disable_web_page_preview = !$this->getUserPrivateChatEntity()->settingsPreview();
		return $inlineQueryResult;
	}

	private function showAddress(): bool
	{
		return $this->getUserPrivateChatEntity()->settingsShowAddress();
	}

	private function singleLocationToMessageResult(BetterLocation $betterLocation): ProcessedMessageResult
	{
		$singleLocationCollection = new BetterLocationCollection();
		$singleLocationCollection->add($betterLocation);

		$processedCollection = new ProcessedMessageResult($singleLocationCollection, $this->getMessageSettings());
		$processedCollection->process();
		return $processedCollection;
	}
}
