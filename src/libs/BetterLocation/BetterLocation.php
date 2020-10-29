<?php declare(strict_types=1);

namespace App\BetterLocation;

use App\BetterLocation\Service\AbstractService;
use App\BetterLocation\Service\Coordinates\MGRSService;
use App\BetterLocation\Service\Coordinates\USNGService;
use App\BetterLocation\Service\Coordinates\WG84DegreesMinutesSecondsService;
use App\BetterLocation\Service\Coordinates\WG84DegreesMinutesService;
use App\BetterLocation\Service\Coordinates\WG84DegreesService;
use App\BetterLocation\Service\DrobnePamatkyCzService;
use App\BetterLocation\Service\DuckDuckGoService;
use App\BetterLocation\Service\Exceptions\InvalidLocationException;
use App\BetterLocation\Service\GlympseService;
use App\BetterLocation\Service\GoogleMapsService;
use App\BetterLocation\Service\HereWeGoService;
use App\BetterLocation\Service\IngressIntelService;
use App\BetterLocation\Service\MapyCzService;
use App\BetterLocation\Service\OpenLocationCodeService;
use App\BetterLocation\Service\OpenStreetMapService;
use App\BetterLocation\Service\RopikyNetService;
use App\BetterLocation\Service\WazeService;
use App\BetterLocation\Service\WhatThreeWordService;
use App\BetterLocation\Service\WikipediaService;
use App\BetterLocation\Service\ZanikleObceCzService;
use App\BetterLocation\Service\ZniceneKostelyCzService;
use App\Config;
use App\Factory;
use App\Icons;
use App\TelegramCustomWrapper\Events\Button\FavouritesButton;
use App\TelegramCustomWrapper\TelegramHelper;
use App\Utils\Coordinates;
use App\Utils\General;
use App\Utils\StringUtils;
use Tracy\Debugger;
use unreal4u\TelegramAPI\Telegram\Types\Inline\Keyboard\Button;
use unreal4u\TelegramAPI\Telegram\Types\MessageEntity;

class BetterLocation
{
	/**
	 * List of content types for images supporting EXIF
	 *
	 * @see https://www.iana.org/assignments/media-types/media-types.xhtml#image
	 */
	const CONTENT_TYPE_IMAGE_EXIF = [
		'image/jpeg',
		'image/png',
		'image/tiff',
		'image/tiff-x',
		'image/webp',
	];

	private $lat;
	private $lon;
	private $description;
	private $prefixMessage;
	private $coordinateSuffixMessage;
	private $address;
	private $originalInput;
	private $sourceService;
	private $sourceType;
	private $pregeneratedLinks = [];

	/**
	 * BetterLocation constructor.
	 *
	 * @param string $originalInput
	 * @param float $lat
	 * @param float $lon
	 * @param string $sourceService has to be name of class extending \BetterLocation\Service\AbstractService
	 * @param string|null $sourceType
	 * @throws InvalidLocationException|Service\Exceptions\NotImplementedException
	 */
	public function __construct(string $originalInput, float $lat, float $lon, string $sourceService, ?string $sourceType = null)
	{
		$this->originalInput = $originalInput;
		if (self::isLatValid($lat) === false) {
			throw new InvalidLocationException('Latitude coordinate must be between or equal from -90 to 90 degrees.');
		}
		$this->lat = $lat;
		if (self::isLonValid($lon) === false) {
			throw new InvalidLocationException('Longitude coordinate must be between or equal from -180 to 180 degrees.');
		}
		$this->lon = $lon;
		if (class_exists($sourceService) === false) {
			throw new InvalidLocationException(sprintf('Invalid source service: "%s".', $sourceService));
		}
		if (is_subclass_of($sourceService, AbstractService::class) === false) {
			throw new InvalidLocationException(sprintf('Source service has to be subclass of "%s".', AbstractService::class));
		}

		$this->sourceService = $sourceService;
		$sourceTypes = $sourceService::getConstants();
		if (count($sourceTypes) === 0 && $sourceType !== null) {
			throw new InvalidLocationException(sprintf('Service "%s" doesn\'t contain any types so $sourceType has to be null, not "%s"', $sourceService, $sourceType));
		}
		if (count($sourceTypes) > 0) {
			if ($sourceType === null) {
				throw new InvalidLocationException(sprintf('Missing source type for service "%s"', $sourceService));
			}
			if (in_array($sourceType, $sourceTypes) === false) {
				throw new InvalidLocationException(sprintf('Invalid source type "%s" for service "%s".', $sourceType, $sourceService));
			}
		}
		$this->sourceType = $sourceType;

		$generatedPrefix = $sourceService::NAME;
		if ($this->sourceType) {
			$generatedPrefix .= ' ' . $this->sourceType;
		}
		if (preg_match('/^https?:\/\//', $this->originalInput)) {
			$generatedPrefix = sprintf('<a href="%s">%s</a>', $this->originalInput, $generatedPrefix);
		}
		$this->setPrefixMessage($generatedPrefix);

		// pregenerate link for MapyCz if contains source and ID (@see https://github.com/DJTommek/better-location/issues/17)
		if ($sourceService === MapyCzService::class && $sourceType === MapyCzService::TYPE_PLACE_ID) {
			$parsedUrl = General::parseUrl($originalInput);
			$generatedUrl = MapyCzService::getLink($this->lat, $this->lon);
			$generatedUrl = str_replace(sprintf('%F%%2C%F', $this->lon, $this->lat), $parsedUrl['query']['id'], $generatedUrl);
			$generatedUrl = str_replace('source=coor', 'source=' . $parsedUrl['query']['source'], $generatedUrl);
			$this->pregeneratedLinks[MapyCzService::class] = $generatedUrl;
		}
	}

