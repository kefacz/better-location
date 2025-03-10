<?php declare(strict_types=1);

namespace App\TelegramCustomWrapper\Events\Special;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\BetterLocationCollection;
use App\BetterLocation\Service\Exceptions\InvalidLocationException;
use App\Config;
use App\Icons;
use App\TelegramCustomWrapper\ProcessedMessageResult;
use App\TelegramCustomWrapper\TelegramHelper;
use App\TelegramUpdateDb;
use Tracy\Debugger;
use Tracy\ILogger;
use unreal4u\TelegramAPI\Telegram;

class FileEvent extends Special
{
	const MIME_TYPE_IMAGE_JPEG = 'image/jpeg';

	const MAX_FILE_SIZE_DOWNLOAD = 20 * 1024 * 1024; // in bytes

	/** @var bool */
	private $fileTooBig = false;

	public function getCollection(): BetterLocationCollection
	{
		$collection = new BetterLocationCollection();
		$document = $this->update->message->document;
		if ($document->mime_type === self::MIME_TYPE_IMAGE_JPEG) {
			if ($document->file_size > self::MAX_FILE_SIZE_DOWNLOAD) {
				$this->fileTooBig = true;
			} else {
				$this->sendAction();
				try {
					$getFile = new Telegram\Methods\GetFile();
					$getFile->file_id = $document->file_id;
					/** @var Telegram\Types\File $response */
					$response = $this->run($getFile);
					$fileLink = TelegramHelper::getFileUrl(Config::TELEGRAM_BOT_TOKEN, $response->file_path);
					$betterLocationExif = BetterLocation::fromExif($fileLink);
					if ($betterLocationExif instanceof BetterLocation) {
						$collection->add($betterLocationExif);
					}
				} catch (\Throwable $exception) {
					Debugger::log($exception, ILogger::EXCEPTION);
				}
			}
		}
		$collection->add(BetterLocationCollection::fromTelegramMessage(
			$this->update->message->caption,
			$this->update->message->caption_entities
		));
		return $collection;
	}

	public function handleWebhookUpdate()
	{
		$collection = $this->getCollection();
		$processedCollection = new ProcessedMessageResult($collection, $this->getMessageSettings());
		$processedCollection->process();
		if ($collection->count() > 0) {
			if ($this->chat->getSendNativeLocation()) {
				$this->replyLocation($processedCollection->getCollection()->getFirst(), $processedCollection->getMarkup(1, false));
			} else {
				$text = $processedCollection->getText();
				$markup = $processedCollection->getMarkup(1);
				$response = $this->reply($text, $markup, ['disable_web_page_preview' => !$this->chat->settingsPreview()]);
				if ($response && $collection->hasRefreshableLocation()) {
					$cron = new TelegramUpdateDb($this->update, $response->message_id, TelegramUpdateDb::STATUS_DISABLED, new \DateTimeImmutable());
					$cron->insert();
					$cron->setLastSendData($text, $markup, true);
				}
			}
		} else { // No detected locations or occured errors
			if ($this->isPm() === true) {
				$message = 'Hi there!' . PHP_EOL;
				$message .= 'Thanks for the ';
				if ($this->isForward()) {
					$message .= 'forwarded ';
				}
				$message .= 'file but ';
				if ($this->fileTooBig) {
					$message .= 'it is too big (> 20 MB, Telegram bot API limit), so I can\'t process it.';
				} else {
					$message .= 'I\'m not sure, what to do... No location in EXIF was found.';
				}
				$this->reply($message);
			} else {
				// do not send anything to chat
			}
		}
	}
}


