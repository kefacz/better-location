<?php declare(strict_types=1);

namespace App\Geocaching;

use App\Config;
use App\Geocaching\Types\GeocachePreviewType;
use App\MiniCurl\MiniCurl;

class Client
{
	const LINK = 'https://www.geocaching.com';
	const LINK_CACHE = self::LINK . '/geocache/';
	const LINK_CACHE_API = self::LINK . '/api/proxy/web/search/geocachepreview/';
	const LINK_SHARE = 'https://coord.info';

	const COOKIE_NAME = 'gspkauth';

	/** @var string */
	private $cookieToken;
	/** @var int */
	private $cacheTtl = 0;

	public function __construct(string $cookieToken)
	{
		$this->cookieToken = $cookieToken;
	}

	public function setCache(int $ttl): self
	{
		$this->cacheTtl = $ttl;
		return $this;
	}

	public function loadGeocachePreview(string $cacheId): GeocachePreviewType
	{
		$json = $this->makeJsonRequest(self::LINK_CACHE_API . $cacheId);
		if (isset($json->statusCode) && $json->statusCode !== 200) {
			throw new \Exception(sprintf('Loading geocache preview responded with bad response code %d: "%s"', $json->statusCode, $json->statusMessage));
		}
		return GeocachePreviewType::createFromVariable($json);
	}

	private function makeJsonRequest(string $url): \stdClass
	{
		$cookies = [
			self::COOKIE_NAME => Config::GEOCACHING_COOKIE,
		];
		return (new MiniCurl($url))
			->setCurlOption(CURLOPT_COOKIE, http_build_query($cookies))
			->allowCache($this->cacheTtl)
			->run()
			->getBodyAsJson();
	}
}
