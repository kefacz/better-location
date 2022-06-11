<?php declare(strict_types=1);

namespace App\BetterLocation\Service;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\Service\Exceptions\NotSupportedException;
use App\BetterLocation\ServicesManager;
use App\Utils\Coordinates;
use App\Utils\Ingress;
use App\Utils\Strict;

final class IngressIntelService extends AbstractService
{
	const ID = 9;
	const NAME = 'Ingress';

	const TYPE_MAP = 'map';
	const TYPE_PORTAL = 'portal';

	const LINK = 'https://intel.ingress.com';

	public const TAGS = [
		ServicesManager::TAG_GENERATE_OFFLINE,
		ServicesManager::TAG_GENERATE_LINK_SHARE,
	];

	/** @throws NotSupportedException */
	public static function getLink(float $lat, float $lon, bool $drive = false, array $options = []): string
	{
		if ($drive) {
			throw new NotSupportedException('Drive link is not supported.');
		} else {
			return self::LINK . sprintf('/?ll=%1$f,%2$f&pll=%1$f,%2$f', $lat, $lon);
		}
	}

	public function isValid(): bool
	{
		$result = false;
		if ($this->url && $this->url->getDomain(2) === 'ingress.com') {
			if ($param = $this->inputUrl->getQueryParameter('pll')) { // map coordinates
				$coords = explode(',', $param);
				if (count($coords) === 2 && Coordinates::isLat($coords[0]) && Coordinates::isLon($coords[1])) {
					$this->data->portalCoord = true;
					$this->data->portalCoordLat = Strict::floatval($coords[0]);
					$this->data->portalCoordLon = Strict::floatval($coords[1]);
					$result = true;
				}
			}

			if ($param = $this->inputUrl->getQueryParameter('ll')) { // portal coordinates
				$coords = explode(',', $param);
				if (count($coords) === 2 && Coordinates::isLat($coords[0]) && Coordinates::isLon($coords[1])) {
					$this->data->mapCoord = true;
					$this->data->mapCoordLat = Strict::floatval($coords[0]);
					$this->data->mapCoordLon = Strict::floatval($coords[1]);
					$result = true;
				}
			}
		}
		return $result;
	}

	public function process(): void
	{
		if ($this->data->portalCoord ?? false) {
			$location = new BetterLocation($this->input, $this->data->portalCoordLat, $this->data->portalCoordLon, self::class, self::TYPE_PORTAL);
			Ingress::addPortalData($location);
			$this->collection->add($location);
		}
		if ($this->data->mapCoord ?? false) {
			$this->collection->add(new BetterLocation($this->input, $this->data->mapCoordLat, $this->data->mapCoordLon, self::class, self::TYPE_MAP));
		}
	}

	public static function getConstants(): array
	{
		return [
			self::TYPE_PORTAL,
			self::TYPE_MAP,
		];
	}
}
