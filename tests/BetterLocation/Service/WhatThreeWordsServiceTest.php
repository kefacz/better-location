<?php declare(strict_types=1);

use App\BetterLocation\BetterLocationCollection;
use App\BetterLocation\Service\Exceptions\NotImplementedException;
use App\BetterLocation\Service\WhatThreeWordService;
use PHPUnit\Framework\TestCase;
use unreal4u\TelegramAPI\Telegram\Types\MessageEntity;

final class WhatThreeWordsServiceTest extends TestCase
{
	/** @noinspection PhpUnhandledExceptionInspection */
	public function testGenerateShareLink(): void
	{
		if (is_null(\App\Config::W3W_API_KEY)) {
			$this->markTestSkipped('Missing What3Words API Key.');
		} else {
			$this->assertSame('https://w3w.co/paves.fans.piston', WhatThreeWordService::getLink(50.087451, 14.420671));
			$this->assertSame('https://w3w.co/perkily.salon.receive', WhatThreeWordService::getLink(50.1, 14.5));
			$this->assertSame('https://w3w.co/proximity.moaned.laxatives', WhatThreeWordService::getLink(-50.2, 14.6000001)); // round down
			$this->assertSame('https://w3w.co/hardly.underpriced.frustrate', WhatThreeWordService::getLink(50.3, -14.7000009)); // round up
			$this->assertSame('https://w3w.co/stampedes.foresees.prow', WhatThreeWordService::getLink(-50.4, -14.800008));
		}
	}

	public function testGenerateDriveLink(): void
	{
		$this->expectException(NotImplementedException::class);
		$this->expectExceptionMessage('Drive link is not implemented.');
		WhatThreeWordService::getLink(50.087451, 14.420671, true);
	}

