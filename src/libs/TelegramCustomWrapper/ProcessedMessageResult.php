<?php declare(strict_types=1);

namespace App\TelegramCustomWrapper;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\BetterLocationCollection;
use App\BetterLocation\Service\Exceptions\InvalidLocationException;
use App\Icons;
use Tracy\Debugger;
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
		$this->collection->fillAddresses();
		foreach ($this->collection->getLocations() as $betterLocation) {
			$this->resultText .= $betterLocation->generateMessage($this->messageSettings);
			$this->buttons[] = $betterLocation->generateDriveButtons($this->messageSettings);
			$this->validLocationsCount++;
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

	public function getText(bool $withPrefix = true, bool $withStaticMapsLink = true): string
	{
		$result = '';
		if ($withPrefix) {
			if ($withStaticMapsLink && (count($this->collection->getLocations()) > 0)) {
				$staticMapsLink = $this->collection->getStaticMapUrl();
			} else {
				$staticMapsLink = null;
			}
			$result .= TelegramHelper::getMessagePrefix($staticMapsLink);
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
