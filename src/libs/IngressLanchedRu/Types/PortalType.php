<?php declare(strict_types=1);

namespace App\IngressLanchedRu\Types;

use App\Utils\Ingress;
use Tracy\Debugger;

class PortalType
{
	/** @var string */
	public $guid;
	/** @var float */
	public $lat;
	/** @var float */
	public $lng;
	/** @var string */
	public $name;
	/** @var ?string */
	public $image;
	/** @var ?string */
	public $address;
	/** Filled on for lazy load */
	private ?string $intelLink = null;
	/** Filled on for lazy load */
	private ?string $primeLink = null;

	public static function createFromVariable(\stdClass $variables): self
	{
		$class = new self();
		foreach ($variables as $key => $value) {
			if (in_array($key, ['name', 'address'], true)) {
				// @BUG in external API: if portal name contains only numbers, it is type int instead of string
				$value = trim((string)$value);
			}
			$class->{$key} = $value;
		}
		return $class;
	}

	/** @param mixed $value */
	public function __set(string $name, $value): void
	{
		Debugger::log(sprintf('Property "%s$%s" of type "%s" is not predefined.', static::class, $name, gettype($value)), Debugger::WARNING);
	}

	public function getIntelLink(): string
	{
		if ($this->intelLink === null) {
			$this->intelLink = Ingress::generateIntelPortalLink($this->lat, $this->lng);
		}
		return $this->intelLink;
	}

	public function getPrimeLink(): string
	{
		if ($this->primeLink === null) {
			$this->primeLink = (string)Ingress::generatePrimePortalLink($this->guid, $this->lat, $this->lng);
		}
		return $this->primeLink;
	}

	/**
	 * @param ?int $size Append size parameter to image URL. See https://developers.google.com/people/image-sizing
	 */
	public function getImageLink(?int $size = null): ?string
	{
		$result = $this->image;
		if ($result !== null && $size !== null) {
			$result .= '=s' . $size;
		}
		return $result;
	}
}
