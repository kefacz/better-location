<?php declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\Strict;
use PHPUnit\Framework\TestCase;

final class StrictTest extends TestCase
{
	public function testIsIntTrue(): void
	{
		$this->assertTrue(Strict::isInt(0));
		$this->assertTrue(Strict::isInt(1));
		$this->assertTrue(Strict::isInt(-1));
		$this->assertTrue(Strict::isInt(10));
		$this->assertTrue(Strict::isInt(-10));

		$this->assertTrue(Strict::isInt('0'));
		$this->assertTrue(Strict::isInt('1'));
		$this->assertTrue(Strict::isInt('-1'));
		$this->assertTrue(Strict::isInt('10'));
		$this->assertTrue(Strict::isInt('-10'));

		$this->assertTrue(Strict::isInt(99999999999));
		$this->assertTrue(Strict::isInt(-99999999999));
	}

	public function testIsIntFalse(): void
	{
		$this->assertFalse(Strict::isInt('a'));
		$this->assertFalse(Strict::isInt('1a'));
		$this->assertFalse(Strict::isInt('1 a'));
		$this->assertFalse(Strict::isInt('a1'));
		$this->assertFalse(Strict::isInt('+1a'));
		$this->assertFalse(Strict::isInt('-1a'));
		$this->assertFalse(Strict::isInt('+1'));
		$this->assertFalse(Strict::isInt('- 1'));

		$this->assertFalse(Strict::isInt(false));
		$this->assertFalse(Strict::isInt(true));
		$this->assertFalse(Strict::isInt(null));
		$this->assertFalse(Strict::isInt([]));
		$this->assertFalse(Strict::isInt([1]));
		$this->assertFalse(Strict::isInt([1, 234]));
		$this->assertFalse(Strict::isInt(['1']));
		$this->assertFalse(Strict::isInt(['foo']));
		$this->assertFalse(Strict::isInt(new \stdClass()));
	}

	public function testIsFloatFalse(): void
	{
		$this->assertFalse(Strict::isFloat('a', false));
		$this->assertFalse(Strict::isFloat('1a', false));
		$this->assertFalse(Strict::isFloat('1 a', false));
		$this->assertFalse(Strict::isFloat('a1', false));
		$this->assertFalse(Strict::isFloat('+1a', false));
		$this->assertFalse(Strict::isFloat('-1a', false));
		$this->assertFalse(Strict::isFloat('+1', false));
		$this->assertFalse(Strict::isFloat('- 1', false));

		$this->assertFalse(Strict::isFloat(0, false));
		$this->assertFalse(Strict::isFloat(1, false));
		$this->assertFalse(Strict::isFloat(-1, false));
		$this->assertFalse(Strict::isFloat(10, false));
		$this->assertFalse(Strict::isFloat(-10, false));

		$this->assertFalse(Strict::isFloat('0', false));
		$this->assertFalse(Strict::isFloat('1', false));
		$this->assertFalse(Strict::isFloat('-1', false));
		$this->assertFalse(Strict::isFloat('10', false));
		$this->assertFalse(Strict::isFloat('-10', false));

		$this->assertFalse(Strict::isFloat(99999999999, false));
		$this->assertFalse(Strict::isFloat(-99999999999, false));

		$this->assertFalse(Strict::isFloat(false));
		$this->assertFalse(Strict::isFloat(true));
		$this->assertFalse(Strict::isFloat(null));
		$this->assertFalse(Strict::isFloat([]));
		$this->assertFalse(Strict::isFloat([1]));
		$this->assertFalse(Strict::isFloat([1, 234]));
		$this->assertFalse(Strict::isFloat(['1']));
		$this->assertFalse(Strict::isFloat(['foo']));
		$this->assertFalse(Strict::isFloat(new \stdClass()));
	}

