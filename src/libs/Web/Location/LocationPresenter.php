<?php declare(strict_types=1);

namespace App\Web\Location;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\Service\AbstractService;
use App\BetterLocation\Service\Exceptions\NotImplementedException;
use App\BetterLocation\Service\Exceptions\NotSupportedException;
use App\BetterLocation\ServicesManager;
use App\Factory;
use App\Web\MainPresenter;
use Nette\Utils\Json;

class LocationPresenter extends MainPresenter
{
	private $lat;
	private $lon;
	/** @var BetterLocation */
	private $location;
	private $services = [];

	public function prepare()
	{
		if (\App\Utils\Coordinates::isLat($_GET['lat'] ?? null) && \App\Utils\Coordinates::isLon($_GET['lon'] ?? null)) {
			$this->lat = \App\Utils\Strict::floatval($_GET['lat']);
			$this->lon = \App\Utils\Strict::floatval($_GET['lon']);
			$this->location = BetterLocation::fromLatLon($this->lat, $this->lon);
			$this->location->generateAddress();

			$manager = new ServicesManager();
			foreach ($manager->getServices() as $service) {
				$this->services[] = $this->website($service, $this->lat, $this->lon);
			}
			$this->services = array_values(array_filter($this->services));
		} else {
			$this->template->setError('Invalid or missing coordinates');
		}
	}

	public function setTemplate()
	{
		$this->template = new LocationTemplate();
	}

	public function render(): void
	{
		$this->template->prepare($this->location, $this->services);
		Factory::Latte('location.latte', $this->template);
	}

	public function json(): void
	{
		$result = new \stdClass();
		$result->lat = $this->lat;
		$result->lon = $this->lon;
		$result->address = $this->location->getAddress();
		$result->services = $this->services;
		header('Content-Type: application/json');
		die(Json::encode($result));
	}

	private function website($service, float $lat, float $lon)
	{
		/** @var $service AbstractService */
		$result = [];
		try {
			$result['share'] = $service::getLink($this->lat, $this->lon);
		} catch (NotImplementedException | NotSupportedException $exception) {
		}
		try {
			$result['drive'] = $service::getLink($lat, $lon, true);
		} catch (NotSupportedException | NotImplementedException $exception) {
		}
		try {
			$result['text'] = $service::getShareText($lat, $lon);
		} catch (NotSupportedException | NotImplementedException $exception) {
		}
		if ($result !== []) {
			$result['name'] = $service::NAME;
		}
		return $result;
	}
}

