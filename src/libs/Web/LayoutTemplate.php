<?php declare(strict_types=1);

namespace App\Web;

use App\User;
use App\Web\Login\LoginFacade;

class LayoutTemplate
{
	/** @var LoginFacade */
	public $login;
	/** @var ?User */
	public $user;
	/** @var int */
	public $cachebusterMainCss;
	/**
	 * @var string Full URL including path without trailing slash
	 * @example https://better-location.palider.cz/better-location/www
	 * @example https://better-location.palider.cz
	 */
	public $baseUrl;
	/**
	 * @var string Path part of URL without domain and trailing slash
	 * @example '/better-location/www'
	 * @example ''
	 */
	public $basePath;
	/**
	 * @var \Generator<FlashMessage>
	 */
	public \Generator $flashMessages;
}

