<?php declare(strict_types=1);

namespace App;

use App\TelegramCustomWrapper\Events\Command\Command;
use App\TelegramCustomWrapper\Events\Command\FavouritesCommand;
use App\TelegramCustomWrapper\Events\Command\FeedbackCommand;
use App\TelegramCustomWrapper\Events\Command\HelpCommand;
use App\TelegramCustomWrapper\Events\Command\LoginCommand;
use App\TelegramCustomWrapper\Events\Command\SettingsCommand;
use Nette\Http\UrlImmutable;
use Nette\Utils\Strings;

/**
 * Warning: Never update this file directly, always update config.local.php in data folder!
 *
 * You can override any constant from this file if you want.
 */
class DefaultConfig
{
	const FOLDER_DATA = __DIR__;
	const FOLDER_TEMP = __DIR__ . '/../../temp';

	/** @var string Basic URL used across application (web, webhook, static image, ...) */
	protected const APP_URL = 'https://your-domain.com/some/path';

	const DB_SERVER = 'localhost';
	const DB_USER = 'dbuser';
	const DB_PASS = 'dbpass';
	const DB_NAME = 'dbschema';

	const TRACY_DEVELOPMENT_IPS = [
		'12.34.56.78',
	];
	/** @var ?string Put your email if you want to receive emails about errors and exceptions. See https://tracy.nette.org/guide for more info. null to disable */
	const TRACY_DEBUGGER_EMAIL = null;  // null to disable
	// const TRACY_DEBUGGER_EMAIL = 'admin@your-domain.com';

	/** @var string Telegram bot token generated from BotFather: https://t.me/BotFather */
	const TELEGRAM_BOT_TOKEN = '123456789:abcdefghijklmnopqrstuvwxyzabcdefghi';
	/** @var string Telegram bot name without @ prefix. */
	const TELEGRAM_BOT_NAME = 'ExampleBot';

	/**
	 * @var string Telegram webhook password to secure webhook access. To provide proper compatibility, it should:
	 * - be random
	 * - contain only alphanumeric characters
	 * - be "long enough" (its up to you but I would go to at least 20 characters)
	 */
	const TELEGRAM_WEBHOOK_PASSWORD = 'someRandomPassword';
	/** @var int Telegram webhook URL, which will automatically receive all events from bot (in this application it should lead to webhook.php) */
	const TELEGRAM_INLINE_CACHE = 300; // https://core.telegram.org/bots/api#answerinlinequery cache_time attribute (default 300)
    /**
     * Maximum allowed number of simultaneous HTTPS connections to the webhook for update delivery.
     * Use lower values to limit the load on your bot‘s server, and higher values to increase your bot’s throughput.
     * @var int 1-100, Defauts to 40
     */
	const TELEGRAM_MAX_CONNECTIONS = 40;
	/** @var int Enforce BotUsername in command, eg. /command@BetterLocationBot */
	const TELEGRAM_COMMAND_STRICT = false;

	/** @var int Limit how many locations can be sent as Telegram message */
	const TELEGRAM_MAXIMUM_LOCATIONS = 10;

	/** @var array<string, array<Command>> */
	const TELEGRAM_COMMANDS = [
		'all_private_chats' => [
			HelpCommand::class,
			FeedbackCommand::class,
			FavouritesCommand::class,
			SettingsCommand::class,
			LoginCommand::class,
		],
		'all_group_chats' => [
			HelpCommand::class,
			FeedbackCommand::class,
		],
		'all_chat_administrators' => [
			HelpCommand::class,
			FeedbackCommand::class,
			SettingsCommand::class,
		],
	];

	/** @var ?string API Key for using Google Place API: https://developers.google.com/places/web-service/search null to disable */
	const GOOGLE_PLACE_API_KEY = null;
	//const GOOGLE_PLACE_API_KEY = 'someRandomGeneratedApiKeyFromGoogleCloudPlatform';

	/** @var ?string API Key for Google Maps Static API: https://developers.google.com/maps/documentation/maps-static null to disable */
	const GOOGLE_MAPS_STATIC_API_KEY = null;
	//const GOOGLE_MAPS_STATIC_API_KEY = 'someRandomGeneratedApiKeyFromGoogleCloudPlatform';

	/** @var ?string API key to What3Word service https://developer.what3words.com/public-api or null to disable */
	const W3W_API_KEY = null;

	/** @var ?string */
	const GLYMPSE_API_USERNAME = null;
	/** @var ?string */
	const GLYMPSE_API_PASSWORD = null;
	/** @var ?string */
	const GLYMPSE_API_KEY = null;

