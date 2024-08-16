<?php

namespace App\Tools;

class ResearchTool
{
	public static function run($config, $preferences, $files)
	{
		// load all the set items
		$compiled = [];
		foreach ($files['sets'] as $item)
		{
			if (ex($item, 'item') and !in_array(ex($item, 'item'), ['rin', 'amu']))
			{
				$compiled[ex($item, 'set')][ex($item, 'item')] = ex($item, 'lvl_req');
			}
		}

		// find out which item codes are shared with more than one set
		$items = [];
		foreach ($compiled as $set_name => $list)
		{
			foreach ($list as $code => $lvl)
			{
				$items[$code][] = [
					'set' => $set_name,
					'level' => $lvl
				];
			}
		}
		foreach ($items as $code => $list)
		{
			if (count($list) > 1)
				$items[$code] = array_orderby($list, ['level' => SORT_ASC, 'set' => SORT_ASC]);
			else
				unset($items[$code]);
		}

		// build a lookup array for these shared items
		$lookup = [];
		foreach ($items as $code => $list)
		{
			foreach ($list as $key => $i)
			{
				$this_lvl = ex($i, 'level');
				$prev_lvl = ex($list, ($key-1).'.level');
				$next_lvl = ex($list, ($key+1).'.level');

				$prev_split = floor(($this_lvl - $prev_lvl) / 2);
				$next_split = ceil(($next_lvl - $this_lvl) / 2);

				$lookup[$code][ex($i, 'set')] = [
					'lvl' => ex($i, 'level'),
					#'prev_lvl' => $prev_lvl,
					#'next_lvl' => $next_lvl,
					'low' => $prev_lvl ? $this_lvl - $prev_split : null,
					'high' => $next_lvl ? $this_lvl + $next_split + 1 : null,
				];
			}
		}

		// build a clean array for each set
		$organized = [];
		foreach ($compiled as $set_name => $items)
		{
			$high = 0;
			$low = 100;
			foreach ($items as $code => $lvl)
			{
				if ($lvl > $high) $high = $lvl;
				if ($lvl < $low) $low = $lvl;
			}

			$list = '';
			foreach ($items as $code => $lvl)
			{
				$check = ex($lookup, $code.'.'.$set_name);
				$h = ex($check, 'high');
				$l = ex($check, 'low');

				$list .= '('.$code.($l ? ' LVLREQ>'.$l : '').($h ? ' LVLREQ<'.$h : '').') OR ';
			}
			$list = trim(trim($list, ' OR '));

			$organized[] = [
				'set' => $set_name,
				'low' => $low,
				'high' => $high,
				'items' => $list,
			];
		}

		// sort by highest level for any item in the set
		$organized = array_orderby($organized, ['high' => SORT_ASC, 'set' => SORT_ASC]);

		// print to screen
		foreach ($organized as $id => $info)
		{
			terminal('ItemDisplay[ID SET ('.ex($info, 'items').')]: %NAME% %GRAY%'.($id+1).'%CONTINUE% // '.RenameTool::run($preferences, ex($info, 'set')).' (lvl '.ex($info, 'low').'-'.ex($info, 'high').')');
		}
		terminal('');
	}
}