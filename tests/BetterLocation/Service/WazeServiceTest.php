<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\BetterLocation\Service\WazeService;

final class WazeServiceTest extends TestCase
{
	public function testGenerateShareLink(): void
	{
		$this->assertSame('https://www.waze.com/ul?ll=50.087451,14.420671', WazeService::getLink(50.087451, 14.420671));
		$this->assertSame('https://www.waze.com/ul?ll=50.100000,14.500000', WazeService::getLink(50.1, 14.5));
		$this->assertSame('https://www.waze.com/ul?ll=-50.200000,14.600000', WazeService::getLink(-50.2, 14.6000001)); // round down
		$this->assertSame('https://www.waze.com/ul?ll=50.300000,-14.700001', WazeService::getLink(50.3, -14.7000009)); // round up
		$this->assertSame('https://www.waze.com/ul?ll=-50.400000,-14.800008', WazeService::getLink(-50.4, -14.800008));
	}

	public function testGenerateDriveLink(): void
	{
		$this->assertSame('https://www.waze.com/ul?ll=50.087451,14.420671&navigate=yes', WazeService::getLink(50.087451, 14.420671, true));
		$this->assertSame('https://www.waze.com/ul?ll=50.100000,14.500000&navigate=yes', WazeService::getLink(50.1, 14.5, true));
		$this->assertSame('https://www.waze.com/ul?ll=-50.200000,14.600000&navigate=yes', WazeService::getLink(-50.2, 14.6000001, true)); // round down
		$this->assertSame('https://www.waze.com/ul?ll=50.300000,-14.700001&navigate=yes', WazeService::getLink(50.3, -14.7000009, true)); // round up
		$this->assertSame('https://www.waze.com/ul?ll=-50.400000,-14.800008&navigate=yes', WazeService::getLink(-50.4, -14.800008, true));
	}

	/** @noinspection PhpUnhandledExceptionInspection */
	public function testShortUrl(): void
	{
		$this->assertSame('50.052273,14.452407', WazeService::parseCoords('https://waze.com/ul/hu2fk8zezt')->__toString());
	}

	/** @noinspection PhpUnhandledExceptionInspection */
	public function testNormalUrl(): void
	{
		$this->assertSame('50.052098,14.451968', WazeService::parseCoords('https://waze.com/ul?ll=50.052098,14.451968')->__toString()); // https link from @ingressportalbot
		$this->assertSame('50.063007,14.439640', WazeService::parseCoords('https://www.waze.com/ul?ll=50.06300713%2C14.43964005&navigate=yes&zoom=15')->__toString());
		$this->assertSame('49.877080,18.430363', WazeService::parseCoords('https://www.waze.com/ul?ll=49.87707960%2C18.43036300&navigate=yes')->__toString());
		$this->assertSame('50.063007,14.439640', WazeService::parseCoords('https://www.waze.com/ul?ll=50.06300713%2C14.43964005')->__toString());
		$this->assertSame('50.063007,14.439640', WazeService::parseCoords('https://www.waze.com/cs/livemap/directions?latlng=50.063007132127616%2C14.439640045166016&utm_campaign=waze_website&utm_expid=.K6QI8s_pTz6FfRdYRPpI3A.0&utm_referrer=https%3A%2F%2Fwww.waze.com%2Fcs%2Faccount&utm_source=waze_website')->__toString());
		$this->assertSame('50.063007,14.439640', WazeService::parseCoords('https://www.waze.com/cs/livemap/directions?latlng=50.063007132127616%2C14.439640045166016')->__toString());
		$this->assertSame('50.077344,14.434758', WazeService::parseCoords('https://www.waze.com/cs/livemap/directions?utm_expid=.K6QI8s_pTz6FfRdYRPpI3A.0&utm_referrer=&to=ll.50.07734439%2C14.43475842')->__toString());
		$this->assertSame('50.077344,14.434758', WazeService::parseCoords('https://www.waze.com/cs/livemap/directions?to=ll.50.07734439%2C14.43475842')->__toString());
		$this->assertSame('49.877080,18.430363', WazeService::parseCoords('https://www.waze.com/cs/livemap/directions?to=ll.49.8770796%2C18.430363')->__toString());
	}
}