	/** @var ?string Cookie of logged user to geocaching.com or null to disable */
	const GEOCACHING_COOKIE = null;

	/** @var ?string */
	const FOURSQUARE_CLIENT_ID = null;
	/** @var ?string */
	const FOURSQUARE_CLIENT_SECRET = null;

	/** @var ?string */
	const INGRESS_MOSAIC_COOKIE_SESSION = null;
	/** @var ?string */
	const INGRESS_MOSAIC_COOKIE_XSRF = null;

	/** @var ?string https://docs.microsoft.com/en-us/bingmaps/getting-started/bing-maps-dev-center-help/getting-a-bing-maps-key */
	const BING_STATIC_MAPS_TOKEN = null;

	/**
	 * If some input (URL) has multiple different locations, how far it has to be from main coordinate to add special line
	 * to notify, that these locations are too far away. Anything lower than this number will be removed from collection
	 *
	 * @var int|float distance in meters
	 */
	const DISTANCE_IGNORE = 10;

	/**
	 * How often refreshable location can be manually refreshed.
	 *
	 * @var int cooldown in seconds
	 */
	const REFRESH_COOLDOWN = 30;

	/** @var int How many autorefreshed messages can be in one chat */
	const REFRESH_AUTO_MAX_PER_CHAT = 5;

	/** @var int How many object to run autorefresh is loaded from database in one cron run */
	const REFRESH_CRON_MAX_UPDATES = 5;

	/** @var int How many seconds has to elapse since last refresh */
	const REFRESH_CRON_MIN_OLD = 300;

	/**
	 * @var string URL for requests for Nominatim search engine.
	 *
	 * @link https://nominatim.openstreetmap.org/ui/about.html
	 * @link https://operations.osmfoundation.org/policies/nominatim/
	 * @link https://github.com/maxhelias/php-nominatim
	 */
	const NOMINATIM_URL = 'https://nominatim.openstreetmap.org';

	/**
	 * Identificator to indentify application.
	 */
	const NOMINATIM_USER_AGENT = 'BetterLocation';

	/**
	 * Identificator to requests to Geonames API. Registration is required.
	 * @link https://www.geonames.org/export/
	 */
	const GEONAMES_USERNAME = 'BetterLocation';

	const DATE_FORMAT = 'Y-m-d';
	const TIME_FORMAT = 'H:i:s';
	const TIME_FORMAT_ZONE = self::TIME_FORMAT . ' T';
	const DATETIME_FORMAT = self::DATE_FORMAT . ' ' . self::TIME_FORMAT;
	const DATETIME_FORMAT_ZONE = self::DATETIME_FORMAT . ' T';

	const DATE_FILE_FORMAT = self::DATE_FORMAT;
	const TIME_FILE_FORMAT = 'H.i.s';
	const TIME_FILE_FORMAT_ZONE = self::TIME_FILE_FORMAT . ' T';
	const DATETIME_FILE_FORMAT = self::DATE_FILE_FORMAT . '_' . self::TIME_FILE_FORMAT;
	const DATETIME_FILE_FORMAT_ZONE = self::DATETIME_FILE_FORMAT . 'T';

	const CACHE_TTL_FOURSQUARE_API = 60 * 60 * 24;
	const CACHE_TTL_GOOGLE_PLACE_API = 60 * 60 * 24;
	const CACHE_TTL_GOOGLE_STREETVIEW_API = 60 * 60 * 24;
	const CACHE_TTL_GOOGLE_GEOCODE_API = 60 * 60 * 24;
	const CACHE_TTL_GOOGLE_MAPS = 60 * 60 * 24;
	const CACHE_TTL_GEOCACHING_API = 60 * 60 * 24;
	const CACHE_TTL_WAYMARKING = 60 * 60 * 24;
	const CACHE_TTL_INGRESS_LANCHED_RU_API = 60 * 60 * 24;
	const CACHE_TTL_DROBNE_PAMATKY_CZ = 60 * 60 * 24;
	const CACHE_TTL_ROPIKY_NET = 60 * 60 * 24;
	const CACHE_TTL_ZNICENE_KOSTELY_CZ = 60 * 60 * 24;
	const CACHE_TTL_ZANIKLE_OBCE_CZ = 60 * 60 * 24;
	const CACHE_TTL_HERE_WE_GO_LOC = 60 * 60 * 24;
	const CACHE_TTL_WIKIPEDIA = 60 * 60 * 24;
	const CACHE_TTL_INGRESS_MOSAIC = 60 * 60 * 24;
	const CACHE_TTL_BANNERGRESS = 60 * 60 * 24;
	const CACHE_TTL_FACEBOOK = 60 * 60 * 24;
	const CACHE_TTL_FEVGAMES = 60 * 60 * 24;
	const CACHE_TTL_SUMAVA_CZ = 60 * 60 * 24;
	const CACHE_TTL_ESTUDANKY_EU = 60 * 60 * 24;
	const CACHE_TTL_HRADY_CZ = 60 * 60 * 24;
	const CACHE_TTL_OPEN_ELEVATION = 60 * 60 * 24;
	const CACHE_TTL_PRAZDNE_DOMY = 60 * 60 * 24;

