<?php

declare(strict_types=1);

namespace BetterLocation;

use BetterLocation\Service\Coordinates\MGRSService;
use BetterLocation\Service\Coordinates\USNGService;
use BetterLocation\Service\Coordinates\WG84DegreesMinutesSecondsService;
use BetterLocation\Service\Coordinates\WG84DegreesMinutesService;
use BetterLocation\Service\Coordinates\WG84DegreesService;
use BetterLocation\Service\Exceptions\InvalidLocationException;
use \BetterLocation\Service\GoogleMapsService;
use \BetterLocation\Service\HereWeGoService;
use \BetterLocation\Service\IngressIntelService;
use \BetterLocation\Service\MapyCzService;
use \BetterLocation\Service\OpenStreetMapService;
use \BetterLocation\Service\OpenLocationCodeService;
use \BetterLocation\Service\WazeService;
use \BetterLocation\Service\WhatThreeWordService;
use TelegramCustomWrapper\Events\Button\FavouritesButton;
use TelegramCustomWrapper\Events\Command\FavouritesCommand;
use unreal4u\TelegramAPI\Telegram\Types\Inline\Keyboard\Button;
use \Utils\General;

class BetterLocation
{
	private $lat;
	private $lon;
	private $description;
	private $prefixMessage;
	private $address;
	private $originalInput;
	private $sourceService;
	private $sourceType;

	/**
	 * BetterLocation constructor.
	 *
	 * @param string $originalInput
	 * @param float $lat
	 * @param float $lon
	 * @param string $sourceService has to be name of class extending \BetterLocation\Service\AbstractService
	 * @param string|null $sourceType
	 * @throws InvalidLocationException
	 */
	public function __construct(string $originalInput, float $lat, float $lon, string $sourceService, ?string $sourceType = null) {
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
		if (is_subclass_of($sourceService, \BetterLocation\Service\AbstractService::class) === false) {
			throw new InvalidLocationException(sprintf('Source service has to be subclass of "%s".', \BetterLocation\Service\AbstractService::class));
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
			if (in_array($sourceType, $sourceService::getConstants()) === false) {
				throw new InvalidLocationException(sprintf('Invalid source type "%s" for service "%s".', $sourceType, $sourceService));
			}
		}
		$this->sourceType = $sourceType;

		if ($this->sourceType) {
			$this->setPrefixMessage(sprintf('<a href="%s">%s %s</a>', $this->originalInput, $sourceService::NAME, $this->sourceType));
		} else {
			$this->setPrefixMessage(sprintf('<a href="%s">%s</a>', $this->originalInput, $sourceService::NAME));
		}
	}

	public function getName() {
		return $this->sourceType;
	}

