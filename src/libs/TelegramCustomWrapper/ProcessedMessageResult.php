<?php declare(strict_types=1);

namespace App\TelegramCustomWrapper;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\BetterLocationCollection;
use App\Config;
use unreal4u\TelegramAPI\Telegram\Types;

class ProcessedMessageResult
{
	/** @var BetterLocationCollection */
	private $collection;

	private $resultText = '';
	/** @var array<array<Types\Inline\Keyboard\Button>> */
	private $buttons = [];
	private $autorefreshEnabled = false;

	private $validLocationsCount = 0;

	/** @var BetterLocationMessageSettings */
	private $messageSettings;

	public function __construct(BetterLocationCollection $collection, BetterLocationMessageSettings $messageSettings)
	{
		$this->collection = $collection;
		$this->messageSettings = $messageSettings;
	}

	public function setAutorefresh(bool $enabled): void
	{
		$this->autorefreshEnabled = $enabled;
	}

	public function process(bool $printAllErrors = false): self
	{
		if ($this->messageSettings->showAddress()) {
			$this->collection->fillAddresses();
		}

		// If multiple locations are available, generate share bulk links
		if ($this->collection->count() > 1) {
			$bulkLinks = [];
			foreach ($this->messageSettings->getBulkLinkServices() as $bulkLinkService) {
				$bulkLinks[] = sprintf(
					'<a href="%s" target="_blank">%s</a>',
					$bulkLinkService::getShareCollectionLink($this->collection),
					$bulkLinkService::getName(true),
				);
			}

			$this->resultText .= sprintf(
				'%d locations: %s' . PHP_EOL . PHP_EOL,
				$this->collection->count(),
				implode(' | ', $bulkLinks)
			);
		}

		foreach ($this->collection->getLocations() as $betterLocation) {

			// @TEMPORARY 2022-10-01 - disabled because of too long waiting for external Ingress API
//			if ($betterLocation->hasDescription(Ingress::BETTER_LOCATION_KEY_PORTAL) === false) {
//				Ingress::setPortalDataDescription($betterLocation);
//			}

			$this->resultText .= $betterLocation->generateMessage($this->messageSettings);
			$this->buttons[] = $betterLocation->generateDriveButtons($this->messageSettings);
			$this->validLocationsCount++;

			if ($this->validLocationsCount >= Config::TELEGRAM_MAXIMUM_LOCATIONS) {
				$this->resultText .= sprintf(
					'Showing only first %d of %d detected locations. All at once can be opened with links on top of the message.',
					$this->validLocationsCount,
					$this->collection->count(),
				);
				break;
			}
		}
		return $this;
	}

	/** @return array<array<Types\Inline\Keyboard\Button>> */
	public function getButtons(?int $maxRows = null, bool $includeRefreshRow = true): array
	{
		$result = $this->buttons;
		if ($maxRows > 0) {
			$result = array_slice($result, 0, $maxRows);
		}
		if ($includeRefreshRow && $this->collection->hasRefreshableLocation()) {
			$result[] = BetterLocation::generateRefreshButtons($this->autorefreshEnabled);
		}
		return $result;
	}

	public function getMarkup(?int $maxRows = null, bool $includeRefreshRow = true): Types\Inline\Keyboard\Markup
	{
		$markup = new Types\Inline\Keyboard\Markup();
		$markup->inline_keyboard = $this->getButtons($maxRows, $includeRefreshRow);
		return $markup;
	}

	public function getText(): string
	{
		$result = '';
		if ($this->collection->count()) {
			$result = TelegramHelper::invisibleLink($this->collection->getStaticMapUrl());
		}
		return $result . $this->resultText;
	}

	public function validLocationsCount(): int
	{
		return $this->validLocationsCount;
	}

	public function getCollection(): BetterLocationCollection
	{
		return $this->collection;
	}

}
