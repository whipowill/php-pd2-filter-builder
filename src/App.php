<?php

require(path('src/Tools/ResearchTool.php')); // for custom research
require(path('src/Tools/PreferencesTool.php')); // for preparing the preferences file
require(path('src/Tools/BuildTool.php')); // for building the output
require(path('src/Tools/RenameTool.php')); // for renaming odd item names

class App
{
	protected $config = [];
	protected $preferences = [];
	protected $files = [];

	public function __construct()
	{
		// config
		$this->config = require(path('config/config.php'));
		$this->preferences = require(path('config/preferences.php'));

		// load remote files
		$this->file['gems'] = csv(ex($this->config, 'endpoints.gems'), true, "	");
		$this->file['runewords'] = csv(ex($this->config, 'endpoints.runewords'), true, "	");
		$this->file['uniques'] = csv(ex($this->config, 'endpoints.uniques'), true, "	");
		$this->file['sets'] = csv(ex($this->config, 'endpoints.sets'), true, "	");
		$this->file['weapons'] = csv(ex($this->config, 'endpoints.weapons'), true, "	");
		$this->file['armors'] = csv(ex($this->config, 'endpoints.armors'), true, "	");
	}

	public function run($method)
	{
		switch ($method)
		{
			case 'research':
				App\Tools\ResearchTool::run($this->config, $this->preferences, $this->file);
				break;
			case 'preferences':
				App\Tools\PreferencesTool::run($this->config, $this->preferences, $this->file);
				break;
			default:
				App\Tools\BuildTool::run($this->config, $this->preferences, $this->file);
				break;
		}
	}
}