	public function testIsFloatAllowIntTrue(): void
	{
		$this->assertTrue(Strict::isFloat(0, true));
		$this->assertTrue(Strict::isFloat(1, true));
		$this->assertTrue(Strict::isFloat(-1, true));
		$this->assertTrue(Strict::isFloat(10, true));
		$this->assertTrue(Strict::isFloat(-10, true));

		$this->assertTrue(Strict::isFloat('0', true));
		$this->assertTrue(Strict::isFloat('1', true));
		$this->assertTrue(Strict::isFloat('-1', true));
		$this->assertTrue(Strict::isFloat('10', true));
		$this->assertTrue(Strict::isFloat('-10', true));

		$this->assertTrue(Strict::isFloat(99999999999, true));
		$this->assertTrue(Strict::isFloat(-99999999999, true));
	}

	public function testIsFloatAllowIntFalse(): void
	{
		$this->assertFalse(Strict::isFloat('a', true));
		$this->assertFalse(Strict::isFloat('1a', true));
		$this->assertFalse(Strict::isFloat('1 a', true));
		$this->assertFalse(Strict::isFloat('a1', true));
		$this->assertFalse(Strict::isFloat('+1a', true));
		$this->assertFalse(Strict::isFloat('-1a', true));
		$this->assertFalse(Strict::isFloat('+1', true));
		$this->assertFalse(Strict::isFloat('- 1', true));

		$this->assertFalse(Strict::isFloat(false));
		$this->assertFalse(Strict::isFloat(true));
		$this->assertFalse(Strict::isFloat(null));
		$this->assertFalse(Strict::isFloat([]));
		$this->assertFalse(Strict::isFloat([1]));
		$this->assertFalse(Strict::isFloat([1, 234]));
		$this->assertFalse(Strict::isFloat(['1']));
		$this->assertFalse(Strict::isFloat(['foo']));
		$this->assertFalse(Strict::isFloat(new \stdClass()));
	}

	public function testIsBool(): void
	{
		// boolable to true
		$this->assertTrue(Strict::isBool(true));
		$this->assertTrue(Strict::isBool(1));
		$this->assertTrue(Strict::isBool('1'));
		$this->assertTrue(Strict::isBool('true'));
		$this->assertTrue(Strict::isBool('TRue'));
		// boolable to false
		$this->assertTrue(Strict::isBool(false));
		$this->assertTrue(Strict::isBool(0));
		$this->assertTrue(Strict::isBool('0'));
		$this->assertTrue(Strict::isBool('false'));
		$this->assertTrue(Strict::isBool('FAlse'));

		$this->assertFalse(Strict::isBool(''));
		$this->assertFalse(Strict::isBool('11'));
		$this->assertFalse(Strict::isBool('t'));
		$this->assertFalse(Strict::isBool('ttrue'));
		$this->assertFalse(Strict::isBool(' true')); // not trimmed
		$this->assertFalse(Strict::isBool('f'));
		$this->assertFalse(Strict::isBool(1.0));
		$this->assertFalse(Strict::isBool(-1));
		$this->assertFalse(Strict::isBool(0.0000000000001));

		$this->assertFalse(Strict::isBool(null));
		$this->assertFalse(Strict::isBool([]));
		$this->assertFalse(Strict::isBool([1]));
		$this->assertFalse(Strict::isBool([1, 234]));
		$this->assertFalse(Strict::isBool(['1']));
		$this->assertFalse(Strict::isBool(['foo']));
		$this->assertFalse(Strict::isBool(new \stdClass()));
	}

	public function testBoolval(): void
	{
		// boolable to true
		$this->assertTrue(Strict::boolval(true));
		$this->assertTrue(Strict::boolval(1));
		$this->assertTrue(Strict::boolval('1'));
		$this->assertTrue(Strict::boolval('true'));
		$this->assertTrue(Strict::boolval('TRue'));
		// boolable to false
		$this->assertFalse(Strict::boolval(false));
		$this->assertFalse(Strict::boolval(0));
		$this->assertFalse(Strict::boolval('0'));
		$this->assertFalse(Strict::boolval('false'));
		$this->assertFalse(Strict::boolval('FAlse'));
	}

