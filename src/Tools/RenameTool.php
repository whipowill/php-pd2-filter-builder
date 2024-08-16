<?php

namespace App\Tools;

class RenameTool
{
	public static function run($preferences, $string)
	{
		return ex($preferences, 'rename.'.$string, $string);
	}
}