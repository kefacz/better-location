<?php declare(strict_types=1);

namespace App\BetterLocation\Service;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\BetterLocationCollection;
use App\BetterLocation\Service\Exceptions\InvalidLocationException;
use App\BetterLocation\Service\Exceptions\NotImplementedException;
use App\MiniCurl\MiniCurl;
use App\Utils\Coordinates;
use App\Utils\General;
use App\Utils\Strict;
use DJTommek\MapyCzApi\MapyCzApi;
use DJTommek\MapyCzApi\MapyCzApiException;
use Nette\Http\UrlImmutable;

final class MapyCzServiceNew extends AbstractServiceNew
{
	const NAME = 'Mapy.cz';
	const LINK = 'https://mapy.cz/zakladni?y=%1$f&x=%2$f&source=coor&id=%2$f%%2C%1$f';

	const TYPE_UNKNOWN = 'unknown';
	const TYPE_MAP = 'Map center';
	const TYPE_PLACE_ID = 'Place';
	const TYPE_PLACE_COORDS = 'Place coords';
	const TYPE_PANORAMA = 'Panorama';

	public function beforeStart(): void {
		$this->data->placeIdCoord = false;
	}

	public function isValid(): bool
	{
		return (
			$this->inputUrl !== null &&
			$this->inputUrl->getDomain(2) === 'mapy.cz' &&
			(
				$this->isShortUrl() ||
				$this->isNormalUrl()
			)
		);
	}

	public function parse(string $input): BetterLocation
	{
		// TODO: Implement parse() method.
	}

	private function isShortUrl()
	{
		// Mapy.cz short link:
		// https://mapy.cz/s/porumejene
		// https://en.mapy.cz/s/porumejene
		// https://en.mapy.cz/s/3ql7u
		// https://en.mapy.cz/s/faretabotu
		return $this->data->isShortUrl = (preg_match('/^\/s\/[a-zA-Z0-9]+$/', $this->inputUrl->getPath()));
	}

	public function isNormalUrl(): bool
	{
		// https://en.mapy.cz/zakladni?x=14.2991869&y=49.0999235&z=16&pano=1&source=firm&id=350556
		// https://mapy.cz/?x=15.278244&y=49.691235&z=15&ma_x=15.278244&ma_y=49.691235&ma_t=Jsem+tady%2C+otev%C5%99i+odkaz&source=coor&id=15.278244%2C49.691235
		// Mapy.cz panorama:
		// https://en.mapy.cz/zakladni?x=14.3139613&y=49.1487367&z=15&pano=1&pid=30158941&yaw=1.813&fov=1.257&pitch=-0.026
//		$parsedUrl = parse_url(urldecode($url)); // @TODO why it is used urldecode?

		if (
			Coordinates::isLat($this->inputUrl->getQueryParameter('x')) && Coordinates::isLon($this->inputUrl->getQueryParameter('y')) || // map position
			Strict::isPositiveInt($this->inputUrl->getQueryParameter( 'id')) && $this->inputUrl->getQueryParameter('source') || // place ID
			Strict::isPositiveInt($this->inputUrl->getQueryParameter('pid')) || // panorama ID
			Coordinates::isLat($this->inputUrl->getQueryParameter('ma_x')) && Coordinates::isLon($this->inputUrl->getQueryParameter('ma_y')) // not sure what is this...
		) {
			return true;
		} else if ($this->inputUrl->getQueryParameter('source') === 'coor' && $this->inputUrl->getQueryParameter( 'id')) { // coordinates in place ID
			$coords = explode(',', $this->inputUrl->getQueryParameter( 'id'));
			if (count($coords) === 2 && Coordinates::isLat($coords[1]) && Coordinates::isLon($coords[0])) {
				$this->data->placeIdCoord = true;
				$this->data->placeIdCoordLat = Strict::floatval($coords[1]);
				$this->data->placeIdCoordLon = Strict::floatval($coords[0]);
				return true;
			}
		}
		return false;
	}

	public static function getConstants(): array
	{
		return [
			self::TYPE_PANORAMA,
			self::TYPE_PLACE_ID,
			self::TYPE_PLACE_COORDS,
			self::TYPE_MAP,
			self::TYPE_UNKNOWN,
		];
	}

