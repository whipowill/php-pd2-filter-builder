<?php

namespace App\Tools;

class BuildTool
{
	// colors for map dots
	protected static $dotmap = [
		'UNI' => 'D3',
		'SET' => '7D',
		'default' => '1F',
	];

	public static function run($config, $preferences, $files)
	{
		// build
		$sections = [];
		$sections[] = static::build_runewords($preferences, $files['runewords'], $files['gems']);
		$sections[] = static::build_tiers($preferences);

		// merge
		$lines = [];
		foreach ($sections as $section)
			$lines = array_merge($lines, $section);

		// save to file
		$string = tofile($lines, 'output.txt');

		// report
		terminal('File "output.txt" saved.');

		// if adding direct to game loot filter...
		$path = ex($config, 'path');
		if ($path)
		{
			if (file_exists($path))
			{
				$contents = file_get_contents($path);

				// split
				$parts = explode('// !!!PD2LFB!!!', $contents);

				if (count($parts) >= 2)
				{
					// part 1 is always presumed to be what is being replaced
					$parts[1] = null;

					// add to end of file
					$perfect = '';
					foreach ($parts as $n => $c)
					{
						if ($c)
						{
							$perfect .= $c;

							if ($n == 0)
							{
								$perfect .= '// !!!PD2LFB!!!'."\n\n";
								$perfect .= $string."\n\n";
								$perfect .= '// !!!PD2LFB!!!';
							}
						}
					}

					file_put_contents($path, $perfect);

					// report
					terminal('File "'.$path.'" saved.');
				}
			}
		}
	}

	public static function build_tiers($preferences)
	{
		$catmap = [
			'uniques' => 'UNI',
			'sets' => 'SET',
		];

		// init
		$lines = [];
		$lines[] = '//=================================================';
		$lines[] = '// TIERS - needs to be last bc not using %CONTINUE%';
		$lines[] = '//=================================================';
		$lines[] = '';
		$lines[] = '// These filter codes are computer generated:';
		$lines[] = '// https://github.com/whipowill/php-pd2-lfb';
		$lines[] = '';

		foreach (['uniques', 'sets', 'armors', 'weapons', 'catchall'] as $mode) // catchall last
		{
			$lines[] = '// '.ucwords($mode);
			foreach (ex($preferences, $mode, []) as $code => $value)
			{
				$cat = ex($catmap, $mode, 'NMAG');

				if (is_array($value))
				{
					foreach ($value as $k => $v)
					{
						$conditions = $k;

						// if conditions only had numbers...
						if (!preg_match('/[^0-9]/', $conditions))
							$conditions = 'SOCK='.$conditions;

						// if condition is dfault
						if (in_array($conditions, ['default']))
							$conditions = null;

						// if this is base item and condition is null...
						if ($cat == 'NMAG' and !$conditions)
							$conditions = 'SOCK=0';

						// print
						$lines = static::print($preferences, $lines, $cat, $code, $v, $conditions);
					}
				}
				else
				{
					$lines = static::print($preferences, $lines, $cat, $code, $value);
				}
			}
			$lines[] = '';
		}
		#$lines[] = '';

		// return
		return $lines;
	}

	protected static function print($preferences, $lines, $cat, $code, $tier, $conditions = null)
	{
		// capture filter rule
		$filter_alert = ex($preferences, 'filters.'.$tier);
		$filter_item = null;

		// trim conditions
		$conditions = trim($conditions);

		// calc what tier label to use
		$t = ex($preferences, 'colors.'.$tier).'T'.$tier;

		// if this is a base item w/ no sockets
		if (!in_array($cat, ['UNI', 'SET']) and stripos('SOCK=0', $conditions) !== false)
		{
			// change label (use tier 6 color)
			#$t = ex($preferences, 'colors.6').'BASE';

			// if this is an actual tier 6 base, just hide it
			if ($tier >= 6) $filter_item = ex($preferences, 'filters.'.$tier);

			// So what happens is crummy base items still show only if
			// they have the right sockets for something, but they don't
			// throw an alert in the chat.  If it's a crummy base item
			// with no sockets it won't show at all.
		}

		// print
		$lines[] = 'ItemDisplay[!INF '.$cat.' '.$code.($conditions ? ' '.$conditions : '').($filter_item ? ' '.$filter_item : '').']: '.$t.' %WHITE%%NAME%{%NAME%}';
		if ($tier < 6) $lines[] = 'ItemDisplay[!INF '.$cat.' '.$code.($conditions ? ' '.$conditions : '').($filter_alert ? ' '.$filter_alert : '').']: %NAME%{%NAME%}%DOT-'.ex(static::$dotmap, $cat, ex(static::$dotmap, 'default')).'%';

		// return
		return $lines;
	}

