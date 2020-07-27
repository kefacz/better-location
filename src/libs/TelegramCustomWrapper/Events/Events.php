<?php

declare(strict_types=1);

namespace TelegramCustomWrapper\Events;

use React\EventLoop\Factory;
use TelegramCustomWrapper\SendMessage;
use TelegramCustomWrapper\TelegramHelper;
use Tracy\Debugger;
use Tracy\ILogger;
use unreal4u\TelegramAPI\HttpClientRequestHandler;
use unreal4u\TelegramAPI\Telegram\Methods\SendChatAction;
use unreal4u\TelegramAPI\Telegram\Types\Update;
use unreal4u\TelegramAPI\TgLog;
use Utils\DummyLogger;

abstract class Events
{
	protected $update;
	protected $tgLog;
	protected $loop;
	protected $user;
	protected $chat;

	protected $command = null;
	protected $params = [];

	public function __construct(Update $update) {
		$this->update = $update;

		$this->loop = Factory::create();
		$this->tgLog = new TgLog(TELEGRAM_BOT_TOKEN, new HttpClientRequestHandler($this->loop));

		if (TelegramHelper::isButtonClick($update)) {
			$this->user = new \User($update->callback_query->from->id, $update->callback_query->from->username);
			/** @noinspection PhpUndefinedFieldInspection */
			$this->chat = new \Chat(
				$update->callback_query->message->chat->id,
				$update->callback_query->message->chat->type,
				empty($update->callback_query->message->chat->title) ? $update->callback_query->from->displayname : $update->callback_query->message->chat->title,
			);
		} else {
			$this->user = new \User($update->message->from->id, $update->message->from->username);
			/** @noinspection PhpUndefinedFieldInspection */
			$this->chat = new \Chat(
				$update->message->chat->id,
				$update->message->chat->type,
				empty($update->message->chat->title) ? $update->message->from->displayname : $update->message->chat->title
			);
		}

		$this->command = TelegramHelper::getCommand($update);
		$this->params = TelegramHelper::getParams($update);
	}

	public function getChatId() {
		return $this->update->message->chat->id;
	}

	public function getFromId() {
		return $this->update->message->from->id;
	}

	public function getText() {
		return $this->update->message->text;
	}

	public function isPm() {
		return TelegramHelper::isPM($this->update);
	}

	/**
	 * @param string $action
	 * @throws \Exception
	 * @noinspection PhpUnused
	 * @TODO Check if action string is valid
	 */
	public function sendAction(string $action = TelegramHelper::CHAT_ACTION_TYPING) {
		$chatAction = new SendChatAction();
		$chatAction->chat_id = $this->getChatId();
		$chatAction->action = $action;
		$this->run($chatAction);
	}

	/**
	 * Send message as reply to recieved message
	 *
	 * @param string $text
	 * @param array $options
	 * @return null
	 * @throws \Exception
	 */
	public function reply(string $text, array $options = []) {
		$msg = new SendMessage($this->getChatId(), $text, $this->update->message->message_id);
		if (isset($options['reply_markup'])) {
			$msg->setReplyMarkup($options['reply_markup']);
		}
		if (isset($options['disable_web_page_preview'])) {
			$msg->disableWebPagePreview($options['disable_web_page_preview']);
		}
		return $this->run($msg->msg);
	}

	/**
	 * @param $objectToSend
	 * @return null
	 * @throws \Exception
	 */
	public function run($objectToSend) {

		$promise = $this->tgLog->performApiRequest($objectToSend);
		$this->loop->run();
		DummyLogger::log(DummyLogger::NAME_TELEGRAM_OUTPUT, $objectToSend);
		$resultResponse = null;
		$resultException = null;
		$promise->then(
			function ($response) use (&$resultResponse) {
				$resultResponse = $response;
				DummyLogger::log(DummyLogger::NAME_TELEGRAM_OUTPUT_RESPONSE, $resultResponse);
			},
			function (\Exception $exception) use (&$resultException) {
				$resultException = $exception;
				Debugger::log(sprintf('TG API Command request error: "%s"', $exception->getMessage()), ILogger::EXCEPTION);
				Debugger::log($exception, ILogger::EXCEPTION);
			}
		);

		if ($resultException) {
			throw $resultException;
		} else {
			return $resultResponse;
		}
	}
}