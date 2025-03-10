<?php declare(strict_types=1);

use App\BetterLocation\BetterLocation;
use App\Config;
use App\Icons;
use App\TelegramCustomWrapper\ProcessedMessageResult;
use App\TelegramCustomWrapper\TelegramHelper;
use App\Utils\Formatter;
use function Clue\React\Block\await;

require_once __DIR__ . '/../../src/bootstrap.php';

function printlog(string $text)
{
	printf('<p><b>%s</b>: %s</p>', (new DateTime())->format(DATE_W3C), $text);
	\App\Utils\SimpleLogger::log(\App\Utils\SimpleLogger::NAME_CRON_AUTOREFRESH, $text);
}

if (isset($_GET['password']) && $_GET['password'] === \App\Config::CRON_PASSWORD) {
	$loop = \React\EventLoop\Factory::create();
	$tgLog = new \unreal4u\TelegramAPI\TgLog(Config::TELEGRAM_BOT_TOKEN, new \unreal4u\TelegramAPI\HttpClientRequestHandler($loop));

	$messagesToRefresh = \App\TelegramUpdateDb::loadAll(
		\App\TelegramUpdateDb::STATUS_ENABLED,
		null,
		Config::REFRESH_CRON_MAX_UPDATES,
		(new DateTime())->sub(new DateInterval(sprintf('PT%dS', Config::REFRESH_CRON_MIN_OLD)))
	);

	if (count($messagesToRefresh) === 0) {
		printlog('No message need refresh');
	} else {
		printlog(sprintf('Loaded %s updates to refresh.', count($messagesToRefresh)));
		$telegramCustomWrapper = \App\Factory::Telegram();
		foreach ($messagesToRefresh as $messageToRefresh) {
			$id = sprintf('%d-%d', $messageToRefresh->getChatId(), $messageToRefresh->getBotReplyMessageId());
			try {
				$telegramCustomWrapper->getUpdateEvent($messageToRefresh->getOriginalUpdateObject());
				$event = $telegramCustomWrapper->getEvent();
				printlog(sprintf('Processing %s with last refresh at %s (%s ago)',
					$id,
					$messageToRefresh->getLastUpdate()->format(DATE_W3C),
					Formatter::ago($messageToRefresh->getLastUpdate()),
				));
				/** @var \App\BetterLocation\BetterLocationCollection $collection */
				$collection = $event->getCollection();
				$processedCollection = new ProcessedMessageResult($collection, $event->getMessageSettings());
				$processedCollection->setAutorefresh(true);
				$processedCollection->process();

				$msg = new \unreal4u\TelegramAPI\Telegram\Methods\EditMessageText();
				$msg->chat_id = $messageToRefresh->getChatId();
				$msg->message_id = $messageToRefresh->getBotReplyMessageId();
				$msg->parse_mode = 'HTML';
				$msg->disable_web_page_preview = true;

				// remove last row where are located autorefresh buttons and replace this row with disabled state
				$lastAutorefreshMarkup = $messageToRefresh->getLastResponseReplyMarkup();
				if ($lastAutorefreshMarkup) {
					array_pop($lastAutorefreshMarkup->inline_keyboard);
					$lastAutorefreshMarkup->inline_keyboard[] = BetterLocation::generateRefreshButtons(false);
				}

				if (count($collection->getLocations()) === 0) {
					printlog(sprintf('Update %s don\'t have any locations anymore, disabling autorefresh.', $id));
					$msg->text = $messageToRefresh->getLastResponseText() . sprintf('%s Last autorefresh at %s didn\'t detect any locations. Autorefreshing was disabled but you can try to enable it again.', Icons::REFRESH, (new \DateTimeImmutable())->format(Config::DATETIME_FORMAT_ZONE));
					$msg->reply_markup = $lastAutorefreshMarkup;
					$messageToRefresh->autorefreshDisable();
					await($tgLog->performApiRequest($msg), $loop);
				} else {
					$replyMarkup = $processedCollection->getMarkup(1);
					$text = $processedCollection->getText();
					$msg->text = $text . sprintf('%s Autorefreshed: %s', Icons::REFRESH, (new \DateTimeImmutable())->format(Config::DATETIME_FORMAT_ZONE));
					$msg->reply_markup = $replyMarkup;
					await($tgLog->performApiRequest($msg), $loop);
					$messageToRefresh->setLastSendData($text, $replyMarkup, true);
					printlog(sprintf('Update %s was processed.', $id));
					$messageToRefresh->touchLastUpdate();
				}
			} catch (\Throwable $exception) {
				if ($exception instanceof \unreal4u\TelegramAPI\Exceptions\ClientException && $exception->getMessage() === TelegramHelper::MESSAGE_TO_EDIT_DELETED) {
					$messageToRefresh->autorefreshDisable();
					printlog(sprintf('Message %s, which should be edited was deleted, disabling autorefresh.', $id));
				} else {
					printlog(sprintf('Exception occured while processing %s: %s', $id, $exception->getMessage()));
					\Tracy\Debugger::log($exception, \Tracy\ILogger::EXCEPTION);
				}
			}
		}
		printlog('All updates were processed');
	}
} else {
	printlog('Invalid password');
}

