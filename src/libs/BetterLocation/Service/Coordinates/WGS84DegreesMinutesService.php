<?php declare(strict_types=1);

namespace App\BetterLocation\Service\Coordinates;

use App\BetterLocation\Service\CoordinatesRender\WGS84DegreeCompactService;
use App\Utils\Coordinates;

final class WGS84DegreesMinutesService extends AbstractService
{
	const ID = 11;
	const NAME = 'WGS84 DM';
	const RE_COORD = '([0-9]{1,3})[° ]{1,3}([0-9]{1,3}\.[0-9]{1,20}) ?\'?';

	public function process(): void
	{
		$location = self::processWGS84();
		$this->collection->add($location);
	}

	public static function getShareText(float $lat, float $lon): string
	{
		$coords = new Coordinates($lat, $lon);
		list($degreesLat, $minutesLat) = Coordinates::wgs84DegreesToDegreesMinutes($lat);
		list($degreesLon, $minutesLon) = Coordinates::wgs84DegreesToDegreesMinutes($lon);
		return sprintf('%s %d° %.5F\', %s %d° %.5F\'',
			$coords->getLatHemisphere(), abs($degreesLat), $minutesLat,
			$coords->getLonHemisphere(), abs($degreesLon), $minutesLon
		);
	}
}
