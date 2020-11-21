<?php declare(strict_types=1);

use App\BetterLocation\Service\Exceptions\NotSupportedException;
use PHPUnit\Framework\TestCase;
use App\BetterLocation\Service\Coordinates\WG84DegreesMinutesService;
use App\BetterLocation\Service\Exceptions\InvalidLocationException;

require_once __DIR__ . '/../../../../src/bootstrap.php';

final class WG84DegreesMinutesServiceTest extends TestCase
{
	public function testGenerateShareLink(): void
	{
		$this->expectException(NotSupportedException::class);
		$this->expectExceptionMessage('Share link for raw coordinates is not supported.');
		WG84DegreesMinutesService::getLink(50.087451, 14.420671);
	}

	public function testGenerateDriveLink(): void
	{
		$this->expectException(NotSupportedException::class);
		$this->expectExceptionMessage('Drive link for raw coordinates is not supported.');
		WG84DegreesMinutesService::getLink(50.087451, 14.420671, true);
	}

	public function testNothingInText(): void
	{
		$this->assertEquals([], WG84DegreesMinutesService::findInText('Nothing valid')->getAll());
	}

	public function testCoordinatesFromGeocaching(): void
	{
		$this->assertEquals('50.079733,14.477500', WG84DegreesMinutesService::parseCoords('N 50° 04.784 E 014° 28.650')->__toString()); // https://www.geocaching.com/geocache/GC19HCD_kafkuv-hrob-kafkas-grave
		$this->assertEquals('49.871733,18.423450', WG84DegreesMinutesService::parseCoords('N 49° 52.304 E 018° 25.407')->__toString()); // https://www.geocaching.com/geocache/GCY3MG_orlova-jinak-orlovacity-otherwise
		$this->assertEquals('-51.692183,-57.856267', WG84DegreesMinutesService::parseCoords('S 51° 41.531 W 057° 51.376')->__toString()); // https://www.geocaching.com/geocache/GC5HVVP_public-jetty
		$this->assertEquals('-45.873917,170.511983', WG84DegreesMinutesService::parseCoords('S 45° 52.435 E 170° 30.719')->__toString()); // https://www.geocaching.com/geocache/GC8MFZX_otd-9-january-otago
		$this->assertEquals('41.882600,-87.623000', WG84DegreesMinutesService::parseCoords('N 41° 52.956 W 087° 37.380')->__toString()); // https://www.geocaching.com/geocache/GCJZDR_cloud-gate-aka-the-bean
	}

	public function testCoordinates(): void
	{
		$text = '';
		$text .= 'N50°59.72333\', E10°31.36987\'' . PHP_EOL;    // +/+
		$text .= 'N 51°4.34702\', E11°46.32372\'' . PHP_EOL;    // +/+
		$text .= 'S52°18.11425\', E 120°46.79265\'' . PHP_EOL;  // -/+
		$text .= 'S 53°37.66440\', W 13°13.32803\'' . PHP_EOL;  // -/-
		$text .= PHP_EOL;
		$text .= '54°59.72333\'N, 14°31.36987\'E' . PHP_EOL;    // +/+
		$text .= '55°4.34702\'N, 15°46.32372\'E' . PHP_EOL;     // +/+
		$text .= PHP_EOL;
		$text .= 'Invalid:';
		$text .= 'N56°18.11425\'S, 160°46.79265\' E' . PHP_EOL;     // Both coordinates are north-south hemisphere
		$text .= 'S56°18.11425\'S, 160°46.79265\' E' . PHP_EOL;     // Both coordinates are north-south hemisphere
		$text .= 'N56°18.11425\'S, E160°46.79265\' E' . PHP_EOL;    // Both coordinates are east-west hemisphere
		$text .= '57°37.66440\'S, E17°13.32803\'W' . PHP_EOL;       // Both coordinates are east-west hemisphere

		$betterLocations = WG84DegreesMinutesService::findInText($text);
		$this->assertEquals([50.99538883333334, 10.522831166666666], $betterLocations[0]->getLatLon());
		$this->assertEquals([51.072450333333336, 11.772062], $betterLocations[1]->getLatLon());
		$this->assertEquals([-52.30190416666667, 120.7798775], $betterLocations[2]->getLatLon());
		$this->assertEquals([-53.62774, -13.222133833333332], $betterLocations[3]->getLatLon());
		$this->assertEquals([54.99538883333334, 14.522831166666666], $betterLocations[4]->getLatLon());
		$this->assertEquals([55.072450333333336, 15.772062], $betterLocations[5]->getLatLon());

		$errors = $betterLocations->getErrors();
		$this->assertInstanceOf(InvalidLocationException::class, $errors[0]);
		$this->assertInstanceOf(InvalidLocationException::class, $errors[1]);
		$this->assertInstanceOf(InvalidLocationException::class, $errors[2]);
		$this->assertInstanceOf(InvalidLocationException::class, $errors[3]);
	}

