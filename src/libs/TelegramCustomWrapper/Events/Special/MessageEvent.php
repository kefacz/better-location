<?php declare(strict_types=1);

namespace App\TelegramCustomWrapper\Events\Special;

use App\BetterLocation\BetterLocationCollection;
use App\BetterLocation\GooglePlaceApi;
use App\Config;
use App\Icons;
use App\TelegramCustomWrapper\Events\Command\HelpCommand;
use App\TelegramCustomWrapper\ProcessedMessageResult;
use App\TelegramUpdateDb;

class MessageEvent extends Special
{
	public function getCollection()
	{
		return BetterLocationCollection::fromTelegramMessage($this->getText(), $this->update->message->entities);
	}

	public function handleWebhookUpdate()
	{
		$collection = $this->getCollection();
		if ($collection->count() === 0 && mb_strlen($this->getText()) >= Config::GOOGLE_SEARCH_MIN_LENGTH && is_null(Config::GOOGLE_PLACE_API_KEY) === false) {
			$googleCollection = GooglePlaceApi::search($this->getText(), $this->getFrom()->language_code, $this->user->getLastKnownLocation());
			$collection->mergeCollection($googleCollection);
		}
		$processedCollection = new ProcessedMessageResult($collection);
		$processedCollection->process();
		if ($collection->count() > 0) {
			if ($this->user->settings()->getSendNativeLocation()) {
				$this->replyLocation($processedCollection->getCollection()->getFirst(), $processedCollection->getMarkup(1, false));
			} else {
				$text = $processedCollection->getText();
				$markup = $processedCollection->getMarkup(1);
				$response = $this->reply($text, $markup, ['disable_web_page_preview' => !$this->user->settings()->getPreview()]);
				if ($response && $collection->hasRefreshableLocation()) {
					$cron = new TelegramUpdateDb($this->update, $response->message_id, TelegramUpdateDb::STATUS_DISABLED, new \DateTimeImmutable());
					$cron->insert();
					$cron->setLastSendData($text, $markup, true);
				}
			}
		} else { // No detected locations or occured errors
			if ($this->isPm() === true) {
				$message = 'Hi there in PM!' . PHP_EOL;
				$message .= 'Thanks for the ';
				if ($this->isForward()) {
					$message .= 'forwarded ';
				}
				$message .= sprintf('message, but I didn\'t detected any location in that message. Use %s command to get info how to use me.', HelpCommand::getCmd(!$this->isPm())) . PHP_EOL;
				$message .= sprintf('%s Most used tips: ', Icons::INFO) . PHP_EOL;
				$message .= '- send me any message with location data (coords, links, Telegram location...)' . PHP_EOL;
				$message .= '- send me Telegram location' . PHP_EOL;
				$message .= '- send me <b>uncompressed</b> photos (as file) to process location from EXIF' . PHP_EOL;
				$message .= '- forward me any of above' . PHP_EOL;
				$this->reply($message);
			} else {
				// do not send anything to group chat
			}
		}
	}
}


