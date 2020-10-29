<?php declare(strict_types=1);

namespace App\TelegramCustomWrapper\Events\Special;

use App\BetterLocation\BetterLocation;
use App\Icons;
use App\TelegramCustomWrapper\TelegramHelper;
use Tracy\Debugger;
use Tracy\ILogger;
use unreal4u\TelegramAPI\Telegram\Types\Inline\Keyboard\Markup;

class LocationEvent extends Special
{
	public function __construct($update)
	{
		parent::__construct($update);

		$result = null;
		try {
			$betterLocation = BetterLocation::fromLatLon($this->update->message->location->latitude, $this->update->message->location->longitude);
			$betterLocation->setPrefixMessage('Location');
			$result = $betterLocation->generateBetterLocation();
			$buttons = $betterLocation->generateDriveButtons();
			$buttons[] = $betterLocation->generateAddToFavouriteButtton();
		} catch (\Throwable $exception) {
			Debugger::log($exception, ILogger::EXCEPTION);
			$this->reply(sprintf('%s Unexpected error occured while processing location for Better location. Contact Admin for more info.', Icons::ERROR));
			return;
		}
		if ($result) {
			$markup = (new Markup());
			if (isset($buttons)) {
				$markup->inline_keyboard = [$buttons];
			}
			$this->reply(
				TelegramHelper::MESSAGE_PREFIX . $result,
				[
					'disable_web_page_preview' => true,
					'reply_markup' => $markup,
				],
			);
			return;
		}
	}
}