	public function getName()
	{
		return $this->sourceType;
	}

	private static function handleShortUrl(string $url): string
	{
		$originalUrl = $url;
		$tries = 0;
		while (is_null($url) === false && Url::isShortUrl($url)) {
			if ($tries >= 5) {
				Debugger::log(sprintf('Too many tries (%d) for translating original URL "%s"', $tries, $originalUrl));
			}
			$url = Url::getRedirectUrl($url);
			$tries++;
		}
		if (is_null($url)) { // in case of some error, revert to original URL
			$url = $originalUrl;
		}
		return $url;
	}

	/**
	 * @param string $message
	 * @param MessageEntity[] $entities
	 * @return BetterLocationCollection | \InvalidArgumentException[]
	 * @throws \Exception
	 */
	public static function generateFromTelegramMessage(string $message, array $entities): BetterLocationCollection
	{
		$betterLocationsCollection = new BetterLocationCollection();

		foreach ($entities as $entity) {
			if (in_array($entity->type, ['url', 'text_link'])) {
				$url = TelegramHelper::getEntityContent($message, $entity);

				if (self::isTrueUrl($url) === false) {
					continue;
				}

				$url = self::handleShortUrl($url);

				try {
					if (GoogleMapsService::isValid($url)) {
						$googleMapsBetterLocationCollection = GoogleMapsService::parseCoordsMultiple($url);
						$googleMapsBetterLocationCollection->filterTooClose(Config::DISTANCE_IGNORE);
						$betterLocationsCollection->mergeCollection($googleMapsBetterLocationCollection);
					} else if (MapyCzService::isValid($url)) {
						$mapyCzBetterLocationCollection = MapyCzService::parseCoordsMultiple($url);
						$mapyCzBetterLocationCollection->filterTooClose(Config::DISTANCE_IGNORE);
						$betterLocationsCollection->mergeCollection($mapyCzBetterLocationCollection);
					} else if (OpenStreetMapService::isValid($url)) {
						$betterLocationsCollection[] = OpenStreetMapService::parseCoords($url);
					} else if (HereWeGoService::isValid($url)) {
						$hereBetterLocationCollection = HereWeGoService::parseCoordsMultiple($url);
						$hereBetterLocationCollection->filterTooClose(Config::DISTANCE_IGNORE);
						$betterLocationsCollection->mergeCollection($hereBetterLocationCollection);
					} else if (WikipediaService::isValid($url)) {
						try {
							$betterLocationsCollection[] = WikipediaService::parseCoords($url);
						} catch (InvalidLocationException $exception) {
							// @HACK workaround to not show error in chat, if processing Wikipedia link without location
						}
					} else if (OpenLocationCodeService::isValid($url)) {
						$betterLocationsCollection[] = OpenLocationCodeService::parseCoords($url);
					} else if (WazeService::isValid($url)) {
						$betterLocationsCollection[] = WazeService::parseCoords($url);
					} else if (is_null(Config::W3W_API_KEY) === false && WhatThreeWordService::isValid($url)) {
						$betterLocationsCollection[] = WhatThreeWordService::parseCoords($url);
					} else if (Config::isGlympse() && GlympseService::isValid($url)) {
						$glympseBetterLocationCollection = GlympseService::parseCoordsMultiple($url);
						$betterLocationsCollection->mergeCollection($glympseBetterLocationCollection);
					} else if (IngressIntelService::isValid($url)) {
						$betterLocationsCollection[] = IngressIntelService::parseCoords($url);
					} else if (DuckDuckGoService::isValid($url)) {
						$betterLocationsCollection[] = DuckDuckGoService::parseCoords($url);
					} else if (RopikyNetService::isValid($url)) {
						$betterLocationsCollection[] = RopikyNetService::parseCoords($url);
					} else if (DrobnePamatkyCzService::isValid($url)) {
						$betterLocationsCollection[] = DrobnePamatkyCzService::parseCoords($url);
					} else if (ZniceneKostelyCzService::isValid($url)) {
						$betterLocationsCollection[] = ZniceneKostelyCzService::parseCoords($url);
					} else if (ZanikleObceCzService::isValid($url)) {
						try {
							$betterLocationsCollection[] = ZanikleObceCzService::parseCoords($url);
						} catch (InvalidLocationException $exception) {
							// @HACK workaround to not show error in chat, if processing Wikipedia link without location
						}
					} else {
						$headers = null;
						try {
							$headers = General::getHeaders($url, [
								CURLOPT_CONNECTTIMEOUT => 5,
								CURLOPT_TIMEOUT => 5,
							]);
						} catch (\Throwable$exception) {
							Debugger::log(sprintf('Error while loading headers for URL "%s": %s', $url, $exception->getMessage()));
						}
						if ($headers && isset($headers['content-type']) && General::checkIfValueInHeaderMatchArray($headers['content-type'], self::CONTENT_TYPE_IMAGE_EXIF)) {
							$betterLocationExif = BetterLocation::fromExif($url);
							if ($betterLocationExif instanceof BetterLocation) {
								$betterLocationExif->setPrefixMessage(sprintf('<a href="%s">EXIF</a>', $url));
								$betterLocationsCollection[] = $betterLocationExif;
							}
						}
					}
				} catch (\Exception $exception) {
					$betterLocationsCollection[] = $exception;
				}
			}
		}

		$messageWithoutUrls = self::getMessageWithoutUrls($message, $entities);
		$messageWithoutUrls = StringUtils::translit($messageWithoutUrls);

		$betterLocationsCollection->mergeCollection(WG84DegreesService::findInText($messageWithoutUrls));
		$betterLocationsCollection->mergeCollection(WG84DegreesMinutesService::findInText($messageWithoutUrls));
		$betterLocationsCollection->mergeCollection(WG84DegreesMinutesSecondsService::findInText($messageWithoutUrls));
		$betterLocationsCollection->mergeCollection(MGRSService::findInText($messageWithoutUrls));
		$betterLocationsCollection->mergeCollection(USNGService::findInText($messageWithoutUrls));

		// OpenLocationCode (Plus codes)
		$openLocationCodes = preg_match_all(OpenLocationCodeService::RE_IN_STRING, $messageWithoutUrls, $matches);
		if ($openLocationCodes) {
			foreach ($matches[2] as $plusCode) {
				try {
					if (OpenLocationCodeService::isValid($plusCode)) {
						$betterLocationsCollection[] = OpenLocationCodeService::parseCoords($plusCode);
					}
				} catch (\Exception $exception) {
					$betterLocationsCollection[] = $exception;
				}
			}
		}

		// What Three Word
		if (is_null(Config::W3W_API_KEY) === false && preg_match_all(WhatThreeWordService::RE_IN_STRING, $messageWithoutUrls, $matches)) {
			for ($i = 0; $i < count($matches[0]); $i++) {
				$words = $matches[0][$i];
				try {
					if (WhatThreeWordService::isWords($words)) {
						$betterLocationsCollection[] = WhatThreeWordService::parseCoords($words);
					}
				} catch (\Exception $exception) {
					$betterLocationsCollection[] = $exception;
				}
			}
		}

		$betterLocationsCollection->deduplicate();

		return $betterLocationsCollection;
	}

