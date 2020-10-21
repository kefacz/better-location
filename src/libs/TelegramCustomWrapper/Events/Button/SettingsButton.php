<?php declare(strict_types=1);

namespace TelegramCustomWrapper\Events\Button;

use TelegramCustomWrapper\Events\Command\SettingsCommand;
use unreal4u\TelegramAPI\Telegram\Types\Inline\Keyboard\Markup;

class SettingsButton extends Button
{
	const CMD = SettingsCommand::CMD;

	public function __construct($update) {
		parent::__construct($update);

		$text = sprintf('<b>Settings</b>') . PHP_EOL;
		$text .= sprintf('Choose one of the settings via buttons below:') . PHP_EOL;

		$replyMarkup = new Markup();
		$replyMarkup->inline_keyboard = [
			[ // row of buttons
				[ // button
					'text' => 'Settings:',
					'callback_data' => self::CMD,
				],
			],
		];

		$this->replyButton($text, $replyMarkup);
	}
}
