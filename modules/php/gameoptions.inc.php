<?php

/**
 *------
  * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Winter implementation : © joesimpson <1324811+joesimpson@users.noreply.github.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * gameoptions.inc.php
 *
 * Winter game options description
 * 
 * NB : 11/2023 new JSON format you can generate it from this file with PHP : 
 * call the debug function from DebugTrait :
 *    debugJSON()
 *
 */

 namespace Bga\Games\winter;

//if placed at root folder
//require_once 'modules/php/constants.inc.php';
//Else near constants :
require_once 'constants.inc.php';

$game_options = [

];

$game_preferences = [
 
  PREF_UNDO_STYLE => [
    'name' => totranslate('Undo buttons style'),
    'needReload' => false,
    'values' => [
      PREF_UNDO_STYLE_TEXT => [ 'name' => totranslate('Text') ],
      PREF_UNDO_STYLE_ICON => [ 'name' => totranslate('Icon')],
    ],
    "default"=> PREF_UNDO_STYLE_ICON,
    'attribute' => 'winter_undo_style',
  ],

  PREF_CONFIRM => [
    'name' => totranslate('Ask for turn confirmation'),
    'needReload' => false,
    'values' => [
      PREF_CONFIRM_ENABLED_START => ['name' => totranslate('Enabled for 1st card')],
      PREF_CONFIRM_ENABLED => ['name' => totranslate('Enabled')],
      PREF_CONFIRM_DISABLED => ['name' => totranslate('Disabled')],
    ],
    "default"=> PREF_CONFIRM_ENABLED_START,
  ],
 
];