	public function export(): array
	{
		return [
			'lat' => $this->getLat(),
			'lon' => $this->getLon(),
			'service' => strip_tags($this->getPrefixMessage()),
		];
	}

	public function generateScreenshotLink(string $serviceClass)
	{
		if (class_exists($serviceClass) === false) {
			throw new \InvalidArgumentException(sprintf('Invalid location service: "%s".', $serviceClass));
		}
		if (is_subclass_of($serviceClass, AbstractService::class) === false) {
			throw new \InvalidArgumentException(sprintf('Source service has to be subclass of "%s".', AbstractService::class));
		}
		if (method_exists($serviceClass, 'getScreenshotLink') === false) {
			throw new \InvalidArgumentException(sprintf('Source service "%s" does not supports screenshot links.', $serviceClass));
		}
		/** @var $services AbstractService[] */
		return $serviceClass::getScreenshotLink($this->getLat(), $this->getLon());
	}

	public function setAddress(string $address)
	{
		$this->address = $address;
	}

	public function getAddress(): ?string
	{
		return $this->address;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function generateAddress()
	{
		if (is_null($this->address)) {
			try {
				$result = Factory::WhatThreeWords()->convertTo3wa($this->getLat(), $this->getLon());
			} catch (\Exception $exception) {
				throw new \Exception('Unable to get address from W3W API');
			}
			if ($result) {
				$this->address = sprintf('Nearby: %s, %s', $result['nearestPlace'], $result['country']);
			} else {
				throw new \Exception('Unable to get address from W3W API');
			}
		}
		return $this->address;
	}

	/**
	 * Skip URLs without defined scheme, eg "tomas.palider.cz" but allow "https://tomas.palider.cz/"
	 */
	private static function isTrueUrl(string $url): bool
	{
		$parsedUrl = General::parseUrl($url);
		return (isset($parsedUrl['scheme']) && isset($parsedUrl['host']));
	}

	/**
	 * Remove all URLs from Telegram message according entities.
	 * URLs will be replaced with ||| proper length to keep entity offset and length valid eg.:
	 * 'Hello https://t.me/ here!'
	 * ->
	 * 'Hello ||||||||||||| here!'
	 *
	 * @param string $text
	 * @param MessageEntity[] $entities
	 * @return string
	 */
	private static function getMessageWithoutUrls(string $text, array $entities): string
	{
		foreach (array_reverse($entities) as $entity) {
			if ($entity->type === 'url') {
				$entityContent = TelegramHelper::getEntityContent($text, $entity);
				if (self::isTrueUrl($entityContent)) {
					$text = str_replace($entityContent, str_repeat('|', $entity->length), $text);
				}
			}
		}
		return $text;
	}

	/**
	 * @param string|resource $input Path or URL link to file or resource (see https://php.net/manual/en/function.exif-read-data.php)
	 * @return BetterLocation|null
	 * @throws InvalidLocationException|Service\Exceptions\NotImplementedException
	 */
	public static function fromExif($input): ?BetterLocation
	{
		if (is_string($input) === false && is_resource($input) === false) {
			throw new \InvalidArgumentException('Input must be string or resource.');
		}
		// Bug on older versions of PHP "Warning: exif_read_data(): Process tag(x010D=DocumentNam): Illegal components(0)" Tested with:
		// WEDOS Linux 7.3.1 (NOT OK)
		// WAMP Windows 7.3.5 (NOT OK)
		// WAMP Windows 7.4.7 (OK)
		// https://bugs.php.net/bug.php?id=77142
		$exif = @exif_read_data($input);
		if (
			$exif &&
			isset($exif['GPSLatitude']) &&
			isset($exif['GPSLongitude']) &&
			isset($exif['GPSLatitudeRef']) &&
			isset($exif['GPSLongitudeRef'])
		) {
			$betterLocationExif = new BetterLocation(
				json_encode([$exif['GPSLatitude'], $exif['GPSLatitudeRef'], $exif['GPSLongitude'], $exif['GPSLongitudeRef']]),
				Coordinates::exifToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef']),
				Coordinates::exifToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef']),
				WG84DegreesService::class,
			);
			$betterLocationExif->setPrefixMessage('EXIF');
			return $betterLocationExif;
		} else {
			return null;
		}
	}

	/**
	 * Generate BetterLocation Telegram message
	 *
	 * @param bool $withAddress
	 * @return string
	 * @throws \Exception
	 */
	public function generateBetterLocation($withAddress = true): string
	{
		/** @var $services AbstractService[] */
		$services = [
			GoogleMapsService::class,
			MapyCzService::class,
			DuckDuckGoService::class,
			WazeService::class,
			HereWeGoService::class,
			OpenStreetMapService::class,
			IngressIntelService::class,
		];
		$text = '';
		$text .= sprintf('%s <a href="%s" target="_blank">%s</a> <code>%s</code>',
			$this->prefixMessage,
			$this->generateScreenshotLink(MapyCzService::class),
			Icons::PICTURE,
			$this->__toString()
		);
		if ($this->getCoordinateSuffixMessage()) {
			$text .= ' ' . $this->getCoordinateSuffixMessage();
		}
		$text .= PHP_EOL;

		// Generate links
		$text .= join(' | ', \array_map(function (string $service) {
				return sprintf('<a href="%s" target="_blank">%s</a>',
					$this->pregeneratedLinks[$service] ?? $service::getLink($this->lat, $this->lon),
					$service::NAME,
				);
			}, $services)) . PHP_EOL;

		if ($withAddress && is_null($this->address) === false) {
			$text .= $this->getAddress() . PHP_EOL;
		}

		if ($this->description) {
			$text .= $this->description . PHP_EOL;
		}

		return $text . PHP_EOL;
	}

	public function generateDriveButtons()
	{
		/** @var $services AbstractService[] */
		$services = [
			GoogleMapsService::class,
			WazeService::class,
			HereWeGoService::class,
		];
		$buttons = [];
		foreach ($services as $service) {
			$button = new Button();
			$button->text = sprintf('%s %s', $service::NAME, Icons::CAR);
			$button->url = $service::getLink($this->lat, $this->lon, true);
			$buttons[] = $button;
		}
		return $buttons;
	}

	public function generateAddToFavouriteButtton(): Button
	{
		$button = new Button();
		$button->text = Icons::FAVOURITE;
		$button->callback_data = sprintf('%s %s %F %F', FavouritesButton::CMD, FavouritesButton::ACTION_ADD, $this->getLat(), $this->getLon());
		return $button;
	}

	/**
	 * @param string $prefixMessage
	 */
	public function setPrefixMessage(string $prefixMessage): void
	{
		$this->prefixMessage = $prefixMessage;
	}

	/**
	 * @return mixed
	 */
	public function getPrefixMessage(): ?string
	{
		return $this->prefixMessage;
	}

	/**
	 * @param string $coordinateSuffixMessage
	 */
	public function setCoordinateSuffixMessage(string $coordinateSuffixMessage): void
	{
		$this->coordinateSuffixMessage = $coordinateSuffixMessage;
	}

	public function getCoordinateSuffixMessage(): ?string
	{
		return $this->coordinateSuffixMessage;
	}

	public function getLink($class, bool $drive = false)
	{
		if ($class instanceof AbstractService === false) {
			throw new \InvalidArgumentException(sprintf('Class must be instance of "%s"', AbstractService::class));
		}
		return $class::getLink($this->lat, $this->lon, $drive);
	}

	public function getLat(): float
	{
		return $this->lat;
	}

	public function getLon(): float
	{
		return $this->lon;
	}

	public function getLatLon(): array
	{
		return [$this->lat, $this->lon];
	}

	public function __toString()
	{
		return sprintf('%F,%F', $this->lat, $this->lon);
	}

	/**
	 * @param string|null $description
	 */
	public function setDescription(?string $description): void
	{
		$this->description = $description;
	}

	public static function isLatValid(float $lat): bool
	{
		return ($lat <= 90 && $lat >= -90);
	}

	public static function isLonValid(float $lon): bool
	{
		return ($lon <= 180 && $lon >= -180);
	}

	public static function fromLatLon(float $lat, float $lon): self
	{
		return new BetterLocation(sprintf('%F,%F', $lat, $lon), $lat, $lon, WG84DegreesService::class);
	}
}
