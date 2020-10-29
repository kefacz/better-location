<?php declare(strict_types=1);

namespace App\BetterLocation\Service;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\BetterLocationCollection;
use App\BetterLocation\Service\Exceptions\InvalidLocationException;
use App\BetterLocation\Service\Exceptions\NotImplementedException;
use App\BetterLocation\Service\Exceptions\NotSupportedException;

final class IngressIntelService extends AbstractService
{
	const NAME = 'Ingress';

	const LINK = 'https://intel.ingress.com';

	/** @throws NotSupportedException */
	public static function getLink(float $lat, float $lon, bool $drive = false): string
	{
		if ($drive) {
			throw new NotSupportedException('Drive link is not implemented.');
		} else {
			return self::LINK . sprintf('/?ll=%1$f,%2$f&pll=%1$f,%2$f', $lat, $lon);
		}
	}

	public static function isValid(string $url): bool
	{
		return self::isUrl($url);
	}

	/** @throws InvalidLocationException */
	public static function parseCoords(string $url): BetterLocation
	{
		$coords = self::parseUrl($url);
		if ($coords) {
			return new BetterLocation($url, $coords[0], $coords[1], self::class);
		} else {
			throw new InvalidLocationException(sprintf('Unable to get coords from Ingress Intel link "%s".', $url));
		}
	}

	public static function isUrl(string $url): bool
	{
		return substr($url, 0, mb_strlen(self::LINK)) === self::LINK;
	}

	public static function parseUrl(string $url): ?array
	{
		$paramsString = explode('?', $url);
		if (count($paramsString) === 2) {
			parse_str($paramsString[1], $params);
			if (isset($params['pll'])) {
				$coords = explode(',', $params['pll']);
			} else if (isset($params['ll'])) {
				$coords = explode(',', $params['ll']);
			} else {
				return null;
			}
			return [
				floatval($coords[0]),
				floatval($coords[1]),
			];
		} else {
			return null;
		}
	}

	/**
	 * @param string $input
	 * @return BetterLocationCollection
	 * @throws NotImplementedException
	 */
	public static function parseCoordsMultiple(string $input): BetterLocationCollection
	{
		throw new NotImplementedException('Parsing multiple coordinates is not available.');
	}
}
