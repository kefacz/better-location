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

	public function __construct(BetterLocationCollection $collection)
	{
		$this->collection = $collection;
	}

	public function setAutorefresh(bool $enabled): void
	{
		$this->autorefreshEnabled = $enabled;
	}

	public function process(bool $printAllErrors = false): self
	{
		foreach ($this->collection->getLocations() as $betterLocation) {
			$this->resultText .= $betterLocation->generateMessage();
			$rowButtons = $betterLocation->generateDriveButtons();
			$rowButtons[] = $betterLocation->generateAddToFavouriteButtton();
			$this->buttons[] = $rowButtons;
			$this->validLocationsCount++;
		}
		foreach ($this->collection->getErrors() as $error) {
			if ($error instanceof InvalidLocationException) {
				$this->resultText .= Icons::ERROR . $error->getMessage() . PHP_EOL;
			} else {
				if ($printAllErrors) {
					$this->resultText .= Icons::ERROR . ' Unexpected error occured while proceessing message for locations.' . PHP_EOL;
				}
				Debugger::log($error, Debugger::EXCEPTION);
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

	public function getText(bool $withPrefix = true, bool $withStaticMapsLink = true): string
	{
		// @TODO Currently not ready to release to production since link contains app token.
		// Hide URL behind proxy or download image somewhere and offer new URL
		$withStaticMapsLink = false;

		$result = '';
		if ($withPrefix) {
			$result .= TelegramHelper::getMessagePrefix($withStaticMapsLink ? $this->collection->getStaticMapUrl() : null);
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
