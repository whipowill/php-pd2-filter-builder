<?php

class App
{
	public static function run($arg)
	{
		// config
		$config = require(path('config/config.php'));
		$preferences = require(path('config/preferences.php'));

		// load remote files
		$gems = csv(ex($config, 'endpoints.gems'), true, "	");
		$runewords = csv(ex($config, 'endpoints.runewords'), true, "	");
		$uniques = csv(ex($config, 'endpoints.uniques'), true, "	");
		$sets = csv(ex($config, 'endpoints.sets'), true, "	");
		$weapons = csv(ex($config, 'endpoints.weapons'), true, "	");
		$armors = csv(ex($config, 'endpoints.armors'), true, "	");

		if ($arg == 'prep')
		{
			// init
			$sections = [];
			$notes = [];

			$notes['codemap'] = 'Convert item types to loot filter codes (for runeword descriptions).';
			$sections['codemap'] = static::prep_runewords($preferences, $runewords);

			$notes['colors'] = 'Define your tiers and their colors.';
			$sections['colors'] = ex($preferences, 'colors', []);

			$notes['rename'] = 'Rename items that are labeled oddly in the game files.';
			$sections['rename'] = ex($preferences, 'rename', []);

			$notes['catchall'] = 'Define conditions to match catch-all base items.';
			$sections['catchall'] = ex($preferences, 'catchall', []);

			$notes['uniques'] = 'Set what tier to label these unique items.';
			$sections['uniques'] = static::prep_tiers($preferences, $uniques);

			$notes['sets'] = 'Set what tier to label these set items.';
			$sections['sets'] = static::prep_tiers($preferences, $sets);

			$notes['armors'] = 'Set what tier to label these base armor items.';
			$sections['armors'] = static::prep_tiers($preferences, $armors);

			$notes['weapons'] = 'Set what tier to label these base weapon items.';
			$sections['weapons'] = static::prep_tiers($preferences, $weapons);

			// convert these config sections into txt
			$lines = [];
			$lines[] = '<?php';
			$lines[] = '';
			$lines[] = '// For the tier labeling, you can use several options in your definitions:';
			$lines[] = '// 	\'item_code\' => 3 // option 1 - just use tier value';
			$lines[] = '// 	\'item_code\' => [3 => 3] // option 2 - use socket count with tier value';
			$lines[] = '// 	\'item_code\' => [\'(SOCK=3 ETH)\' => 3] // option 3 - use conditions with tier value';
			$lines[] = '// The system will automatically detect how you did it and act appropriately.';
			$lines[] = '';
			$lines[] = 'return [';
			$lines[] = '';
			foreach ($sections as $label => $values)
			{
				$lines[] = '	// '.ex($notes, $label);
				$lines[] = '	\''.$label.'\' => [';
				foreach ($values as $key => $note)
				{
					$existing_value = ex($preferences, $label.'.'.$key);

					// catch bug
					if ($existing_value == $note) $note = null;

					if (is_array($existing_value))
					{
						if (count($existing_value))
						{
							$lines[] = '		\''.addslashes($key).'\' => [ '.($note ? '// '.$note : '');
							foreach ($existing_value as $k => $v)
							{
								$default_key = is_numeric($k) ? $k : '\''.addslashes($k).'\'';
								$default_value = is_numeric($v) ? $v : '\''.addslashes($v).'\'';
								$lines[] = '			'.$default_key.' => '.$default_value.',';
							}
							$lines[] = '		],';
						}
					}
					else
					{
						$default_value = is_numeric($existing_value) ? $existing_value : '\''.addslashes($existing_value).'\'';

						if (in_array($label, ['armors', 'weapons']) and !$existing_value) $default_value = '[]';

						$lines[] = '		'.(!$existing_value ? '//' : '').'\''.addslashes($key).'\' => '.$default_value.', '.($note ? '// '.$note : '');
					}
				}
				$lines[] = '	],';
				$lines[] = '';
			}
			//$lines[] = '';
			$lines[] = '];';

			// save to file
			tofile($lines, 'config/preferences_generated.php');

			// report
			terminal('File "config/preferences_generated.php" saved.  Remember to replace your existing config with this new file.');
		}
		else
		{
			// build
			$sections = [];
			$sections[] = static::build_runewords($preferences, $runewords, $gems);
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
					$parts = explode('!!!PD2LFB!!!', $contents);

					if (count($parts) == 2)
					{
						// add to end of file
						$contents = ex($parts, 0).'!!!PD2LFB!!!'."\n\n".$string;

						file_put_contents($path, $contents);

						// report
						terminal('File "'.$path.'" saved.');
					}
				}
			}
		}
	}

	public static function prep_tiers($preferences, $items)
	{
		#xx($items);

		// order by level
		$items = array_orderby($items, ['lvl_req' => SORT_ASC, 'levelreq' => SORT_ASC, 'index' => SORT_ASC, 'name' => SORT_ASC]);

		$codes = [];
		foreach ($items as $i)
		{
			$name = ex($i, 'index', ex($i, 'name'));
			$type = ex($i, 'type', ex($i, 'item_1'));
			$item = ex($i, 'code', ex($i, 'item'), ex($i, 'namestr')); // the actual item code
			$level = ex($i, 'lvl_req', ex($i, 'levelreq', 0));

			if ($item)
			{
				$codes[$item] = static::rename($preferences, ucwords($name)).($type ? ' - '.ucwords($type) : '').' ('.$level.')'; // value is a note
			}
		}

		return $codes;
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
		$lines[] = '// https://github.com/whipowill/php-pd2-filter-builder';
		$lines[] = '';

		foreach (['uniques', 'sets', 'catchall', 'armors', 'weapons'] as $mode)
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

						$lines[] = 'ItemDisplay[!INF '.$cat.' '.$code.($conditions ? ' '.$conditions : '').']: '.ex($preferences, 'colors.'.$v).'T'.$v.' %WHITE%%NAME%{%NAME%}%MAP%%TIER-'.$v.'%';
					}
				}
				else
				{
					$lines[] = 'ItemDisplay[!INF '.$cat.' '.$code.']: '.ex($preferences, 'colors.'.$value).'T'.$value.' %WHITE%%NAME%{%NAME%}%MAP%%TIER-'.$value.'%';
				}
			}
			$lines[] = '';
		}
		#$lines[] = '';

		// return
		return $lines;
	}

	public static function prep_runewords($preferences, $runewords)
	{
		$codes = [];
		foreach ($runewords as $r)
		{
			foreach ([1, 2, 3, 4, 5, 6] as $i)
			{
				$c = ex($r, 'itype'.$i);

				if ($c and ex($r, 'complete'))
					$codes[$c] = null;
			}
		}

		return $codes;
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
		$lines[] = '// https://github.com/whipowill/php-pd2-filter-builder';
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
			$string .= 'SOCK='.count(ex($runeword, 'runes')).']: %NAME%{%NAME%%CL%%GOLD%'.static::rename($preferences, ex($runeword, 'name')).' %GRAY%'.ex($runeword, 'level').'}%CONTINUE%';

			$lines[] = $string;
		}
		$lines[] = '';

		// return
		return $lines;
	}

	protected static function rename($preferences, $string)
	{
		return ex($preferences, 'rename.'.$string, $string);
	}

	/*
	public static function build_runewords_old($preferences, $runewords)
	{
		// load config
		$preferences = require(path('config/labels2codes.php'));

		// load array of runewords
		$runewords = static::load_remote_runewords();

		// order by level
		$runewords = array_orderby($runewords, ['level' => SORT_ASC, 'title' => SORT_ASC]);

		// reverse order
		$runewords = array_reverse($runewords);

		// init
		$lines = [];
		$lines[] = '//=================================================';
		$lines[] = '// RUNEWORDS';
		$lines[] = '//=================================================';
		$lines[] = '';
		$lines[] = '// These filter codes are computer generated:';
		$lines[] = '// https://github.com/whipowill/php-pd2-filter-builder';
		$lines[] = '';
		foreach ($runewords as $runeword)
		{
			$types = ex($runeword, 'ttypes', []);

			$codes = [];
			foreach ($types as $type)
			{
				// find item codes
				$s = ex($preferences, $type);

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
			$string .= 'SOCK='.count(ex($runeword, 'runes')).']: %NAME%{%NAME%%CL%%GOLD%'.ex($runeword, 'title').' %GRAY%'.ex($runeword, 'level').'}%CONTINUE%';

			$lines[] = $string;
		}
		$lines[] = '';

		// return
		return $lines;
	}

	protected static function load_remote_runewords()
	{
		// load file
		$string = file_get_contents('https://raw.githubusercontent.com/kvothed2/pd2-runewizard/main/src/data/runewords.ts');

		// find replace
		$string = str_ireplace(
			['const runewords: TRuneword[] = ', 'export default runewords'],
			['', ''],
			$string
		);

		// remove comments
		$string = preg_replace('/\/\*[\s\S]*?\*\//', '', $string);

	    // Split the input string into lines
	    $lines = explode("\n", $string);
	    $output = [];
	    $inArray = false;
	    $arrayDepth = 0;

	    // Regular expression to match keys
	    $keyPattern = '/(\s*)(\w+)(\s*):/';

	    foreach ($lines as $index => $line)
	    {
	        // Replace keys with quoted keys
	        $line = preg_replace($keyPattern, '$1"$2"$3:', $line);

	        if (trim($line))
	        {
	        	if (!in_array(trim($line), [';']))
	        	{
	        		$output[] = $line;
	        	}
	        }
	    }

	    // Join the lines back into a single string
	    $string = implode("\n", $output);

	    // find replace
		$string = str_ireplace([':', '{', '}'], [' => ', '[', ']'], $string);

		// convert string to array
		eval('$result = ' . $string);

		// return
		return $result;
	}
	*/
}