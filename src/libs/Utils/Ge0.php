<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Decode Ge0 used in maps.me or omaps.app and  as sharing coordinates
 *
 * @author Alex Zolotarev from Minsk, Belarus <alex@maps.me> - Original author of decode functions and Algorithm (https://github.com/mapsme/ge0_url_decoder)
 * @author Tomas Palider (DJTommek) <tomas@palider.cz> - Refactored to class, added encoding, optimized,
 * @link https://github.com/mapsme/omim/tree/1892903b63f2c85b16ed4966d21fe76aba06b9ba/ge0 original source code written in C++
 */
class Ge0
{
	public static $maxPointBytes = 10;
	public static $wgs84Precision = 6;

	private const BASE64_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

	private static function maxPointBits()
	{
		return self::$maxPointBytes * 3;
	}

	/** @var string */
	public $code;
	/** @var float */
	public $lat;
	/** @var float */
	public $lon;
	/** @var float */
	public $zoom;

	/**
	 * Create via self::decode() or self::encode()
	 */
	private function __construct()
	{
	}

	public static function decode(string $code): self
	{
		$self = new self();
		$self->code = $code;
		$self->zoom = self::decodeZoom($code);
		[$self->lat, $self->lon] = self::decodeLatLon($code);
		return $self;
	}

	/**
	 * Get code from lat, lon and zoom
	 *
	 * @param float $lat Latitude between -90 and 90
	 * @param float $lon Longitude between -180 and 180
	 * @param float|int $zoom Zoom between 4 and 19.25
	 */
	public static function encode(float $lat, float $lon, float $zoom = 15): self
	{
		if (!Coordinates::isLat($lat)) {
			throw new \InvalidArgumentException(sprintf('Invalid latitude "%F".', $lat));
		}
		if (!Coordinates::isLon($lon)) {
			throw new \InvalidArgumentException(sprintf('Invalid longitude "%F".', $lon));
		}
		if ($zoom < 4 || $zoom > 19.25) {
			throw new \InvalidArgumentException(sprintf('Invalid zoom "%F": must be between 4 and 19.25.', $zoom));
		}
		$self = new self();
		$self->lat = $lat;
		$self->lon = $lon;
		$self->zoom = $zoom;
		$self->code = self::encodeZoom($zoom) . self::encodeLatLon($lat, $lon);
		return $self;
	}

	///////////////////////////////////////////////////
	//                 Decoding                      //
	///////////////////////////////////////////////////

	private static function decodeZoom(string $code): float
	{
		$base64ReverseArray = self::base64reversed();
		$zoomRaw = substr($code, 0, 1);
		$zoomCode = ord($zoomRaw);
		$zoomDecoded = $base64ReverseArray[$zoomCode];
		if ($zoomDecoded > 63) {
			throw new \InvalidArgumentException(sprintf('Invalid code "%s": zoom is not valid', $code));
		}
		return ($zoomDecoded / 4) + 4;
	}

	private static function decodeLatLon(string $code): array
	{
		$base64ReverseArray = self::base64reversed();
		$latLonStr = substr($code, 1);
		$latLonBytes = strlen($latLonStr);
		$lat = 0;
		$lon = 0;
		for ($i = 0, $shift = self::maxPointBits() - 3; $i < $latLonBytes; $i++, $shift -= 3) {
			$a = $base64ReverseArray[ord($latLonStr[$i])];
			$lat1 = ((($a >> 5) & 1) << 2 |
				(($a >> 3) & 1) << 1 |
				(($a >> 1) & 1));
			$lon1 = ((($a >> 4) & 1) << 2 |
				(($a >> 2) & 1) << 1 |
				($a & 1));
			$lat |= $lat1 << $shift;
			$lon |= $lon1 << $shift;
		}

		$middleOfSquare = 1 << (3 * (self::$maxPointBytes - $latLonBytes) - 1);
		$lat += $middleOfSquare;
		$lon += $middleOfSquare;

		$lat = round($lat / ((1 << self::maxPointBits()) - 1) * 180 - 90, self::$wgs84Precision);
		$lon = round($lon / (1 << self::maxPointBits()) * 360 - 180, self::$wgs84Precision);

		if ($lat <= -90 || $lat >= 90) {
			throw new \InvalidArgumentException(sprintf('Invalid code "%s", : latitude coordinate is out of bounds', $code));
		}
		if ($lon <= -180 || $lon >= 180) {
			throw new \InvalidArgumentException(sprintf('Invalid code "%s": longitude coordinate is out of bounds', $code));
		}
		return [$lat, $lon];
	}

	private static function base64reversed(): array
	{
		$result = [];
		for ($i = 0; $i < strlen(self::BASE64_ALPHABET); $i++) {
			$char = self::BASE64_ALPHABET[$i];
			$charCode = ord($char);
			$result[$charCode] = $i;
		}
		return $result;
	}
	///////////////////////////////////////////////////
	//                 Encoding                      //
	///////////////////////////////////////////////////

	/**
	 * Convert latitude from range(-90, 90) to range(0, $maxValue)
	 *
	 * M = maxValue, L = maxValue-1
	 * lat: -90                        90
	 *   x:  0     1     2       L     M
	 *       |--+--|--+--|--...--|--+--|
	 *       000111111222222...LLLLLMMMM
	 */
	private static function encodeLatToInt(float $lat, int $maxValue): int
	{
		$x = ((($lat + 90) / 180) * $maxValue) + 0.5;
		return (int)General::clamp($x, 0, $maxValue);
	}

	/**
	 * Convert longitude from range(-180, 180) to range(0, $maxValue)
	 */
	private static function encodeLonToInt(float $lon, int $maxValue): int
	{
		$x = ($lon + 180) / 360 * ($maxValue + 1) + 0.5;
		return (int)General::clamp($x, 0, $maxValue + 1);
	}

	private static function encodeZoom(float $zoom): string
	{
		$zoomRaw = ($zoom <= 4 ? 0 : ($zoom >= 19.75 ? 63 : (int)(($zoom - 4) * 4)));
		return self::BASE64_ALPHABET[$zoomRaw];
	}

	private static function encodeLatLon(float $lat, float $lon): string
	{
		$latI = self::encodeLatToInt($lat, (1 << self::maxPointBits()) - 1);
		$lonI = self::encodeLonToInt($lon, (1 << self::maxPointBits()) - 1);

		$result = [];
		// bits - 3 and bytes - 1 is because of skipping extra character dedicated to zoom
		for ($i = 0, $shift = self::maxPointBits() - 3; $i < self::$maxPointBytes - 1; ++$i, $shift -= 3) {
			$latBits = $latI >> $shift & 7;
			$lonBits = $lonI >> $shift & 7;

			$nextByte =
				($latBits >> 2 & 1) << 5 |
				($lonBits >> 2 & 1) << 4 |
				($latBits >> 1 & 1) << 3 |
				($lonBits >> 1 & 1) << 2 |
				($latBits & 1) << 1 |
				($lonBits & 1);

			$result[$i] = self::BASE64_ALPHABET[$nextByte];
		}
		return implode('', $result);
	}

}