	/** @var string[] */
	const API_KEYS = [];
	/** @var ?string */
	const CRON_PASSWORD = null;
	/** @var ?string */
	const ADMIN_PASSWORD = null;

	/** @var int How long input text must be to start Google API searching */
	const GOOGLE_SEARCH_MIN_LENGTH = 3;

	/**
	 * @var string Default timezone to work with.
	 * Disclaimer: Changing might result in unexpected behaviour of this app. Make sure that you know, what you are doing.
	 */
	const TIMEZONE = 'UTC';

	const FOLDER_TEMPLATES = __DIR__ . '/../templates';

	public static function isGlympse(): bool
	{
		return (
			is_null(static::GLYMPSE_API_USERNAME) === false &&
			is_null(static::GLYMPSE_API_PASSWORD) === false &&
			is_null(static::GLYMPSE_API_KEY) === false
		);
	}

	public static function isTelegram(): bool
	{
		return (
			self::isTelegramWebhookPassword() &&
			self::isTelegramBotToken() &&
			self::isTelegramBotName()
		);
	}

	public static function isTelegramWebhookPassword(): bool
	{
		return (Config::TELEGRAM_WEBHOOK_PASSWORD !== DefaultConfig::TELEGRAM_WEBHOOK_PASSWORD && is_string(Config::TELEGRAM_WEBHOOK_PASSWORD));
	}

	public static function isTelegramBotToken(): bool
	{
		return (Config::TELEGRAM_BOT_TOKEN !== DefaultConfig::TELEGRAM_BOT_TOKEN && is_string(Config::TELEGRAM_BOT_TOKEN));
	}

	public static function isTelegramBotName(): bool
	{
		return (Config::TELEGRAM_BOT_NAME !== DefaultConfig::TELEGRAM_BOT_NAME && is_string(Config::TELEGRAM_BOT_NAME));
	}

	public static function getTelegramWebhookUrl(): UrlImmutable
	{
		return static::getAppUrl('/webhook/telegram.php');
	}

	public static function isIngressMosaic(): bool
	{
		return (
			is_null(static::INGRESS_MOSAIC_COOKIE_SESSION) === false &&
			is_null(static::INGRESS_MOSAIC_COOKIE_XSRF) === false
		);
	}

	public static function isFoursquare(): bool
	{
		return (
			is_null(static::FOURSQUARE_CLIENT_ID) === false &&
			is_null(static::FOURSQUARE_CLIENT_SECRET) === false
		);
	}

	public static function isGoogleGeocodingApi(): bool
	{
		return is_null(static::GOOGLE_PLACE_API_KEY) === false;
	}

	public static function isGoogleStreetViewStaticApi(): bool
	{
		return is_null(static::GOOGLE_PLACE_API_KEY) === false;
	}

	public static function isGooglePlaceApi(): bool
	{
		return is_null(static::GOOGLE_PLACE_API_KEY) === false;
	}

	public final static function getAppUrl(string $path = null): UrlImmutable
	{
		$appUrl = new UrlImmutable(static::APP_URL);
		if ($path !== null && Strings::startsWith($path, '/')) {
			$appUrl = $appUrl->withPath(rtrim($appUrl->getPath(), '/') . $path);
		}
		return $appUrl;
	}

	public final static function getLoginUrl(UrlImmutable $redirectUrl = null): UrlImmutable
	{
		$loginURl = static::getAppUrl('/login.php');
		if ($redirectUrl) {
			$loginURl = $loginURl->withQueryParameter('redirect', $redirectUrl->getAbsoluteUrl());
		}
		return $loginURl;
	}

	public final static function getStaticImageUrl(string $id): UrlImmutable
	{
		return static::getAppUrl('/api/staticmap.php')->withQueryParameter('id', $id);
	}

	public static function getTimezone(): \DateTimeZone
	{
		return new \DateTimeZone(static::TIMEZONE);
	}
}
