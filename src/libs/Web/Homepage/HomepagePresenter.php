<?php declare(strict_types=1);

namespace App\Web\Homepage;

use App\Factory;
use App\Web\MainPresenter;

class HomepagePresenter extends MainPresenter
{
	public function render(): void
	{
		Factory::Latte('homepage.latte', $this->template);
	}
}

