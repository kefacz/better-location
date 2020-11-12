<?php declare(strict_types=1);

namespace App\TelegramCustomWrapper\Events\Special;

use App\BetterLocation\Service\WazeService;
use App\Config;
use App\Icons;
use App\TelegramCustomWrapper\Events\Command\HelpCommand;
use unreal4u\TelegramAPI\Telegram\Types\Inline\Keyboard\Markup;
use unreal4u\TelegramAPI\Telegram\Types\Update;

class AddedToChatEvent extends Special
{
	public function __construct(Update $update)
	{
		parent::__construct($update);

		$lat = 50.087451;
		$lon = 14.420671;
		$wazeLink = WazeService::getLink($lat, $lon);
		$betterLocationWaze = WazeService::parseCoords($wazeLink);

		$text = sprintf('%s Hi <b>%s</b>, @%s here!', Icons::LOCATION, $this->getChatDisplayname(), Config::TELEGRAM_BOT_NAME) . PHP_EOL;
		$text .= sprintf('Thanks for adding me to this chat. I will be checking every message here if it contains any form of location (coordinates, links, photos with EXIF...) and send a nicely formatted message. More info in %s.', HelpCommand::getCmd(!$this->isPm())) . PHP_EOL;
		$text .= sprintf('For example if you send %s I will respond with this:', $wazeLink) . PHP_EOL;
		$text .= PHP_EOL;
		$text .= $betterLocationWaze->generateMessage();

		$markup = (new Markup());
		$markup->inline_keyboard = [array_merge(
			$betterLocationWaze->generateDriveButtons(),
			[$betterLocationWaze->generateAddToFavouriteButtton()]
		)];

		$this->reply($text, [
			'disable_web_page_preview' => true,
			'reply_markup' => $markup,
		]);
	}
}