	public static function getLink(float $lat, float $lon, bool $drive = false): string
	{
		if ($drive) {
			// No official API for backend so it might be probably generated only via simulating frontend
			// @see https://napoveda.seznam.cz/forum/threads/120687/1
			// @see https://napoveda.seznam.cz/forum/file/13641/Schema-otevirani-aplikaci-z-url-a-externe.pdf
			throw new NotImplementedException('Drive link is not implemented.');
		} else {
			return sprintf(self::LINK, $lat, $lon);
		}
	}

	public static function getScreenshotLink(float $lat, float $lon): string
	{
		// URL Parameters to screenshoter (Mapy.cz website is using it with p=3 and l=0):
		// l=0 hide right panel (can be opened via arrow icon)
		// p=1 disable right panel (can't be opened) and disable bottom left panorama view screenshot
		// p=2 show right panel and (can't be hidden) and disable bottom left panorama view screenshot
		// p=3 disable right panel (can't be opened) and enable bottom left panorama view screenshot
		return 'https://en.mapy.cz/screenshoter?url=' . urlencode(self::getLink($lat, $lon) . '&p=3&l=0');
	}

	public function process()
	{
		if ($this->data->isShortUrl) {
			$this->url = new UrlImmutable(MiniCurl::loadRedirectUrl($this->data->input));
		}
		$mapyCzApi = new MapyCzApi();

		// URL with Panorama ID
		if (Strict::isPositiveInt($this->url->getQueryParameter('pid'))) {
			try {
				$mapyCzResponse = $mapyCzApi->loadPanoramaDetails(Strict::intval($this->url->getQueryParameter('pid')));
			} catch (MapyCzApiException $exception) {
				throw new InvalidLocationException(sprintf('MapyCz API response: "%s"', htmlentities($exception->getMessage())));
			}
			$betterLocation = new BetterLocation($this->input, $mapyCzResponse->getLat(), $mapyCzResponse->getLon(), self::class, self::TYPE_PANORAMA);
			$this->collection[self::TYPE_PANORAMA] = $betterLocation;
		}

		// URL with Place ID
		if ($this->url->getQueryParameter('source') && Strict::isPositiveInt($this->url->getQueryParameter('id'))) {
			try {
				$mapyCzResponse = $mapyCzApi->loadPoiDetails($this->url->getQueryParameter('source'), Strict::intval($this->url->getQueryParameter('id')));
			} catch (MapyCzApiException $exception) {
				throw new InvalidLocationException(sprintf('MapyCz API response: "%s"', htmlentities($exception->getMessage())));
			}
			$betterLocation = new BetterLocation($this->input, $mapyCzResponse->getLat(), $mapyCzResponse->getLon(), self::class, self::TYPE_PLACE_ID);
			$betterLocation->setPrefixMessage(sprintf('<a href="%s">%s %s</a>', $this->url, self::NAME, $mapyCzResponse->title));
			$betterLocation->setAddress($mapyCzResponse->titleVars->locationMain1);
			$this->collection[self::TYPE_PLACE_ID] = $betterLocation;
		}

		// MapyCZ URL has ID in format of coordinates
		if ($this->data->placeIdCoord === true) {
			$betterLocation = new BetterLocation($this->input, $this->data->placeIdCoordLat, $this->data->placeIdCoordLon, self::class, self::TYPE_PLACE_COORDS);
			$this->collection[] = $betterLocation;
		}

		if (Strict::isFloat($this->url->getQueryParameter('ma_x')) && Strict::isFloat($this->url->getQueryParameter('ma_y'))) {
			$betterLocation = new BetterLocation($this->input, Strict::floatval($this->url->getQueryParameter('ma_y')), Strict::floatval($this->url->getQueryParameter('ma_x')), self::class, self::TYPE_UNKNOWN);
			$this->collection[] = $betterLocation;
		}

		if (Strict::isFloat($this->url->getQueryParameter('x')) && Strict::isFloat($this->url->getQueryParameter('y'))) {
			$betterLocation = new BetterLocation($this->input, Strict::floatval($this->url->getQueryParameter('y')), Strict::floatval($this->url->getQueryParameter('x')), self::class, self::TYPE_MAP);
			$this->collection[] = $betterLocation;
		}
	}
}