	public static function build_runewords($preferences, $runewords, $gems)
	{
		// build lookup array of runes (this is from misc.txt)
		$runesmap = [];
		foreach ($gems as $g)
		{
			if (stripos(ex($g, 'name'), 'Rune') !== false)
			{
				$runesmap[ex($g, 'code')] = ex($g, 'levelreq');
			}
		}

		// build clean array
		$clean = [];
		foreach ($runewords as $r)
		{
			if (ex($r, 'complete'))
			{
				// capture data from runeword info
				$runes = [];
				$items = [];
				foreach ([1, 2, 3, 4, 5, 6] as $i)
				{
					$v = ex($r, 'itype'.$i);
					if ($v) $items[] = $v;

					$v = ex($r, 'rune'.$i);
					if ($v) $runes[] = $v;
				}

				$highest_gem_level = 0;
				foreach ($runes as $rune)
				{
					$score = ex($runesmap, $rune);
					if ($score and $score > $highest_gem_level)
						$highest_gem_level = $score;
				}

				$clean[] = [
					'level' => $highest_gem_level,
					'name' => ex($r, 'rune_name'),
					'items' => $items,
					'runes' => $runes,
				];
			}
		}

		// order by level
		$runewords = array_orderby($clean, ['level' => SORT_ASC, 'name' => SORT_ASC]);

		// reverse order
		$runewords = array_reverse($runewords);

		// init
		$lines = [];
		$lines[] = '//=================================================';
		$lines[] = '// RUNEWORDS';
		$lines[] = '//=================================================';
		$lines[] = '';
		$lines[] = '// These filter codes are computer generated:';
		$lines[] = '// https://github.com/whipowill/php-pd2-lfb';
		$lines[] = '';
		foreach ($runewords as $runeword)
		{
			$codes = [];
			foreach (ex($runeword, 'items') as $type)
			{
				// find item codes
				$s = ex($preferences, 'codemap.'.$type);

				// catch error...
				if (!$s) die('Unable to convert type "'.$type.'" to code.');

				$codes[] = $s;
			}
			$codes = implode(' OR ', $codes);

			// set required level limits
			$level_max = ex($runeword, 'level', 0) + 15 + 1;
			$level_min = ex($runeword, 'level', 0) - 5 - 1;
			if ($level_min < 0) $level_min = null; // don't allow negative numbers
			if ($level_min >= 40) $level_min = 40; // if min ideal item levelreq is over 50, change to 40
			if ($level_min >= 40 and $level_max >= 40) $level_max = null; // if max ideal item levelreq is over 50, eliminate limit

			// This minimum level requirement for the item is just so we don't
			// have super awesome runewords recommended on shitty items.

			$string = 'ItemDisplay[NMAG !RW !INF ('.$codes.') ';
			#if ($level_min) $string .= 'LVLREQ>'.$level_min.' ';
			#if ($level_max) $string .= 'LVLREQ<'.$level_max.' ';
			$string .= 'SOCK='.count(ex($runeword, 'runes')).']: %NAME%{%NAME%%CL%%GOLD%'.RenameTool::run($preferences, ex($runeword, 'name')).' %GRAY%'.ex($runeword, 'level').'}%CONTINUE%';

			$lines[] = $string;
		}
		$lines[] = '';

		// return
		return $lines;
	}
}