	public function testIsValidWords(): void
	{
		$this->assertTrue(WhatThreeWordService::isValidStatic('///aaaa.bbbb.cccc'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('///a.b.c'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('///stampedes.foresees.prow'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('stampedes.foresees.prow'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('///chladná.naopak.vložit'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('///井水.组装.湖泊'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('///шейна.читалня.мишле'));

		$this->assertFalse(WhatThreeWordService::isValidStatic(''));
		$this->assertFalse(WhatThreeWordService::isValidStatic('///a.b.c.d'));
		$this->assertFalse(WhatThreeWordService::isValidStatic('///a-b.c'));
		$this->assertFalse(WhatThreeWordService::isValidStatic('///a b.c'));
		$this->assertFalse(WhatThreeWordService::isValidStatic('//stampedes.foresees.prow'));
		$this->assertFalse(WhatThreeWordService::isValidStatic('/// stampedes.foresees.prow'));
	}

	public function testIsValidShortUrl(): void
	{
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://w3w.co/aaaa.bbbb.cccc'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('http://w3w.co/aaaa.bbbb.cccc'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://www.w3w.co/aaaa.bbbb.cccc'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('http://www.w3w.co/aaaa.bbbb.cccc'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://w3w.co/井水.组装.湖泊'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://w3w.co/%E4%BA%95%E6%B0%B4.%E7%BB%84%E8%A3%85.%E6%B9%96%E6%B3%8A'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://w3w.co/kobry.sedátko.vývozy'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://w3w.co/%EB%A7%A4%EC%B6%9C.%EC%88%98%ED%96%89.%EC%B9%BC%EA%B5%AD%EC%88%98?alias=매출.수행.칼국수'));
//		$this->assertTrue(WhatThreeWordService::isValidStatic('https://w3w.co/útlum.hravost.rohlíky')); // @TODO for some reason it is returning invalid character: "útlum.hravost.rohl�_ky"
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://w3w.co/%D1%88%D0%B5%D0%B9%D0%BD%D0%B0.%D1%87%D0%B8%D1%82%D0%B0%D0%BB%D0%BD%D1%8F.%D0%BC%D0%B8%D1%88%D0%BB%D0%B5?alias=шейна.читалня.мишле'));

		$this->assertFalse(WhatThreeWordService::isValidStatic(''));
		$this->assertFalse(WhatThreeWordService::isValidStatic('https://w3w.co/aaaa.bbbb.cccc.ddd'));
		$this->assertFalse(WhatThreeWordService::isValidStatic('https://w3w.co/aaaa-bbbb.cccc'));
	}

	public function testIsValidNormalUrl(): void
	{
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://what3words.com/aaaa.bbbb.cccc'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('http://what3words.com/aaaa.bbbb.cccc'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://www.what3words.com/aaaa.bbbb.cccc'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('http://www.what3words.com/aaaa.bbbb.cccc'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://what3words.com/井水.组装.湖泊'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://what3words.com/%E4%BA%95%E6%B0%B4.%E7%BB%84%E8%A3%85.%E6%B9%96%E6%B3%8A'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://what3words.com/kobry.sedátko.vývozy'));
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://what3words.com/%EB%A7%A4%EC%B6%9C.%EC%88%98%ED%96%89.%EC%B9%BC%EA%B5%AD%EC%88%98?alias=매출.수행.칼국수'));
//		$this->assertTrue(WhatThreeWordService::isValidStatic('https://what3words.com/útlum.hravost.rohlíky')); // @TODO for some reason it is returning invalid character: "útlum.hravost.rohl�_ky"
		$this->assertTrue(WhatThreeWordService::isValidStatic('https://what3words.com/%D1%88%D0%B5%D0%B9%D0%BD%D0%B0.%D1%87%D0%B8%D1%82%D0%B0%D0%BB%D0%BD%D1%8F.%D0%BC%D0%B8%D1%88%D0%BB%D0%B5?alias=шейна.читалня.мишле'));

		$this->assertFalse(WhatThreeWordService::isValidStatic(''));
		$this->assertFalse(WhatThreeWordService::isValidStatic('https://invalid.com/aaaa.bbbb.cccc'));
	}

	public function testGeneral(): void
	{
		if (is_null(\App\Config::W3W_API_KEY)) {
			$this->markTestSkipped('Missing What3Words API Key.');
		} else {
			$entity = new MessageEntity();
			$entity->type = 'url';
			$entity->offset = 9;
			$entity->length = 21;
			$entities[] = $entity;
			$entity = new MessageEntity();
			$entity->type = 'url';
			$entity->offset = 49;
			$entity->length = 25;
			$entities[] = $entity;
			$result = BetterLocationCollection::fromTelegramMessage('Hello ///smaller.biggest.money there! Random URL https://tomas.palider.cz/ there...', $entities);
			$this->assertCount(1, $result);
			$this->assertSame('50.086258,14.423709', $result[0]->__toString());
		}
	}

	public function testWords(): void
	{
		if (is_null(\App\Config::W3W_API_KEY)) {
			$this->markTestSkipped('Missing What3Words API Key.');
		} else {
			$collection = WhatThreeWordService::processStatic('///define.readings.cucumber')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('49.297286,14.126510', $collection[0]->__toString());

			$collection = WhatThreeWordService::processStatic('///chladná.naopak.vložit')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('49.297286,14.126510', $collection[0]->__toString());

			$collection = WhatThreeWordService::processStatic('///dispersant.cuts.authentication')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('-25.066260,-130.100342', $collection[0]->__toString());

			$collection = WhatThreeWordService::processStatic('///smaller.biggest.money')->getCollection(); // TG is thinking, that this is URL (probably .money is valid domain)
			$this->assertCount(1, $collection);
			$this->assertSame('50.086258,14.423709', $collection[0]->__toString());
		}
	}

	public function testShortUrls(): void
	{
		if (is_null(\App\Config::W3W_API_KEY)) {
			$this->markTestSkipped('Missing What3Words API Key.');
		} else {
			$collection = WhatThreeWordService::processStatic('https://w3w.co/define.readings.cucumber')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('49.297286,14.126510', $collection[0]->__toString());

			$collection = WhatThreeWordService::processStatic('https://w3w.co/chladná.naopak.vložit')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('49.297286,14.126510', $collection[0]->__toString());

			$collection = WhatThreeWordService::processStatic('https://w3w.co/chladn%C3%A1.naopak.vlo%C5%BEit')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('49.297286,14.126510', $collection[0]->__toString());

			$collection = WhatThreeWordService::processStatic('https://w3w.co/dispersant.cuts.authentication')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('-25.066260,-130.100342', $collection[0]->__toString());
		}
	}

	public function testNormalUrls(): void
	{
		if (is_null(\App\Config::W3W_API_KEY)) {
			$this->markTestSkipped('Missing What3Words API Key.');
		} else {
			$collection = WhatThreeWordService::processStatic('https://what3words.com/define.readings.cucumber')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('49.297286,14.126510', $collection[0]->__toString());

			$collection = WhatThreeWordService::processStatic('https://what3words.com/chladná.naopak.vložit')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('49.297286,14.126510', $collection[0]->__toString());

			$collection = WhatThreeWordService::processStatic('https://what3words.com/chladn%C3%A1.naopak.vlo%C5%BEit')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('49.297286,14.126510', $collection[0]->__toString());

			$collection = WhatThreeWordService::processStatic('https://what3words.com/dispersant.cuts.authentication')->getCollection();
			$this->assertCount(1, $collection);
			$this->assertSame('-25.066260,-130.100342', $collection[0]->__toString());
		}
	}
}
