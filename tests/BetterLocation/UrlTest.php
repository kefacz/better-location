<?php declare(strict_types=1);

namespace Tests\BetterLocation;

use PHPUnit\Framework\TestCase;


final class UrlTest extends TestCase
{
	public function testIsShortUrlTrue(): void
	{
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://bit.ly/3hFN12b'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://bit.ly/3hFN12b'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://bit.ly/BetterLocationTest'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://bit.ly/BetterLocationTest'));

		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://tinyurl.com/q4e74we'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://tinyurl.com/q4e74we'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://tinyurl.com/BetterLocationTest')); // custom URL
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://tinyurl.com/BetterLocationTest')); // custom URL

		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://t.co/F9s19A9pU2?amp=1'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://t.co/F9s19A9pU2'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://t.co/F9s19A9pU2'));

		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://rb.gy/yjoqrj'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://rb.gy/yjoqrj'));

		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://tiny.cc/ji2ysz'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://tiny.cc/ji2ysz'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://tiny.cc/BetterLocationTest')); // custom URL
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://tiny.cc/BetterLocationTest')); // custom URL

		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://jdem.cz/fguwx2'));
		// @TODO add support for URL with custom subdomain
//		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://better-location-test.jdem.cz/')); // custom permanent link

		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://1url.cz/tzmQs'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://1url.cz/tzmQs'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://1url.cz/@better-location-test'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://1url.cz/@better-location-test'));

		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://ow.ly/cjiY50FjakQ'));

		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://buff.ly/2IzTY3W'));

		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://cutt.ly/Rmrysek'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://cutt.ly/Rmrysek'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('https://cutt.ly/better-location-test'));
		$this->assertTrue(\App\BetterLocation\Url::isShortUrl('http://cutt.ly/better-location-test'));
	}

	public function testIsShortUrlFalse(): void
	{
		$this->assertFalse(\App\BetterLocation\Url::isShortUrl('Some invalid text'));
		$this->assertFalse(\App\BetterLocation\Url::isShortUrl('https://en.wikipedia.org/wiki/Prague'));
		$this->assertFalse(\App\BetterLocation\Url::isShortUrl('https://mapy.cz'));
	}

	/**
	 * @throws Exception
	 * @group request
	 */
	public function testGetRedirectUrl(): void
	{
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://bit.ly/3hFN12b'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://bit.ly/3hFN12b'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://bit.ly/BetterLocationTest')); // custom URL
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://bit.ly/BetterLocationTest')); // custom URL

		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://tinyurl.com/q4e74we'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://tinyurl.com/q4e74we'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://tinyurl.com/BetterLocationTest')); // custom URL
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://tinyurl.com/BetterLocationTest')); // custom URL

		// Twitter URLs are not returning 'location' header if provided browser useragent
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://t.co/F9s19A9pU2?amp=1'));
		$this->assertSame('https://t.co/F9s19A9pU2?amp=1', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://t.co/F9s19A9pU2?amp=1'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://t.co/F9s19A9pU2'));
		$this->assertSame('https://t.co/F9s19A9pU2', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://t.co/F9s19A9pU2'));

		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://rb.gy/yjoqrj'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://rb.gy/yjoqrj'));

		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://tiny.cc/v050vz'));
		$this->assertSame('https://tiny.cc/v050vz', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://tiny.cc/v050vz'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://tiny.cc/BetterLocationTest')); // custom URL
		$this->assertSame('https://tiny.cc/BetterLocationTest', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://tiny.cc/BetterLocationTest')); // custom URL

		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://jdem.cz/fguwx2'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://better-location-test.jdem.cz/'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://better-location-test.jdem.cz'));

		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://1url.cz/tzmQs'));
		$this->assertSame('https://1url.cz/tzmQs', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://1url.cz/tzmQs'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://1url.cz/@better-location-test'));
		$this->assertSame('https://1url.cz/@better-location-test', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://1url.cz/@better-location-test'));

		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://ow.ly/cjiY50FjakQ'));

		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://buff.ly/2IzTY3W'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://buff.ly/2IzTY3W'));

		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://cutt.ly/Rmrysek'));
		$this->assertSame('https://cutt.ly/Rmrysek', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://cutt.ly/Rmrysek'));
		$this->assertSame('https://en.wikipedia.org/wiki/Prague', \App\MiniCurl\MiniCurl::loadRedirectUrl('https://cutt.ly/better-location-test'));
		$this->assertSame('https://cutt.ly/better-location-test', \App\MiniCurl\MiniCurl::loadRedirectUrl('http://cutt.ly/better-location-test'));
	}
}