	public function testIsBoolException1(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Input is not valid bool');
		Strict::boolval('');
	}

	public function testIsBoolException2(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Input is not valid bool');
		Strict::boolval('f');
	}

	public function testIsUrlTrue(): void
	{
		$this->assertFalse(Strict::isUrl('http://a'));
		$this->assertTrue(Strict::isUrl('http://a.b'));
		$this->assertTrue(Strict::isUrl('http://a.b.c'));
		$this->assertTrue(Strict::isUrl('http://a.b.c.d'));

		$this->assertTrue(Strict::isUrl('https://github.com/'));
		$this->assertTrue(Strict::isUrl('https://github.com'));
		$this->assertTrue(Strict::isUrl('http://github.com'));
		$this->assertTrue(Strict::isUrl('http://github.com/'));
		$this->assertTrue(Strict::isUrl('http://github.com/path'));
		$this->assertTrue(Strict::isUrl('http://github.com/path with spaces'));
		$this->assertTrue(Strict::isUrl('https://github.com/DJTommek/better-location'));
		$this->assertTrue(Strict::isUrl('https://www.waze.com/ul?ll=50.087451,14.420671'));
		$this->assertTrue(Strict::isUrl('https://www.google.cz/maps/place/50.087451,14.420671?q=50.087451,14.420671'));
		$this->assertTrue(Strict::isUrl('https://pldr-gallery.redilap.cz/#/special-characters/'));
	}

	public function testIsUrlFalse(): void
	{
		$this->assertFalse(Strict::isUrl(''));
		$this->assertFalse(Strict::isUrl('/'));
		$this->assertFalse(Strict::isUrl('random text'));
		$this->assertFalse(Strict::isUrl('http://')); // missing domain
		$this->assertFalse(Strict::isUrl('http://localhost')); // missing domain
		$this->assertFalse(Strict::isUrl('github.com')); // missing scheme
		$this->assertFalse(Strict::isUrl('//github.com')); // missing scheme
		$this->assertFalse(Strict::isUrl('ftp://github.com/')); // invalid scheme

		$this->assertFalse(Strict::isUrl('http://192.168.1.1')); // ip address
		$this->assertFalse(Strict::isUrl('http://192.168.1.1/')); // ip address
		$this->assertFalse(Strict::isUrl('http://192.168.1.1/some path')); // ip address
		$this->assertFalse(Strict::isUrl('http://localhost')); // missing domain
		$this->assertFalse(Strict::isUrl('localhost')); // missing domain and scheme
		$this->assertFalse(Strict::isUrl('192.168.1.1')); // IPv4 address
		$this->assertFalse(Strict::isUrl('192.168.1.1/')); // IPv4 address
		$this->assertFalse(Strict::isUrl('2001:0db8:0000:0000:0000:ff00:0042:8329')); // IPv6 address
		$this->assertFalse(Strict::isUrl('2001:db8:0:0:0:ff00:42:8329')); // IPv6 address
		$this->assertFalse(Strict::isUrl('2001:db8::ff00:42:8329')); // IPv6 address
		$this->assertFalse(Strict::isUrl('0000:0000:0000:0000:0000:0000:0000:0001')); // IPv6 address
		$this->assertFalse(Strict::isUrl('::1')); // IPv6 address

		$this->assertFalse(Strict::isUrl('///vynikat.vyrábět.poctivá'));
		$this->assertFalse(Strict::isUrl('///slang.ground.markets'));

		$this->assertFalse(Strict::isUrl('   http://github.com')); // not trimmed
		// @TODO this appears to be valid according parse_url() but should it?
//		$this->assertFalse(Strict::isUrl('http://github.com   ')); // not trimmed
	}
}
