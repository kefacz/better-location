<?php declare(strict_types=1);

namespace App;

use App\BetterLocation\BetterLocation;
use App\BetterLocation\BetterLocationCollection;
use App\Repository\FavouritesRepository;
use App\Repository\UserEntity;
use App\Repository\UserRepository;
use App\TelegramCustomWrapper\BetterLocationMessageSettings;
use App\Utils\Coordinates;

class User
{
	/** @var UserRepository */
	private $userRepository;

	/** @var UserEntity */
	private $userEntity;

	/** @var ?BetterLocationCollection Lazy list of Favourites (should be accessed only via getFavourites()) */
	private $favourites;

	/** @var ?BetterLocationMessageSettings */
	private $messageSettings;

	/** @var FavouritesRepository */
	private $favouritesRepository;

	public function __construct(int $telegramId, string $telegramDisplayname)
	{
		$db = Factory::Database();
		$this->userRepository = new UserRepository($db);
		$this->favouritesRepository = new FavouritesRepository($db);

		if (($this->userEntity = $this->userRepository->fromTelegramId($telegramId)) === null) {
			$this->userRepository->insert($telegramId, $telegramDisplayname);
			$this->userEntity = $this->userRepository->fromTelegramId($telegramId);
		} else {
			// @TODO update $this->userEntity->lastUpdate
		}
	}

	public function setLastKnownLocation(float $lat, float $lon): void
	{
		$coords = new Coordinates($lat, $lon);
		$this->userEntity->setLastLocation($coords);
		$this->userRepository->update($this->userEntity);
	}

	public function getFavourite(float $lat, float $lon): ?BetterLocation
	{
		return $this->getFavourites()->getByLatLon($lat, $lon);
	}

	/**
	 * @param string|null $title used only if it never existed before
	 */
	public function addFavourite(BetterLocation $location, ?string $title = null): BetterLocation
	{
		if ($this->getFavourite($location->getLat(), $location->getLon()) === null) { // add only if it is not added already
			$this->favouritesRepository->add($this->userEntity->id, $location->getLat(), $location->getLon(), $title);
			$this->favourites = null; // clear cached favourites
		}
		return $this->getFavourite($location->getLat(), $location->getLon());
	}

	public function deleteFavourite(BetterLocation $location): void
	{
		$this->favouritesRepository->removeByUserLatLon($this->userEntity->id, $location->getLat(), $location->getLon());
		$this->favourites = null; // clear cached favourites
	}

	public function renameFavourite(BetterLocation $location, string $title): BetterLocation
	{
		$this->favouritesRepository->renameByUserLatLon($this->userEntity->id, $location->getLat(), $location->getLon(), $title);
		$this->favourites = null; // clear cached favourites
		return $this->getFavourite($location->getLat(), $location->getLon());
	}

	public function getId(): int
	{
		return $this->userEntity->id;
	}

	public function getTelegramId(): int
	{
		return $this->userEntity->telegramId;
	}

	public function getTelegramDisplayname(): string
	{
		return $this->userEntity->telegramName;
	}

	public function getFavourites(): BetterLocationCollection
	{
		if ($this->favourites === null) {
			$this->favourites = new BetterLocationCollection();
			foreach ($this->favouritesRepository->byUserId($this->userEntity->id) as $favourite) {
				$location = BetterLocation::fromLatLon($favourite->lat, $favourite->lon);
				$location->setPrefixMessage(sprintf('%s %s', Icons::FAVOURITE, $favourite->title));
				$this->favourites->add($location);
			}
		}
		return $this->favourites;
	}

	public function getLastKnownLocation(): ?BetterLocation
	{
		$location = BetterLocation::fromLatLon($this->userEntity->getLat(), $this->userEntity->getLon());
		$location->setPrefixMessage(sprintf('%s Last location', Icons::CURRENT_LOCATION));
		$location->setDescription(sprintf('Last update %s', $this->userEntity->lastLocationUpdate->format(\App\Config::DATETIME_FORMAT_ZONE)));
		return $location;
	}

	public function getLastKnownLocationDatetime(): ?\DateTimeImmutable
	{
		return $this->userEntity->lastLocationUpdate;
	}

	public function getMessageSettings(): BetterLocationMessageSettings
	{
		if ($this->messageSettings === null) {
			$this->messageSettings = BetterLocationMessageSettings::loadByChatId($this->userEntity->id);
		}
		return $this->messageSettings;
	}
}
