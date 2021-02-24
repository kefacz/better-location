<?php declare(strict_types=1);

namespace App\BetterLocation\Service;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\BetterLocationCollection;
use Nette\Http\UrlImmutable;

abstract class AbstractServiceNew
{
	/** @var bool */
	private $processed = false;
	/** @var string */
	protected $input;
	/** @var ?UrlImmutable */
	protected $inputUrl;
	/** @var ?UrlImmutable */
	protected $url;

	protected $collection;

	/** @var \stdClass Helper to store data between methods (eg isValid and process) */
	protected $data;

	public function __construct(string $input)
	{
		$this->input = $input;
		try {
			$this->inputUrl = new UrlImmutable($input);
			$this->url = $this->inputUrl;
		} catch (\Nette\InvalidArgumentException $exception) {
			// Silent, probably is not URL
		}
		$this->collection = new BetterLocationCollection();
		$this->data = new \stdClass();
	}

	abstract public function isValid(): bool;

	abstract public function process();

	abstract public function parse(string $input): BetterLocation;

	abstract static public function getLink(float $lat, float $lon, bool $drive = false);

	final public function getCollection(): BetterLocationCollection
	{
		return $this->collection;
	}

	public static function getConstants()
	{
		return [];
	}

	public static function getName(bool $short = false)
	{
		if ($short && defined(sprintf('%s::%s', static::class, 'NAME_SHORT'))) {
			return static::NAME_SHORT;
		} else {
			return static::NAME;
		}
	}

	public static function isValidStatic(string $input): bool {
		$instance = new static($input);
		return $instance->isValid();
	}
}