	/**
	 * @param string $message
	 * @param array $entities
	 * @return BetterLocationCollection | \InvalidArgumentException[]
	 * @throws \Exception
	 */
	public static function generateFromTelegramMessage(string $message, array $entities): BetterLocationCollection {
		$betterLocationsCollection = new BetterLocationCollection();

		foreach ($entities as $entity) {
			if (in_array($entity->type, ['url', 'text_link'])) {
				if ($entity->type === 'url') { // raw url
					$url = mb_substr($message, $entity->offset, $entity->length);
				} else if ($entity->type === 'text_link') { // url hidden in text
					$url = $entity->url;
				} else {
					throw new \InvalidArgumentException('Unhandled Telegram entity type');
				}

				try {
					if (GoogleMapsService::isValid($url)) {
						$googleMapsBetterLocationCollection = GoogleMapsService::parseCoordsMultiple($url);
						$googleMapsBetterLocationCollection->filterTooClose(DISTANCE_IGNORE);
						$betterLocationsCollection->mergeCollection($googleMapsBetterLocationCollection);
					} else if (MapyCzService::isValid($url)) {
						$mapyCzBetterLocationCollection = MapyCzService::parseCoordsMultiple($url);
						$mapyCzBetterLocationCollection->filterTooClose(DISTANCE_IGNORE);
						$betterLocationsCollection->mergeCollection($mapyCzBetterLocationCollection);
					} else if (OpenStreetMapService::isValid($url)) {
						$betterLocationsCollection[] = OpenStreetMapService::parseCoords($url);
					} else if (HereWeGoService::isValid($url)) {
						$betterLocationsCollection[] = HereWeGoService::parseCoords($url);
					} else if (OpenLocationCodeService::isValid($url)) {
						$betterLocationsCollection[] = OpenLocationCodeService::parseCoords($url);
					} else if (WazeService::isValid($url)) {
						$betterLocationsCollection[] = WazeService::parseCoords($url);
					} else if (WhatThreeWordService::isValid($url)) {
						$betterLocationsCollection[] = WhatThreeWordService::parseCoords($url);
					} else if (IngressIntelService::isValid($url)) {
						$betterLocationsCollection[] = IngressIntelService::parseCoords($url);
					}
				} catch (\Exception $exception) {
					$betterLocationsCollection[] = $exception;
				}
			}
		}

		$messageWithoutUrls = self::getMessageWithoutUrls($message, $entities);

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
		if (preg_match_all(WhatThreeWordService::RE_IN_STRING, $messageWithoutUrls, $matches)) {
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

		return $betterLocationsCollection;
	}

	public function export(): array {
		return [
			'lat' => $this->getLat(),
			'lon' => $this->getLon(),
			'service' => strip_tags($this->getPrefixMessage()),
		];
	}

	public function generateScreenshotLink(string $serviceClass) {
		if (class_exists($serviceClass) === false) {
			throw new \InvalidArgumentException(sprintf('Invalid location service: "%s".', $serviceClass));
		}
		if (is_subclass_of($serviceClass, \BetterLocation\Service\AbstractService::class) === false) {
			throw new \InvalidArgumentException(sprintf('Source service has to be subclass of "%s".', \BetterLocation\Service\AbstractService::class));
		}
		if (method_exists($serviceClass, 'getScreenshotLink') === false) {
			throw new \InvalidArgumentException(sprintf('Source service "%s" does not supports screenshot links.', $serviceClass));
		}
		/** @var $services \BetterLocation\Service\AbstractService[] */
		return $serviceClass::getScreenshotLink($this->getLat(), $this->getLon());
	}


	public function setAddress(string $address) {
		$this->address = $address;
	}

	public function getAddress(): ?string {
		return $this->address;
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function generateAddress() {
		if (is_null($this->address)) {
			try {
				$w3wApi = new \What3words\Geocoder\Geocoder(W3W_API_KEY);
				$result = $w3wApi->convertTo3wa($this->getLat(), $this->getLon());
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

	private static function getMessageWithoutUrls(string $text, array $entities) {
		foreach (array_reverse($entities) as $entity) {
			if ($entity->type === 'url') {
				$text = General::substrReplace($text, str_pad('|', $entity->length), $entity->offset, $entity->length);
			}
		}
		return $text;
	}

	/**
	 * @param bool $withAddress
	 * @return string
	 * @throws \Exception
	 */
	public function generateBetterLocation($withAddress = true) {
		/** @var $services \BetterLocation\Service\AbstractService[] */
		$services = [
			GoogleMapsService::class,
			MapyCzService::class,
			WazeService::class,
			HereWeGoService::class,
			OpenStreetMapService::class,
			IngressIntelService::class,
		];
		$links = [];
		foreach ($services as $service) {
			$links[] = sprintf('<a href="%s">%s</a>', $service::getLink($this->lat, $this->lon), $service::NAME);
		}
		$text = '';
		$text .= sprintf('%s <a href="%s">%s</a> <code>%f,%f</code>',
				$this->prefixMessage,
				$this->generateScreenshotLink(MapyCzService::class),
				\Icons::PICTURE,
				$this->lat,
				$this->lon
			) . PHP_EOL;
		$text .= join(' | ', $links) . PHP_EOL;
		if ($withAddress && is_null($this->address) === false) {
			$text .= $this->getAddress() . PHP_EOL;
		}
		if ($this->description) {
			$text .= $this->description . PHP_EOL;
		}
		return $text . PHP_EOL;
	}

	public function generateDriveButtons() {
		/** @var $services \BetterLocation\Service\AbstractService[] */
		$services = [
			GoogleMapsService::class,
			WazeService::class,
			HereWeGoService::class,
		];
		$buttons = [];
		foreach ($services as $service) {
			$button = new Button();
			$button->text = sprintf('%s %s', $service::NAME, \Icons::CAR);
			$button->url = $service::getLink($this->lat, $this->lon, true);
			$buttons[] = $button;
		}
		return $buttons;
	}

	public function generateAddToFavouriteButtton(): Button {
		$button = new Button();
		$button->text = \Icons::FAVOURITE;
		$button->callback_data = sprintf('%s %s %f %f', FavouritesCommand::CMD, FavouritesButton::ACTION_ADD, $this->getLat(), $this->getLon());
		return $button;
	}


	/**
	 * @param string $prefixMessage
	 */
	public function setPrefixMessage(string $prefixMessage): void {
		$this->prefixMessage = $prefixMessage;
	}

	/**
	 * @return mixed
	 */
	public function getPrefixMessage() {
		return $this->prefixMessage;
	}

	public function getLink($class, bool $drive = false) {
		if ($class instanceof \BetterLocation\Service\AbstractService === false) {
			throw new \InvalidArgumentException('Class must be instance of \BetterLocation\Service\AbstractService');
		}
		return $class::getLink($this->lat, $this->lon, $drive);
	}

	public function getLat(): float {
		return $this->lat;
	}

	public function getLon(): float {
		return $this->lon;
	}

	public function getLatLon(): array {
		return [$this->lat, $this->lon];
	}

	public function __toString() {
		return sprintf('%f, %f', $this->lat, $this->lon);
	}

	/**
	 * @param string $description
	 */
	public function setDescription(string $description): void {
		$this->description = $description;
	}

	public static function isLatValid(float $lat): bool {
		return ($lat <= 90 && $lat >= -90);
	}

	public static function isLonValid(float $lon): bool {
		return ($lon <= 180 && $lon >= -180);
	}
}
