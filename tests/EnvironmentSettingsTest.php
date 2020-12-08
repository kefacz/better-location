<?php declare(strict_types=1);

use App\BetterLocation\Service\Coordinates\WGS84DegreesService;
use PHPUnit\Framework\TestCase;


final class EnvironmentSettingsTest extends TestCase
{
	/**
	 * Keep same floating point character even if locale is different
	 *
	 * @noinspection PhpUnhandledExceptionInspection
	 */
	public function testLocale(): void
	{
		$localeOriginal = setlocale(LC_NUMERIC, 0); // do not change anything, just save original location to restore it later
		$betterLocationPositive = WGS84DegreesService::parseCoords('50.123456,10.123456');
		$betterLocationPositiveNegative = WGS84DegreesService::parseCoords('50.123456,-10.123456');
		$betterLocationNegativePositive = WGS84DegreesService::parseCoords('-50.123456,10.123456');
		$betterLocationNegative = WGS84DegreesService::parseCoords('-50.123456,-10.123456');

		// default formatting (usually from environment settings)
		$this->assertSame('50.123456,10.123456', $betterLocationPositive->__toString());
		$this->assertSame('50.123456,-10.123456', $betterLocationPositiveNegative->__toString());
		$this->assertSame('-50.123456,10.123456', $betterLocationNegativePositive->__toString());
		$this->assertSame('-50.123456,-10.123456', $betterLocationNegative->__toString());

		setlocale(LC_NUMERIC, 'swedish'); // swedish formatting is using "," instead of "." in floating point
		$this->assertSame('50.123456,10.123456', $betterLocationPositive->__toString());
		$this->assertSame('50.123456,-10.123456', $betterLocationPositiveNegative->__toString());
		$this->assertSame('-50.123456,10.123456', $betterLocationNegativePositive->__toString());
		$this->assertSame('-50.123456,-10.123456', $betterLocationNegative->__toString());

		setlocale(LC_NUMERIC, 'american'); // american formatting is using "." instead of "," in floating point
		$this->assertSame('50.123456,10.123456', $betterLocationPositive->__toString());
		$this->assertSame('50.123456,-10.123456', $betterLocationPositiveNegative->__toString());
		$this->assertSame('-50.123456,10.123456', $betterLocationNegativePositive->__toString());
		$this->assertSame('-50.123456,-10.123456', $betterLocationNegative->__toString());

		setlocale(LC_NUMERIC, $localeOriginal); // restore original settings (again default formatting)
		$this->assertSame('50.123456,10.123456', $betterLocationPositive->__toString()); //
		$this->assertSame('50.123456,-10.123456', $betterLocationPositiveNegative->__toString());
		$this->assertSame('-50.123456,10.123456', $betterLocationNegativePositive->__toString());
		$this->assertSame('-50.123456,-10.123456', $betterLocationNegative->__toString());
	}
}
