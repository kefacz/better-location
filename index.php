<?php declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (isset($_GET['delete-tracy-email-sent'])) {
	if (@unlink(\Dashboard\Status::getTracyEmailSentFilePath())) {
		printf('<p>%s Tracy\'s "email-sent" file was deleted.</p>', Icons::SUCCESS);
	} else {
		$lastPhpError = error_get_last();
		printf('<p>%s Error while deleting Tracy\'s "email-sent" file: <b>%s</b></p>', Icons::ERROR, $lastPhpError ? $lastPhpError['message'] : 'unknown error');
	}
	die('<p>Go back to <a href="./index.php">index.php</a></p>');
}

?>
<html lang="en">
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<title>BetterLocation - Admin</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
	<link rel="stylesheet" href="asset/css/main.css">
	<link rel="shortcut icon" href="asset/favicon.ico">
</head>
<body>
<div class="container">
	<h1><?= \Icons::LOCATION; ?> <a href="./">BetterLocation</a> - Admin</h1>
	<ul class="nav nav-tabs nav-fill" id="main-tab" role="tablist">
		<li class="nav-item">
			<a class="nav-link active" id="tab-install" data-toggle="tab" href="#install"><?= \Dashboard\Status::getInstallTabIcon() ?> Install and status</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="tab-error" data-toggle="tab" href="#error"><?= \Dashboard\Status::getTracyEmailIcon() ?> Errors</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="tab-statistics" data-toggle="tab" href="#statistics">Statistics</a>
		</li>
	</ul>
	<div class="tab-content">
		<div class="tab-pane fade show active" id="install">
			<h2>Install and status</h2>
			<ol>
				<li>Download/clone <a href="https://github.com/DJTommek/better-location" target="_blank" title="DJTommek/better-location on Github">BetterLocation repository</a> <?= \Icons::SUCCESS; ?></li>
				<li>Run <code>composer install</code> <?= \Icons::SUCCESS; ?></li>
				<?php
				$dbTextPrefix = sprintf('Update all <code>DB_*</code> constants in <b>%s</b>', \Dashboard\Status::getLocalConfigPath());
				$tablesTextPrefix = 'Create tables in database using <b>asset/sql/structure.sql</b>';
				if (\Dashboard\Status::isDatabaseConnectionSet()) {
					printf('<li>%s: %s Connected to <b>%s</b></li>', $dbTextPrefix, \Icons::SUCCESS, \Config::DB_NAME);
					if (\Dashboard\Status::isDatabaseTablesSet()) {
						printf('<li>%s: %s All tables and columns are set.</li>', $tablesTextPrefix, \Icons::CHECKED);
					} else {
						printf('<li>%s: %s Error while checking columns: <b>%s</b></li>', $tablesTextPrefix, \Icons::ERROR, \Dashboard\Status::$tablesError->getMessage());
					}
				} else {
					printf('<li>%s: %s Error while connecting to database <b>%s</b>. Error: <b>%s</b></li>', $dbTextPrefix, \Icons::ERROR, \Config::DB_NAME, \Dashboard\Status::$dbError->getMessage());
					printf('<li>%s: %s Setup database connection first</li>', $tablesTextPrefix, \Icons::ERROR);
				}
				$tgStatusTextPrefix = sprintf('Update all <code>TELEGRAM_*</code> constants in <b>%s</b>', \Dashboard\Status::getLocalConfigPath());
				if (\Dashboard\Status::isTGWebhookUrSet() === false) {
					printf('<li>%s: %s webhook URL is not set.</li>', $tgStatusTextPrefix, \Icons::ERROR);
				} else if (\Dashboard\Status::isTGTokenSet() === false) {
					printf('<li>%s: %s bot token is not set.</li>', $tgStatusTextPrefix, \Icons::ERROR);
				} else if (\Dashboard\Status::isTGBotNameSet() === false) {
					printf('<li>%s: %s bot name is not set.</li>', $tgStatusTextPrefix, \Icons::ERROR);
				} else {
					printf('<li>%s: %s TG set to bot <a href="https://t.me/%3$s" target="_blank">%3$s</a> and webhook url to <a href="%4$s" target="_blank">%4$s</a></li>',
						$tgStatusTextPrefix, \Icons::SUCCESS, \Config::TELEGRAM_BOT_NAME, \Config::TELEGRAM_WEBHOOK_URL,
					);
				}

				$tgWebhookTextPrefix = 'Enable webhook via <a href="set-webhook.php" target="_blank">set-webhook.php</a>';
				if (\Dashboard\Status::isTGset()) {
					\Dashboard\Status::runGetWebhookStatus();
					if (\Dashboard\Status::$webhookError) {
						$jsonText = sprintf('<pre>%s</pre>', json_encode(get_object_vars(\Dashboard\Status::$webhookError->getError()), JSON_PRETTY_PRINT));
						$webhookDetailStatus = sprintf('%s Something is wrong: <b>%s</b>:%s', \Icons::ERROR, \Dashboard\Status::$webhookError->getMessage(), $jsonText);
					} else {
						if (\Dashboard\Status::$webhookOk) {
							$webhookDetailStatus = sprintf('%s Everything seems to be ok, check response below.', \Icons::SUCCESS);
						} else {
							$webhookDetailStatus = sprintf('%s Something might be wrong, check response below.', \Icons::WARNING);
						}
					}
					printf('<li>%s - response from <a href="https://core.telegram.org/bots/api#getwebhookinfo" target="_blank">getWebhookInfo</a>: %s</li>', $tgWebhookTextPrefix, $webhookDetailStatus);
				} else {
					printf('<li>%s: %s setup <code>TELEGRAM_*</code> in local config first.</li>', $tgWebhookTextPrefix, \Icons::ERROR);
				}
				?>
			</ol>
			<?php
			if (is_null(\Dashboard\Status::$webhookResponseRaw) === false) {
				?>
				<ul class="nav nav-tabs" id="webhook-tab" role="tablist">
					<li class="nav-item">
						<a class="nav-link active" id="webhook-tab-formatted" data-toggle="tab" href="#webhook-formatted">Formatted</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" id="tab-status" data-toggle="tab" href="#webhook-raw">Raw</a>
					</li>
				</ul>
				<div class="tab-content">
					<div class="tab-pane fade show active" id="webhook-formatted">
						<table class="table table-striped table-bordered table-hover table-sm">
							<?php
							foreach (get_object_vars(\Dashboard\Status::$webhookResponse) as $key => $value) {
								printf('<tr><td>%s</td><td>%s</td></tr>', $key, $value);
							}
							?>
						</table>
					</div>
					<div class="tab-pane fade" id="webhook-raw">
						<pre><?= json_encode(get_object_vars(\Dashboard\Status::$webhookResponseRaw), JSON_PRETTY_PRINT) ?></pre>
					</div>
				</div>
				<?php
			}
			?>
		</div>
		<div class="tab-pane fade" id="error">
			<h3>Errors</h3>
			<h4>Email reporting (<a href="https://tracy.nette.org/guide" target="_blank" title="Getting started with Tracy">help</a>)</h4>
			<?php
			if (is_null(\Config::TRACY_DEBUGGER_EMAIL)) {
				printf('<p>%s Email reporting is disabled. Set email to <b>%s::TRACY_DEBUGGER_EMAIL</b> to enable.</p>', \Icons::INFO, \Dashboard\Status::getLocalConfigPath());
			} else {
				printf('<p>%s Email reporting is enabled and set to <a href="mailto:%2$s">%2$s</a>.</p>', \Icons::SUCCESS, \Config::TRACY_DEBUGGER_EMAIL);
				$tracyEmailHelpPrefix = 'Tracy\'s "email-sent" file ';
				if (file_exists(\Dashboard\Status::getTracyEmailSentFilePath()) === true) {
					printf('%s %s detected - no futher emails will be sent unless this file is removed. <a href="?delete-tracy-email-sent" onclick="return confirm(\'Are you sure, you want to delete Tracy\\\'s \\\'email-sent\\\' file?\')">Delete</a>', Icons::WARNING, $tracyEmailHelpPrefix);
				} else {
					printf('%s %s not detected - in case of error, email will be sent.', Icons::SUCCESS, $tracyEmailHelpPrefix);
				}
			}
			?>
		</div>
		<div class="tab-pane fade" id="statistics">
			<h2>Statistics</h2>
			<p>
				<?php
				if (\Dashboard\Status::isDatabaseConnectionSet() && \Dashboard\Status::isDatabaseTablesSet()) {
					printf('<ul>');
					$now = new DateTimeImmutable();

					// Detected chats
					$results = [];
					$totalCount = 0;
					foreach (\Dashboard\Status::getChatsStats() as $groupType => $groupCount) {
						$results[] = sprintf('%s = <b>%d</b>', $groupType, $groupCount);
						$totalCount += $groupCount;
					}
					printf('<li><b>%d</b> detected chats (%s)</li>', $totalCount, join(', ', $results));

					// Detected users
					printf('<li><b>%d</b> detected users (wrote at least one message or command)</li>', \Dashboard\Status::getUsersCount());

					// Newest user
					$newestUser = \Dashboard\Status::getNewestUser();
					if ($newestUser) {
						printf('<li>Most recent active user:<br>ID = <b>%d</b><br>TG ID = <b>%d</b><br>TG Name = <b>%s</b><br>Registered = <b>%s</b> (%s ago)<br>Last update = <b>%s</b> (%s ago)</li>',
							$newestUser['user_id'],
							$newestUser['user_telegram_id'],
							$newestUser['user_telegram_name'] ? sprintf('<a href="https://t.me/%1$s" target="_blank">%1$s</a>', $newestUser['user_telegram_name']) : '<i>unknown</i>',
							$newestUser['user_registered']->format(DateTimeInterface::W3C),
							Utils\General::sToHuman($now->getTimestamp() - $newestUser['user_registered']->getTimestamp()),
							$newestUser['user_last_update']->format(DateTimeInterface::W3C),
							Utils\General::sToHuman($now->getTimestamp() - $newestUser['user_last_update']->getTimestamp()),
						);
					}

					// Last changed user
					$lastChangedUser = \Dashboard\Status::getLatestChangedUser();
					if ($lastChangedUser) {
						printf('<li>Newest registered user:<br>ID = <b>%d</b><br>TG ID = <b>%d</b><br>TG Name = <b>%s</b><br>Registered = <b>%s</b> (%s ago)<br>Last update = <b>%s</b> (%s ago)</li>',
							$lastChangedUser['user_id'],
							$lastChangedUser['user_telegram_id'],
							$lastChangedUser['user_telegram_name'] ? sprintf('<a href="https://t.me/%1$s" target="_blank">%1$s</a>', $lastChangedUser['user_telegram_name']) : '<i>unknown</i>',
							$lastChangedUser['user_registered']->format(DateTimeInterface::W3C),
							Utils\General::sToHuman($now->getTimestamp() - $lastChangedUser['user_registered']->getTimestamp()),
							$lastChangedUser['user_last_update']->format(DateTimeInterface::W3C),
							Utils\General::sToHuman($now->getTimestamp() - $lastChangedUser['user_last_update']->getTimestamp()),
						);
					}

					printf('</ul>');
				} else {
					printf('<p>%s Setup database connection and prepare tables.</p>', \Icons::ERROR);
				}
				?>
			</p>
		</div>
		<div class="tab-pane fade" id="statistics">
			<h2>Tester</h2>
			<div id="tester">
				<?php
				$input = (isset($_POST['input']) ? trim($_POST['input']) : null);
				?>
				<form method="POST">
					<label>
						<textarea name="input"><?= $input ?? 'Type something...' ?></textarea>
					</label>
					<button type="submit">Send</button>
				</form>
				<h3>Result</h3>
				<div>
					<?php
					if ($input) {
						$urls = \Utils\General::getUrls($input);

						// Simulate Telegram message by creating URL entities
						$entities = [];
						foreach ($urls as $url) {
							$entity = new stdClass();
							$entity->type = 'url';
							$entity->offset = mb_strpos($input, $url);
							$entity->length = mb_strlen($url);
							$entities[] = $entity;
						}
						try {
							$betterLocations = \BetterLocation\BetterLocation::generateFromTelegramMessage($input, $entities);
							if (count($betterLocations->getLocations())) {
								$result = '';
								foreach ($betterLocations->getLocations() as $betterLocation) {
									$result .= $betterLocation->generateBetterLocation();
								}
								printf('<pre>%s</pre>', $result);
							} else {
								printf('No location(s) was detected in text.');
							}
							foreach ($betterLocations->getErrors() as $betterLocationError) {
								printf('<p>%s Error: <b>%s</b></p>', \Icons::ERROR, htmlentities($betterLocationError->getMessage()));
							}
						} catch (\Throwable $exception) {
							\Tracy\Debugger::log($exception, \Tracy\ILogger::EXCEPTION);
							printf('%s Error occured while processing input: %s', Icons::ERROR, $exception->getMessage());
						}
					} else {
						print('Fill and send some data.');
					}
					?>
				</div>
			</div>
		</div>
	</div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
</body>