	/**
	 * Coordinates copied from random Geocahing.com geocaches listings randomly around the world. Most of them are Mystery and Multi.
	 * @note Lot's of coordinates were defined as "49 10.ABC". These were replaced with random
	 * numbers, like this "49 10.123". So any of these are solutions at all.
	 */
	public function testCoordinatesFromGeocachingListing(): void
	{
		$this->assertEquals('50.135383,14.890933', WG84DegreesMinutesService::findInText('Finálku najdete na
N50°08.123
E014°53.456')[0]->__toString());
		$this->assertEquals('50.205750,15.131650', WG84DegreesMinutesService::findInText('Vzoreček k výpočtu finálky:
N50° 12.345
E014° 67.899')[0]->__toString());
		$this->assertEquals('50.139617,14.895833', WG84DegreesMinutesService::findInText('N 50° 08.377 E 014° 53.750')[0]->__toString());
		$this->assertEquals('50.111100,14.444433', WG84DegreesMinutesService::findInText('N 50°06.666 E 014°26.666')[0]->__toString());
		$this->assertEquals('50.072383,14.402017', WG84DegreesMinutesService::findInText('N 50° 04.343 E 014° 24.121')[0]->__toString());
		$this->assertEquals('50.077767,14.408767', WG84DegreesMinutesService::findInText('N 50° 4.666
E 014° 24.526')[0]->__toString());
		$this->assertEquals('50.068717,14.352050', WG84DegreesMinutesService::findInText('N50°4.123 E14°21.123')[0]->__toString());
		$this->assertEquals('50.075667,14.402967', WG84DegreesMinutesService::findInText('N 50 04.540 E 014 24.178')[0]->__toString());
		$this->assertEquals('50.080617,14.401700', WG84DegreesMinutesService::findInText('Vratte se zpet na vnitrní stranu zdi, nahodte tradicku Hladová zed (N 50° 04.837 E 014° 24.102) a jdete za ní tak, abyste byli uvnitr hradeb (hradby s ochozem = uvnitr).')[0]->__toString());
		$this->assertEquals('50.090900,14.389033', WG84DegreesMinutesService::findInText('N 50° 05.454 E 014° 23.342')[0]->__toString());
		$this->assertCount(0, WG84DegreesMinutesService::findInText('S 41 17. 1 2 34
E 174 44.1 23 45'));
		$this->assertCount(0, WG84DegreesMinutesService::findInText('S 41 17.4 9 87
E 174 44. 65 43 5'));
		$this->assertCount(0, WG84DegreesMinutesService::findInText('The cache can be found at S 41 11.123, East 174 45.657.'));
		$this->assertEquals('-41.185183,174.185183', WG84DegreesMinutesService::findInText('S 41 11.111            E174 11.111')[0]->__toString());
		$this->assertEquals('-41.185183,174.685383', WG84DegreesMinutesService::findInText('Final: S 41° 11.111 174° 41.123')[0]->__toString());
		$this->assertEquals('-41.261300,174.666667', WG84DegreesMinutesService::findInText('The cache is not hidden at the published coordinates. It can be found at S 41° 15.678 E 174° 40.000, where:')[0]->__toString());
		$this->assertEquals('-41.016867,174.016872', WG84DegreesMinutesService::findInText('The cache can be found at S41 01.012 E174 01.0123.')[0]->__toString());
		$this->assertEquals('30.180383,-93.375650', WG84DegreesMinutesService::findInText('A N30 10.823 W93 22.539')[0]->__toString());
		$collection = WG84DegreesMinutesService::findInText('The cache can be found at S41 AB.CDE E174 FG.HJK.

A N30 10.823 W93 22.539

B N26 26.760 E80 25.169

C N6 29.238 E99 18.412

D N30 10.891, W93 22.527

E N13 45.029 E100 38.010

F N36 7.450 W86 43.494

G N16 40.853 E121 32.293

H N46 52.724 W114 1.162

J N22 36.385 E71 47.748

K N52 30.199 E13 26.906
');
		$this->assertEquals('30.180383,-93.375650', $collection[0]->__toString()); // A
		$this->assertEquals('26.446000,80.419483', $collection[1]->__toString()); // B
		$this->assertEquals('6.487300,99.306867', $collection[2]->__toString()); // C
		$this->assertEquals('30.181517,-93.375450', $collection[3]->__toString()); // D
		$this->assertEquals('13.750483,100.633500', $collection[4]->__toString()); // E
		$this->assertEquals('36.124167,-86.724900', $collection[5]->__toString()); // F
		$this->assertEquals('16.680883,121.538217', $collection[6]->__toString()); // G
		$this->assertEquals('46.878733,-114.019367', $collection[7]->__toString()); // H
		$this->assertEquals('22.606417,71.795800', $collection[8]->__toString()); // J
		$this->assertEquals('52.503317,13.448433', $collection[9]->__toString()); // K

		$this->assertEquals('-41.259250,174.759250', WG84DegreesMinutesService::findInText('The secret waypoint is at S41°15.555 E174°45.555, where')[0]->__toString());
		$this->assertEquals('-12.576117,123.761300', WG84DegreesMinutesService::findInText('S: 12 34.567 E: 123 45.678')[0]->__toString());
		$this->assertEquals('41.202050,174.202050', WG84DegreesMinutesService::findInText('Cache can be found at 41 12.123 174 12.123')[0]->__toString());
		$this->assertEquals('-41.202050,174.202050', WG84DegreesMinutesService::findInText('Cache can be found at S41 12.123 E174 12.123')[0]->__toString());
		$this->assertCount(0, WG84DegreesMinutesService::findInText('Where final is at South 41 degrees 12.456;  East 174 degrees 49.999'));
		$this->assertEquals('-41.277767,174.777767', WG84DegreesMinutesService::findInText('The final is at S41° 16.666 E174° 46.666.')[0]->__toString());
		$this->assertEquals('-54.618667,-68.137667', WG84DegreesMinutesService::findInText('Esta caché se puede encontrar en las coordenadas: S 54° 37.120 W 068° 08.260')[0]->__toString());
		$this->assertEquals('-34.602000,-58.383000', WG84DegreesMinutesService::findInText('Logbook: S 34° 36.12 W 058° 22.98')[0]->__toString());
		$this->assertEquals('34.631467,58.529617', WG84DegreesMinutesService::findInText('N 34° 37.888\' E 58° 31.777\'')[0]->__toString());
		$this->assertEquals('34.631467,-58.529617', WG84DegreesMinutesService::findInText('N 34° 37.888\' W 58° 31.777\'')[0]->__toString());
		$this->assertEquals('-34.631467,58.529617', WG84DegreesMinutesService::findInText('S 34° 37.888\' E 58° 31.777\'')[0]->__toString());
		$this->assertEquals('-34.631467,-58.529617', WG84DegreesMinutesService::findInText('S 34° 37.888\' W 58° 31.777\'')[0]->__toString());
		$this->assertEquals('-26.824067,-65.212950', WG84DegreesMinutesService::findInText('Las coordenadas finales de la caché son:
S26°49.444\' ; W065°12.777\'
Para encontrar a los')[0]->__toString());
		$this->assertEquals('-26.833117,-65.227567', WG84DegreesMinutesService::findInText('monumento al bicentenario:

S26 49.987 W065 13.654

Puede usar el GeoChecker para verificar las coordenadas.')[0]->__toString());
		$this->assertEquals('23.597267,133.335100', WG84DegreesMinutesService::findInText('N23° 35.836\' E133° 20.106\'')[0]->__toString());
		$this->assertEquals('-23.588883,-46.672217', WG84DegreesMinutesService::findInText('S23° 35.333\' W46° 40.333\'')[0]->__toString());
		$this->assertEquals('-23.561100,-46.657400', WG84DegreesMinutesService::findInText('Coordenada final do cache:  S 23° 33.666\' W046° 39.444\'')[0]->__toString());

		$this->assertEquals('50.118717,14.924267', WG84DegreesMinutesService::findInText('Souradnice:

N 50°07.123
E 14°55.456')[0]->__toString());
		$this->assertEquals('50.118717,14.924267', WG84DegreesMinutesService::findInText('N 50 07.123
E 14 55.456')[0]->__toString());
		$this->assertCount(0, WG84DegreesMinutesService::findInText('Finally, multiply AB by C. Also, add D and EF. The cache can be found at 47 37.762+(AB*C) and 122 17.870-(D+EF)'));
		$this->assertCount(0, WG84DegreesMinutesService::findInText('Finally, multiply AB by C. Also, add D and EF. The cache can be found at 47 37.999 and 122 17.888'));
		// these are only decimal coordinates, not decimal-degrees
		$this->assertCount(0, WG84DegreesMinutesService::findInText('Here\'s some of my favorite spots in the great state of Washington to road trip to!

46.15248 -123.28656
46.80093 -122.28097
46.93173 -119.95785
47.82678 -119.97878
46.16639 -118.95113
47.59239 -120.63664
47.27658 -122.15940
46.08262,-118.90882'));
		$this->assertCount(0, WG84DegreesMinutesService::findInText('Visit these places to find where parking can be found.
46.86261 -124.06686
45.96076 -122.37305'));

		$this->assertEquals('35.194133,136.194133', WG84DegreesMinutesService::findInText('This cache is placed at following place.
N35 11.648
E136 11.648')[0]->__toString());
		$this->assertEquals('35.194133,-136.194133', WG84DegreesMinutesService::findInText('This cache is placed at following place.
N35 11.648
W136 11.648')[0]->__toString());
		$this->assertEquals('-35.194133,136.194133', WG84DegreesMinutesService::findInText('This cache is placed at following place.
S35 11.648
E136 11.648')[0]->__toString());
		$this->assertEquals('-35.194133,-136.194133', WG84DegreesMinutesService::findInText('This cache is placed at following place.
S35 11.648
W136 11.648')[0]->__toString());

		$this->assertEquals('37.675200,127.053250', WG84DegreesMinutesService::findInText('N37° 40.512\' E127° 03.195\'')[0]->__toString());
		$this->assertEquals('37.675200,-127.053250', WG84DegreesMinutesService::findInText('N37° 40.512\' W127° 03.195\'')[0]->__toString());
		$this->assertEquals('-37.675200,127.053250', WG84DegreesMinutesService::findInText('S37° 40.512\' E127° 03.195\'')[0]->__toString());
		$this->assertEquals('-37.675200,-127.053250', WG84DegreesMinutesService::findInText('S37° 40.512\' W127° 03.195\'')[0]->__toString());
		$this->assertEquals('37.205750,127.205750', WG84DegreesMinutesService::findInText('N37 12.345
E127 12.345')[0]->__toString());
		$this->assertEquals('37.533183,127.058717', WG84DegreesMinutesService::findInText('N37 31.991
E127 03.523')[0]->__toString());
		$this->assertEquals('37.533183,127.058717', WG84DegreesMinutesService::findInText('N37 31.991
E127 3.523')[0]->__toString());
		$this->assertCount(0, WG84DegreesMinutesService::findInText('12°N 39°E')); // not matching coordinates without any decimal
		$this->assertEquals('-0.002050,32.035383', WG84DegreesMinutesService::findInText('S 00 00.123 E 032 02.123')[0]->__toString());
		$this->assertEquals('0.002050,32.035383', WG84DegreesMinutesService::findInText('N 00 00.123 E 032 02.123')[0]->__toString());
		$this->assertEquals('-1.703517,-16.202050', WG84DegreesMinutesService::findInText('S1°42.211 W016° 12.123')[0]->__toString());
		$this->assertEquals('-5.972000,-35.150300', WG84DegreesMinutesService::findInText('S 05° 58.320 W 035° 09.018')[0]->__toString());
	}

	/**
	 * Special cases from Geocahing listings
	 * @see testCoordinatesFromGeocachingListing
	 * @TODO should be solved? Corrected version are always below badly written coordinates
	 */
	public function testCoordinatesFromGeocachingListingSpecial(): void
	{
		// Word after coordinates is starting with E, which leads to regex matching hemispheres in this order: S, W, E. Correct should be only S and W
//		$this->assertEquals('-54.775333,-68.361650', WG84DegreesMinutesService::findInText('This cache can be found at coordinates: S 54° 46.520 W 068° 21.699 El "powertrail" más austral del mundo.')[0]->__toString());
		$this->assertEquals('-54.775333,-68.361650', WG84DegreesMinutesService::findInText('This cache can be found at coordinates: S 54° 46.520 W 068° 21.699 "powertrail" más austral del mundo.')[0]->__toString());

		// Used different degree sign "MASCULINE ORDINAL INDICATOR": \u00BA (https://www.fileformat.info/info/unicode/char/00ba/index.htm)
		// Used different quote character "RIGHT SINGLE QUOTATION MARK": \u2019 (https://www.fileformat.info/info/unicode/char/2019/index.htm)
		// Used comma instead of dot
//		$this->assertEquals('-23.585383,-46.666450', WG84DegreesMinutesService::findInText('Coordenadas finais = 23º 35,123’S  046º 39,987’W')[0]->__toString());
		$this->assertEquals('-23.585383,-46.666450', WG84DegreesMinutesService::findInText('Coordenadas finais = 23° 35.123\'S  046° 39.987\'W')[0]->__toString());

		// Used different quote character "RIGHT SINGLE QUOTATION MARK": \u2019 (https://www.fileformat.info/info/unicode/char/2019/index.htm)
//		$this->assertEquals('-23.578850,-46.640967', WG84DegreesMinutesService::findInText('As Coordenadas Finais do Bônus Cache, são:
//
//S 23° 34.731’    W 046° 38.458’, sendo')[0]->__toString());
		$this->assertEquals('-23.578850,-46.640967', WG84DegreesMinutesService::findInText('As Coordenadas Finais do Bônus Cache, são:

S 23° 34.731\'    W 046° 38.458\', sendo')[0]->__toString());

		// Used different quote character "PRIME": \u2032 (https://www.fileformat.info/info/unicode/char/2032/index.htm)
//		$this->assertEquals('-23.561100,-46.657400', WG84DegreesMinutesService::findInText('Coordenada final do cache:  S 23° 33.666′ W046° 39.444\'')[0]->__toString());
		$this->assertEquals('-23.561100,-46.657400', WG84DegreesMinutesService::findInText('Coordenada final do cache:  S 23° 33.666\' W046° 39.444\'')[0]->__toString());


	}
}
