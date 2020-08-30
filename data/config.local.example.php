<?php
// Set database connection settings
DEFINE('DB_SERVER', 'localhost');
DEFINE('DB_USER', 'root');
DEFINE('DB_PASS', 'password');
DEFINE('DB_NAME', 'better_location');

// List of your IP address for development
DEFINE('DEVELOPMENT_IPS', [
	'12.34.56.78',
]);

// Put your email if you want to receive emails about errors and exceptions. See https://tracy.nette.org/guide for more info.
DEFINE('TRACY_DEBUGGER_EMAIL', null);  // null to disable
// DEFINE('TRACY_DEBUGGER_EMAIL', 'admin@your-domain.com');

// Telegram bot token generated from BotFather: https://t.me/BotFather
DEFINE('TELEGRAM_BOT_TOKEN', '123456789:afsddfsggfergfgsadfdiswefqjdfbjfddt');
// Telegram bot name without @ prefix.
DEFINE('TELEGRAM_BOT_NAME', 'BetterLocationBot');
// Telegram webhook URL, which will automatically receive all events from bot (in this application it should lead to webhook.php)
DEFINE('TELEGRAM_WEBHOOK_URL', 'https://your-domain.com/better-location/webhook.php');
// Telegram webhook URL, which will automatically receive all events from bot (in this application it should lead to webhook.php)
DEFINE('TELEGRAM_INLINE_CACHE', 300); // https://core.telegram.org/bots/api#answerinlinequery cache_time attribute (default 300)

// API Key for using Google Place API: https://developers.google.com/places/web-service/search
DEFINE('GOOGLE_PLACE_API_KEY', null); // null to disable this feature
//DEFINE('GOOGLE_PLACE_API_KEY', 'someRandomGeneratedApiKeyFromGoogleCloudPlatform');

// API key to What3Word service https://developer.what3words.com/public-api
DEFINE('W3W_API_KEY', 'SOME_API_KEY');

// URL to NodeJS dummy server to handle generating payload and requests for MapyCZ place IDs. For more info see src/nodejs/README.md
//DEFINE('MAPY_CZ_DUMMY_SERVER_URL', 'http://localhost:3055'); // URL of your webserver (without trailing slash)
DEFINE('MAPY_CZ_DUMMY_SERVER_URL', null); // null to disable this feature and fallback to using inaccurate x and y coordinates from URL
// Request timeout
DEFINE('MAPY_CZ_DUMMY_SERVER_TIMEOUT', 5); // default 5

// If some input (URL) has multiple different locations, how far it has to be from main coordinate to add special line
// to notify, that these locations are too far away. Anything lower than this number will be removed from list
DEFINE('DISTANCE_IGNORE', 10); // distance in meters (int or float)
