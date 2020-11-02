<?php declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (isset($_GET['delete-tracy-email-sent'])) {
	if (@unlink(\App\Dashboard\Status::getTracyEmailSentFilePath())) {
		printf('<p>%s Tracy\'s "email-sent" file was deleted.</p>', \App\Icons::SUCCESS);
	} else {
		$lastPhpError = error_get_last();
		printf('<p>%s Error while deleting Tracy\'s "email-sent" file: <b>%s</b></p>', \App\Icons::ERROR, $lastPhpError ? $lastPhpError['message'] : 'unknown error');
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
	<h1><?= \App\Icons::LOCATION; ?> <a href="./">BetterLocation</a> - Admin</h1>
	<ul class="nav nav-tabs nav-fill" id="main-tab" role="tablist">
		<li class="nav-item">
			<a class="nav-link active" id="tab-install" data-toggle="tab" href="#install"><?= \App\Dashboard\Status::getInstallTabIcon() ?> Install and status</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="tab-error" data-toggle="tab" href="#error"><?= \App\Dashboard\Status::getTracyEmailIcon() ?> Errors</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="tab-statistics" data-toggle="tab" href="#statistics">Statistics</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="tab-tester" data-toggle="tab" href="#tester">Tester</a>
		</li>
	</ul>
	<div class="tab-content">
		<div class="tab-pane fade show active" id="install">
			<h2>Install and status</h2>
			<ol>
				<li>Download/clone <a href="https://github.com/DJTommek/better-location" target="_blank" title="DJTommek/better-location on Github">BetterLocation repository</a> <?= \App\Icons::SUCCESS; ?></li>
				<li>Run <code>composer install</code> <?= \App\Icons::SUCCESS; ?></li>
				<?php
				$dbTextPrefix = sprintf('Update all <code>DB_*</code> constants in <b>%s</b>', \App\Dashboard\Status::getLocalConfigPath());
				$tablesTextPrefix = 'Create tables in database using <b>asset/sql/structure.sql</b>';
				if (\App\Dashboard\Status::isDatabaseConnectionSet()) {
					printf('<li>%s: %s Connected to <b>%s</b></li>', $dbTextPrefix, \App\Icons::SUCCESS, \App\Config::DB_NAME);
					if (\App\Dashboard\Status::isDatabaseTablesSet()) {
						printf('<li>%s: %s All tables and columns are set.</li>', $tablesTextPrefix, \App\Icons::CHECKED);
					} else {
						printf('<li>%s: %s Error while checking columns: <b>%s</b></li>', $tablesTextPrefix, \App\Icons::ERROR, \App\Dashboard\Status::$tablesError->getMessage());
					}
				} else {
					printf('<li>%s: %s Error while connecting to database <b>%s</b>. Error: <b>%s</b></li>', $dbTextPrefix, \App\Icons::ERROR, \App\Config::DB_NAME, \App\Dashboard\Status::$dbError->getMessage());
					printf('<li>%s: %s Setup database connection first</li>', $tablesTextPrefix, \App\Icons::ERROR);
				}
				$tgStatusTextPrefix = sprintf('Update all <code>TELEGRAM_*</code> constants in <b>%s</b>', \App\Dashboard\Status::getLocalConfigPath());
				if (\App\Dashboard\Status::isTGWebhookUrSet() === false) {
					printf('<li>%s: %s webhook URL is not set.</li>', $tgStatusTextPrefix, \App\Icons::ERROR);
				} else if (\App\Dashboard\Status::isTGTokenSet() === false) {
					printf('<li>%s: %s bot token is not set.</li>', $tgStatusTextPrefix, \App\Icons::ERROR);
				} else if (\App\Dashboard\Status::isTGBotNameSet() === false) {
					printf('<li>%s: %s bot name is not set.</li>', $tgStatusTextPrefix, \App\Icons::ERROR);
				} else {
					printf('<li>%s: %s TG set to bot <a href="https://t.me/%3$s" target="_blank">%3$s</a> and webhook url to <a href="%4$s" target="_blank">%4$s</a></li>',
						$tgStatusTextPrefix, \App\Icons::SUCCESS, \App\Config::TELEGRAM_BOT_NAME, \App\Config::TELEGRAM_WEBHOOK_URL,
					);
				}

				$tgWebhookTextPrefix = 'Enable webhook via <a href="set-webhook.php" target="_blank">set-webhook.php</a>';
				if (\App\Dashboard\Status::isTGset()) {
					\App\Dashboard\Status::runGetWebhookStatus();
					if (\App\Dashboard\Status::$webhookError) {
						$jsonText = sprintf('<pre>%s</pre>', json_encode(get_object_vars(\App\Dashboard\Status::$webhookError->getError()), JSON_PRETTY_PRINT));
						$webhookDetailStatus = sprintf('%s Something is wrong: <b>%s</b>:%s', \App\Icons::ERROR, \App\Dashboard\Status::$webhookError->getMessage(), $jsonText);
					} else {
						if (\App\Dashboard\Status::$webhookOk) {
							$webhookDetailStatus = sprintf('%s Everything seems to be ok, check response below.', \App\Icons::SUCCESS);
						} else {
							$webhookDetailStatus = sprintf('%s Something might be wrong, check response below.', \App\Icons::WARNING);
						}
					}
					printf('<li>%s - response from <a href="https://core.telegram.org/bots/api#getwebhookinfo" target="_blank">getWebhookInfo</a>: %s</li>', $tgWebhookTextPrefix, $webhookDetailStatus);
				} else {
					printf('<li>%s: %s setup <code>TELEGRAM_*</code> in local config first.</li>', $tgWebhookTextPrefix, \App\Icons::ERROR);
				}
				?>
			</ol>
			<?php
			if (is_null(\App\Dashboard\Status::$webhookResponseRaw) === false) {
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
							foreach (get_object_vars(\App\Dashboard\Status::$webhookResponse) as $key => $value) {
								printf('<tr><td>%s</td><td>%s</td></tr>', $key, $value);
							}
							?>
						</table>
					</div>
					<div class="tab-pane fade" id="webhook-raw">
						<pre><?= json_encode(get_object_vars(\App\Dashboard\Status::$webhookResponseRaw), JSON_PRETTY_PRINT) ?></pre>
					</div>
				</div>
				<?php
			}
			?>
			<ol>
				<li value="7">Google Place API: <?= \App\Config::GOOGLE_PLACE_API_KEY ? sprintf('%s Enabled', \App\Icons::SUCCESS) : sprintf('%s Disabled', \App\Icons::ERROR) ?></li>
				<li>What3Words API: <?= \App\Config::W3W_API_KEY ? sprintf('%s Enabled', \App\Icons::SUCCESS) : sprintf('%s Disabled', \App\Icons::ERROR) ?></li>
				<li>Glympse API: <?= \App\Config::isGlympse() ? sprintf('%s Enabled', \App\Icons::SUCCESS) : sprintf('%s Disabled', \App\Icons::ERROR) ?></li>
			</ol>

		</div>
		<div class="tab-pane fade" id="error">
			<h2>Errors</h2>
			<h4>Email reporting (<a href="https://tracy.nette.org/guide" target="_blank" title="Getting started with Tracy">help</a>)</h4>
			<?php
			if (is_null(\App\Config::TRACY_DEBUGGER_EMAIL)) {
				printf('<p>%s Email reporting is disabled. Set email to <b>%s::TRACY_DEBUGGER_EMAIL</b> to enable.</p>', \App\Icons::INFO, \App\Dashboard\Status::getLocalConfigPath());
			} else {
				printf('<p>%s Email reporting is enabled and set to <a href="mailto:%2$s">%2$s</a>.</p>', \App\Icons::SUCCESS, \App\Config::TRACY_DEBUGGER_EMAIL);
				$tracyEmailHelpPrefix = 'Tracy\'s "email-sent" file ';
				if (file_exists(\App\Dashboard\Status::getTracyEmailSentFilePath()) === true) {
					printf('%s %s detected - no futher emails will be sent unless this file is removed. <a href="?delete-tracy-email-sent" onclick="return confirm(\'Are you sure, you want to delete Tracy\\\'s \\\'email-sent\\\' file?\')">Delete</a>', \App\Icons::WARNING, $tracyEmailHelpPrefix);
				} else {
					printf('%s %s not detected - in case of error, email will be sent.', \App\Icons::SUCCESS, $tracyEmailHelpPrefix);
				}
			}
			?>
			<h2>Logs</h2>
			<?php
			$maxLines = 10;
			printf('<p>Showing last %d lines per log file, oldest lines first.</p>', $maxLines);
			$now = new DateTimeImmutable();
			$logs = \App\Dashboard\Logs::getLogs($now, $maxLines);
			foreach ($logs as $logName => $logLines) {
				printf('<h4>%s <small>(%s)</small></h4>', $logName, $now->format(\App\Config::DATE_FORMAT));
				if (count($logLines) === 0) {
					printf('<p>No log for this day is available.</p>');
				} else {
					$newestLogLine = htmlentities(json_encode($logLines[0]));
					printf('<pre>%s</pre>', htmlentities(join(PHP_EOL, array_map('json_encode', $logLines))));
				}
			}
			?>
		</div>
		<div class="tab-pane fade" id="statistics">
			<h2>Statistics</h2>
			<p>
				<?php
				if (\App\Dashboard\Status::isDatabaseConnectionSet() && \App\Dashboard\Status::isDatabaseTablesSet()) {
					printf('<ul>');
					$now = new DateTimeImmutable();

					// Detected chats
					$results = [];
					$totalCount = 0;
					foreach (\App\Dashboard\Status::getChatsStats() as $groupType => $groupCount) {
						$results[] = sprintf('%s = <b>%d</b>', $groupType, $groupCount);
						$totalCount += $groupCount;
					}
					printf('<li><b>%d</b> detected chats (%s)</li>', $totalCount, join(', ', $results));

					// Detected users
					printf('<li><b>%d</b> detected users (wrote at least one message or command)</li>', \App\Dashboard\Status::getUsersCount());

					// Newest user
					$newestUser = \App\Dashboard\Status::getNewestUser();
					if ($newestUser) {
						printf('<li>Most recent active user:<br>ID = <b>%d</b><br>TG ID = <b>%d</b><br>TG Name = <b>%s</b><br>Registered = <b>%s</b> (%s ago)<br>Last update = <b>%s</b> (%s ago)</li>',
							$newestUser['user_id'],
							$newestUser['user_telegram_id'],
							$newestUser['user_telegram_name'] ? sprintf('<a href="https://t.me/%1$s" target="_blank">%1$s</a>', $newestUser['user_telegram_name']) : '<i>unknown</i>',
							$newestUser['user_registered']->format(DateTimeInterface::W3C),
							\App\Utils\General::sToHuman($now->getTimestamp() - $newestUser['user_registered']->getTimestamp()),
							$newestUser['user_last_update']->format(DateTimeInterface::W3C),
							\App\Utils\General::sToHuman($now->getTimestamp() - $newestUser['user_last_update']->getTimestamp()),
						);
					}

					// Last changed user
					$lastChangedUser = \App\Dashboard\Status::getLatestChangedUser();
					if ($lastChangedUser) {
						printf('<li>Newest registered user:<br>ID = <b>%d</b><br>TG ID = <b>%d</b><br>TG Name = <b>%s</b><br>Registered = <b>%s</b> (%s ago)<br>Last update = <b>%s</b> (%s ago)</li>',
							$lastChangedUser['user_id'],
							$lastChangedUser['user_telegram_id'],
							$lastChangedUser['user_telegram_name'] ? sprintf('<a href="https://t.me/%1$s" target="_blank">%1$s</a>', $lastChangedUser['user_telegram_name']) : '<i>unknown</i>',
							$lastChangedUser['user_registered']->format(DateTimeInterface::W3C),
							\App\Utils\General::sToHuman($now->getTimestamp() - $lastChangedUser['user_registered']->getTimestamp()),
							$lastChangedUser['user_last_update']->format(DateTimeInterface::W3C),
							\App\Utils\General::sToHuman($now->getTimestamp() - $lastChangedUser['user_last_update']->getTimestamp()),
						);
					}
					printf('</ul>');
				} else {
					printf('<p>%s Setup database connection and prepare tables.</p>', \App\Icons::ERROR);
				}
				?>
			</p>
		</div>
		<div class="tab-pane fade" id="tester">
			<h2>Tester</h2>
			<div id="tester">
				<?php
				$tester = new \App\Dashboard\Tester($_POST['input'] ?? null);
				?>
				<form method="POST">
					<textarea name="input" class="form-control" placeholder="Type something..."><?= $tester->getTextareaInput() ?></textarea>
					<button type="submit" class="btn btn-primary">Send</button>
				</form>
				<h3>Result</h3>
				<div class="result">
					<?php
					if ($tester->isInput()) {
						$tester->handleInput();
						if ($tester->isOutputTextEmpty()) {
							print('<div class="alert alert-info">No location was detected</div>');
						} else {
							printf('<div class="message">');
							printf('<pre>%s</pre>', $tester->getOutputText());
							foreach ($tester->getOutputButtons() as $row) {
								printf('<div class="row">');
								foreach ($row as $button) {
									printf('<div class="col buttons">');
									if (empty($button->url) === false) {
										printf('<a href="%1$s" class="btn btn-secondary" target="_blank" data-toggle="tooltip" title="%1$s">%2$s</a>', $button->url, $button->text);
									} else if ($button->callback_data) {
										printf('<button class="btn btn-secondary" data-toggle="tooltip" title="Callback data: \'%s\'">%s</button>', $button->callback_data, $button->text);
									} else {
										throw new \OutOfBoundsException('Unexpected button type.');
									}
									printf('</div>');
								}
								printf('</div>');
							}
							printf('</div>');
						}
					} else {
						print('<div class="alert alert-info">Fill and send some data.</div>');
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
<script src="asset/js/main.js"></script>
</body>
