# Loot Filter Builder

A standalone PHP app that helps you build custom loot filter rules for Project Diablo 2.  This is pretty nerdy stuff that only the most dedicated D2 players will care about or find interesting.

## About

What this does:

- builds runeword descriptions for all eligable base items
- builds tier labels for unique, set, and base items per your preferences
- auto adds these loot filter codes to your game loot filter

How it does these things:

- consults remote hosted game files
- builds a preferences file that lists every item in the game
- you edit the preferences file to your tastes by adding tier labels (1-6)
- builds loot filter rules based on your preferences
- opens your game loot filter file and adds them in the right place

This makes it easy to make changes to your preferences as you play.  Just run the command and it patches your loot filter for you.

## Usage

Download the app:

```bash
$ git clone git@github.com:whipowill/php-pd2-filter-builder.git
$ cd php-pd2-filter-builder
```

Rename the config file so you can input your loot filter path:

```bash
$ cp config/config_example.php config/config.php
```

Open your game ``default.filter`` and add this line to the bottom:

```
// !!!PD2LFB!!!
```

Run the prep command to make sure your preferences file is up to date (or create if missing):

```bash
$ php run prep
```

Rename the generated preferences file:

```bash
$ mv config/preferences_generated.php config/preferences.php
```

Make desired changes to the preferences files, then run the app:

```bash
$ php run
```

Your ``default.filter`` file should be patched with the generated loot filter rules.  If you didn't specify a path, or the file is missing, then the generated content is put in ``output.txt`` in the app folder.

## Settings

The tier config entries require some subjective decision making.  My operating rules for best base items are:

- Armor
	- Elite:  Wire Fleece (``utu``), Archon Plate (``utp``), Dusk Shroud (``uui``)
	- Exceptional: Trellised Armor (``xtu``), Mage Plate (``xtp``)
	- Normal: Studded Leather (``stu``), Light Plate (``ltp``)
- Polearms
	- Elite: Thresher (``7s8``), Cryptic Axe (``7pa``), Great Poleaxe (``7h7``)
	- Exceptional: Battle Scythe (``9s8``)
	- Normal: Scythe (``scy``)
- 2H Weapons
	- Elite: Colossus Blade (``7gd``), Colossus Sword (``7fb``)
	- Exceptional: Executioner Sword (``9gd``), Zweihander (``9fb``)
	- Normal: Great Sword (``gsd``), Flamberge (``flb``)
- 1H Weapons
	- Elite: Phase Blade (``7cr``), Berserker Axe (``7wa``)
- Caster
	- Crystal Sword (``crs``), Flail (``fla``)

These items are generally "the best" bc of their damage output and strength requirements.  As usual, elite ethereal items are always good for mercenaries.

You can change the config I've provided to match your own tastes.

## External Links

- [PD2 Filter Rules](https://wiki.projectdiablo2.com/wiki/Item_Filtering#Item_Codes) - loot filter helpsheet
- [PD2 Source Files](https://github.com/BetweenWalls/PD2-Singleplayer/tree/main/Diablo%20II/ProjectD2/data/global/excel/modpacks/official) - latest game files