<?php declare(strict_types=1);

namespace App\Utils;

class StringUtils
{
	/** Replace or remove some characters */
	public static function translit(string $text): string
	{
		$chars = [
			'"' => ['″'],
			'\'' => ['′'],
		];
		foreach ($chars as $validChar => $invalidChars) {
			$text = str_replace($invalidChars, $validChar, $text);
		}
		return $text;
	}

	public static function startWith(string $haystack, string $needle): bool
	{
		return mb_substr($haystack, 0, mb_strlen($needle)) === $needle;
	}

	public static function isGuid(string $guid, bool $supportParenthess = true) {
		$regex = '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}';
		if ($supportParenthess) {
			$regex = '{?' . $regex . '}?';
		}
		return !!preg_match('/^' . $regex . '$/i', $guid);
	}
}
