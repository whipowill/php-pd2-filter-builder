<?php

namespace App\Tools;

class PreferencesTool
{
	public static function run($config, $preferences, $files)
	{
		// init
		$sections = [];
		$notes = [];

		$notes['codemap'] = 'Convert item types to loot filter codes (for runeword descriptions).';
		$sections['codemap'] = static::prep_runewords($preferences, $files['runewords']);

		$notes['colors'] = 'Define your tiers and their colors.';
		$sections['colors'] = ex($preferences, 'colors', []);

		$notes['filters'] = 'Define any FILTLVL codes to apply for each tier.';
		$sections['filters'] = ex($preferences, 'filters', []);

		$notes['rename'] = 'Rename items that are labeled oddly in the game files.';
		$sections['rename'] = ex($preferences, 'rename', []);

		$notes['catchall'] = 'Define conditions to match catch-all base items.';
		$sections['catchall'] = ex($preferences, 'catchall', []);

		$notes['uniques'] = 'Set what tier to label these unique items.';
		$sections['uniques'] = static::prep_tiers($preferences, $files['uniques']);

		$notes['sets'] = 'Set what tier to label these set items.';
		$sections['sets'] = static::prep_tiers($preferences, $files['sets']);

		$notes['armors'] = 'Set what tier to label these base armor items.';
		$sections['armors'] = static::prep_tiers($preferences, $files['armors']);

		$notes['weapons'] = 'Set what tier to label these base weapon items.';
		$sections['weapons'] = static::prep_tiers($preferences, $files['weapons']);

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
				$codes[$item] = RenameTool::run($preferences, ucwords($name)).($type ? ' - '.ucwords($type) : '').' ('.$level.')'; // value is a note
			}
		}

		return $codes;
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
}