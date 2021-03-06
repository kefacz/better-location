<?php declare(strict_types=1);

namespace App\BetterLocation\Service;

use App\BetterLocation\BetterLocation;
use App\Utils\Coordinates;
use App\Utils\Strict;
use Nette\Utils\Arrays;

final class OsmAndService extends AbstractServiceNew
{
	const NAME = 'OsmAnd';

	const LINK = 'https://osmand.net';

	public static function getLink(float $lat, float $lon, bool $drive = false): string
	{
		return self::LINK . sprintf('/go.html?lat=%1$f&lon=%2$f', $lat, $lon);
	}

	public function isValid(): bool
	{
		return (
			$this->url->getDomain(2) === 'osmand.net' &&
			Arrays::contains(['/go', '/go.html'], $this->url->getPath()) &&
			Coordinates::isLat($this->url->getQueryParameter('lat')) &&
			Coordinates::isLat($this->url->getQueryParameter('lon'))
		);
	}

	public function process(): void
	{
		$location = new BetterLocation(
			$this->inputUrl->getAbsoluteUrl(),
			Strict::floatval($this->url->getQueryParameter('lat')),
			Strict::floatval($this->url->getQueryParameter('lon')),
			self::class
		);
		$this->collection->add($location);
	}
}
