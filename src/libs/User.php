<?php declare(strict_types=1);

use BetterLocation\BetterLocation;
use BetterLocation\Service\Coordinates\WG84DegreesService;
use BetterLocation\Service\Exceptions\InvalidLocationException;

class User
{
	private $db;

	private $id;
	private $telegramId;
	private $telegramUsername;
	private $lastKnownLocation;
	private $lastKnownLocationDatetime;

	const FAVOURITES_STATUS_ENABLED = 1;
	const FAVOURITES_STATUS_DELETED = 2;

	/**
	 * @TODO convert to \BetterLocation\BetterLocationCollection
	 * @var BetterLocation[]
	 */
	private $favourites = [];

	/**
	 * @TODO convert to \BetterLocation\BetterLocationCollection
	 * @var BetterLocation[]
	 */
	private $favouritesDeleted = [];

	/**
	 * User constructor.
	 *
	 * @param int $telegramId
	 * @param string|null $telegramUsername
	 * @throws InvalidLocationException
	 */
	public function __construct(int $telegramId, ?string $telegramUsername = null) {
		$this->telegramId = $telegramId;
		$this->telegramUsername = $telegramUsername;
		$this->db = Factory::Database();
		$userData = $this->register($telegramId, $telegramUsername);
		$this->updateCachedData($userData);
		$this->loadFavourites();
	}

	/**
	 * @param $newUserData
	 * @throws InvalidLocationException
	 * @throws Exception
	 */
	private function updateCachedData($newUserData) {
		$this->id = $newUserData['user_id'];
		$this->telegramId = $newUserData['user_telegram_id'];
		$this->telegramUsername = $newUserData['user_telegram_name'];
		if (isset($newUserData['user_location_lat']) and isset($newUserData['user_location_lon']) and isset($newUserData['user_location_last_update'])) {
			if (is_null($newUserData['user_location_lat']) || is_null($newUserData['user_location_lon']) || is_null($newUserData['user_location_last_update'])) {
				$this->lastKnownLocation = null;
				$this->lastKnownLocationDatetime = null;
			} else {
				$this->lastKnownLocation = new BetterLocation(
					sprintf('%F,%F', $newUserData['user_location_lat'], $newUserData['user_location_lon']),
					$newUserData['user_location_lat'],
					$newUserData['user_location_lon'],
					WG84DegreesService::class,
				);
				$this->lastKnownLocationDatetime = new \DateTimeImmutable($newUserData['user_location_last_update'], new \DateTimeZone('UTC'));
				$this->lastKnownLocation->setPrefixMessage(sprintf('%s Last location', Icons::CURRENT_LOCATION));
				$this->lastKnownLocation->setDescription(sprintf('Last update %s', $this->lastKnownLocationDatetime->format(\Config::DATETIME_FORMAT_ZONE)));
			}
		}
	}

	public function register(int $telegramId, ?string $telegramUsername = null) {
		$this->db->query('INSERT INTO better_location_user (user_telegram_id, user_telegram_name, user_last_update, user_registered) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP()) 
			ON DUPLICATE KEY UPDATE user_telegram_name = ?, user_last_update = UTC_TIMESTAMP()',
			$telegramId, $telegramUsername, $telegramUsername
		);
		return $this->load();
	}

	/**
	 * @param float $lat
	 * @param float $lon
	 * @throws InvalidLocationException
	 */
	public function setLastKnownLocation(float $lat, float $lon) {
		$this->update(null, $lat, $lon);
	}

	private function load() {
		return $this->db->query('SELECT * FROM better_location_user WHERE user_telegram_id = ?', $this->telegramId)->fetchAll()[0];
	}

	/**
	 * @return BetterLocation[]
	 */
	public function loadFavourites(): array {
		$favourites = $this->db->query('SELECT * FROM better_location_favourites WHERE user_id = ?', $this->id)->fetchAll(\PDO::FETCH_OBJ);
		$this->favourites = [];
		$this->favouritesDeleted = [];
		foreach ($favourites as $favouriteDb) {
			$key = sprintf('%F,%F', $favouriteDb->lat, $favouriteDb->lon);
			$location = new BetterLocation($key, $favouriteDb->lat, $favouriteDb->lon, WG84DegreesService::class);
			$location->setPrefixMessage(sprintf('%s %s', Icons::FAVOURITE, $favouriteDb->title));
			if ($favouriteDb->status === self::FAVOURITES_STATUS_ENABLED) {
				$this->favourites[$key] = $location;
			} else if ($favouriteDb->status === self::FAVOURITES_STATUS_DELETED) {
				$this->favouritesDeleted[$key] = $location;
			} else {
				throw new Exception(sprintf('Unexpected type of favourites type: "%d"', $favouriteDb->status));
			}
		}
		return $this->getFavourites();
	}

