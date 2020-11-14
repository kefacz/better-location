<?php declare(strict_types=1);

namespace App\BetterLocation;

use App\BetterLocation\Service\Coordinates\MGRSService;
use App\BetterLocation\Service\Coordinates\USNGService;
use App\BetterLocation\Service\Coordinates\WG84DegreesMinutesSecondsService;
use App\BetterLocation\Service\Coordinates\WG84DegreesMinutesService;
use App\BetterLocation\Service\Coordinates\WG84DegreesService;
use App\BetterLocation\Service\DrobnePamatkyCzService;
use App\BetterLocation\Service\DuckDuckGoService;
use App\BetterLocation\Service\Exceptions\InvalidLocationException;
use App\BetterLocation\Service\GeocachingService;
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
use App\Icons;
use App\TelegramCustomWrapper\TelegramHelper;
use App\Utils\Coordinates;
use App\Utils\General;
use App\Utils\StringUtils;
use Tracy\Debugger;
use unreal4u\TelegramAPI\Telegram\Types\MessageEntity;

class BetterLocationCollection implements \ArrayAccess, \Iterator, \Countable
{
	/** @var BetterLocation[] */
	private $locations = [];
	/** @var \Exception[] */
	private $errors = [];
	private $position = 0;

	public function __invoke()
	{
		return $this->locations;
	}

	/** @param BetterLocation|\Throwable $betterLocation */
	public function add($betterLocation): self
	{
		if ($betterLocation instanceof BetterLocation) {
			$this->locations[] = $betterLocation;
		} else if ($betterLocation instanceof \Throwable) {
			$this->errors[] = $betterLocation;
		} else {
			throw new \InvalidArgumentException(sprintf('%s is accepting only "%s" and "%s" objects.', self::class, BetterLocation::class, \Throwable::class));
		}
		return $this;
	}

	public function getAll()
	{
		return array_merge($this->locations, $this->errors);
	}

	/**
	 * @return BetterLocation[]
	 */
	public function getLocations(): array
	{
		return $this->locations;
	}

	public function getErrors()
	{
		return $this->errors;
	}

	public function getFirst()
	{
		return reset($this->locations);
	}

	public function mergeCollection(BetterLocationCollection $betterLocationCollection): void
	{
		foreach ($betterLocationCollection->getAll() as $betterLocation) {
			$this->add($betterLocation);
		}
	}

	public function filterTooClose(int $ignoreDistance = 0): void
	{
		$mostImportantLocation = $this->getFirst();
		foreach ($this->locations as $key => $location) {
			if ($mostImportantLocation === $location) {
				continue;
			} else {
				// @TODO possible optimalization to skip calculating distance: if 0, check if coordinates are same
				$distance = Coordinates::distance(
					$mostImportantLocation->getLat(),
					$mostImportantLocation->getLon(),
					$location->getLat(),
					$location->getLon(),
				);
				if ($distance < $ignoreDistance) {
					// Remove locations that are too close to main location
					unset($this->locations[$key]);
				} else {
					$location->setDescription(sprintf('%s Location is %d meters away from %s %s.', Icons::WARNING, $distance, $mostImportantLocation->getName(), Icons::ARROW_UP));
				}
			}
		}
	}

	/**
	 * Remove locations with exact same coordinates and keep only one
	 */
	public function deduplicate(): void
	{
		$originalCoordinates = [];
		foreach ($this->locations as $location) {
			$key = $location->__toString();
			if (isset($originalCoordinates[$key])) {
				$originalCoordinates[$key]++;
			} else {
				$originalCoordinates[$key] = 1;
			}
		}

		$coordinates = $originalCoordinates; // copy array
		// array_reverse to remove all other duplicated locations but first
		foreach (array_reverse($this->locations, true) as $collectionKey => $location) {
			$key = $location->__toString();
			if ($coordinates[$key] > 1) {
				unset($this->locations[$collectionKey]);
				$coordinates[$key]--;
			} else if ($coordinates[$key] === 1 && $originalCoordinates[$key] > 1) { // add info that coordinates was deduplicated
				$this->locations[$collectionKey]->setCoordinateSuffixMessage(sprintf('(%dx)', $originalCoordinates[$key]));
			}
		}
	}

	public function offsetExists($offset)
	{
		return isset($this->locations[$offset]);
	}

	public function offsetGet($offset)
	{
		return isset($this->locations[$offset]) ? $this->locations[$offset] : null;
	}

	public function offsetSet($offset, $value)
	{
		if ($value instanceof BetterLocation) {
			if (is_null($offset)) {
				$this->locations[] = $value;
			} else {
				$this->locations[$offset] = $value;
			}
		} else if ($value instanceof \Throwable) {
			if (is_null($offset)) {
				$this->errors[] = $value;
			} else {
				$this->errors[$offset] = $value;
			}
		} else {
			throw new \InvalidArgumentException('Accepting only BetterLocation or Exception objects.');
		}
	}

	public function offsetUnset($offset)
	{
		unset($this->locations[$offset]);
	}

	public function current()
	{
		return $this->locations[$this->position];
	}

	public function next()
	{
		++$this->position;
	}

	public function key()
	{
		return $this->position;
	}

	public function valid()
	{
		return isset($this->locations[$this->position]);
	}

	public function rewind()
	{
		$this->position = 0;
	}

	public function count()
	{
		return count($this->locations) + count($this->errors);
	}

	/** @param MessageEntity[] $entities */
	public static function fromTelegramMessage(string $message, array $entities): self
	{
		$betterLocationsCollection = new self();

		foreach ($entities as $entity) {
			if (in_array($entity->type, ['url', 'text_link'])) {
				$url = TelegramHelper::getEntityContent($message, $entity);

				if (Url::isTrueUrl($url) === false) {
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
					} else if (GeocachingService::isUrl($url)) {
						$betterLocationsCollection[] = GeocachingService::parseUrl($url);
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
						if ($headers && isset($headers['content-type']) && General::checkIfValueInHeaderMatchArray($headers['content-type'], Url::CONTENT_TYPE_IMAGE_EXIF)) {
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

		$messageWithoutUrls = TelegramHelper::getMessageWithoutUrls($message, $entities);
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

}