	public function getFavourite(float $lat, float $lon): ?BetterLocation {
		$key = sprintf('%F,%F', $lat, $lon);
		if (isset($this->favourites[$key])) {
			return $this->favourites[$key];
		} else {
			return null;
		}
	}

	/**
	 * @param BetterLocation $betterLocation
	 * @param string|null $title used only if it never existed before
	 * @return BetterLocation
	 * @throws Exception
	 */
	public function addFavourite(BetterLocation $betterLocation, ?string $title = null): BetterLocation {
		$key = $betterLocation->__toString();
		if (in_array($key, $this->favourites)) {
			// already saved
		} else if (in_array($key, $this->favouritesDeleted)) { // already saved but deleted
			$this->db->query('UPDATE better_location_favourites SET status = ? WHERE user_id = ? AND lat = ? AND lon = ?',
				self::FAVOURITES_STATUS_ENABLED, $this->id, $betterLocation->getLat(), $betterLocation->getLon()
			);
			$this->favourites[$key] = $this->favouritesDeleted[$key];
			unset($this->favouritesDeleted[$key]);
		} else { // not in database at all
			$this->db->query('INSERT INTO better_location_favourites (user_id, status, lat, lon, title) VALUES (?, ?, ?, ?, ?)',
				$this->id, self::FAVOURITES_STATUS_ENABLED, $betterLocation->getLat(), $betterLocation->getLon(), $title
			);
			$this->loadFavourites();
		}
		return $this->favourites[$key];
	}

	/**
	 * @param BetterLocation $betterLocation
	 * @throws Exception
	 */
	public function deleteFavourite(BetterLocation $betterLocation): void {
		$this->db->query('UPDATE better_location_favourites SET status = ? WHERE user_id = ? AND lat = ? AND lon = ?',
			self::FAVOURITES_STATUS_DELETED, $this->id, $betterLocation->getLat(), $betterLocation->getLon()
		);
		$key = $betterLocation->__toString();
		$this->favouritesDeleted[$key] = $this->favourites[$key];
		unset($this->favourites[$key]);
	}

	/**
	 * @param BetterLocation $betterLocation
	 * @param string $title
	 * @throws Exception
	 */
	public function renameFavourite(BetterLocation $betterLocation, string $title): BetterLocation {
		$this->db->query('UPDATE better_location_favourites SET title = ? WHERE user_id = ? AND lat = ? AND lon = ?',
			htmlspecialchars($title),
			$this->id, $betterLocation->getLat(), $betterLocation->getLon()
		);
		$this->loadFavourites();
		return $this->getFavourite($betterLocation->getLat(), $betterLocation->getLon());
	}

	/**
	 * @param string|null $telegramUsername
	 * @param float|null $locationLat
	 * @param float|null $locationLon
	 * @return $this
	 * @throws InvalidLocationException
	 */
	public function update(?string $telegramUsername = null, ?float $locationLat = null, ?float $locationLon = null) {
		$queries = [];
		$params = [];
		if (is_string($telegramUsername)) {
			$queries[] = 'user_telegram_name = ?';
			$params[] = $telegramUsername;
		}
		if ($locationLat && $locationLon) {
			if (BetterLocation::isLatValid($locationLat) === false || BetterLocation::isLonValid($locationLon) === false) {
				throw new InvalidLocationException('Invalid coordinates');
			}
			$queries[] = 'user_location_lat = ?';
			$params[] = $locationLat;
			$queries[] = 'user_location_lon = ?';
			$params[] = $locationLon;
			$queries[] = 'user_location_last_update = UTC_TIMESTAMP()';
		}
		if (count($params) > 0) {
			$query = sprintf('UPDATE better_location_user SET %s WHERE user_telegram_id = ?', join($queries, ', '));

			$params[] = $this->telegramId;
			call_user_func_array([$this->db, 'query'], array_merge([$query], $params));
			$newData = $this->load();
			$this->updateCachedData($newData);
		}
		return $this->get();
	}

	public function get() {
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return mixed
	 */
	public function getTelegramId() {
		return $this->telegramId;
	}

	/**
	 * @return mixed
	 */
	public function getTelegramUsername() {
		return $this->telegramUsername;
	}

	/**
	 * @return BetterLocation[]
	 */
	public function getFavourites(): array {
		return $this->favourites;
	}

	public function getLastKnownLocation(): ?BetterLocation {

		return $this->lastKnownLocation;
	}

	public function getLastKnownLocationDatetime(): ?\DateTimeImmutable {

		return $this->lastKnownLocationDatetime;
	}
}
