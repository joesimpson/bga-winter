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
 
  PREF_DRAW_CONFIRM => [
    'name' => totranslate('Warning before drawing a card'),
    'needReload' => false,
    'values' => [
      PREF_DRAW_CONFIRM_ENABLED => ['name' => totranslate('Enabled')],
      PREF_DRAW_CONFIRM_DISABLED => ['name' => totranslate('Disabled')],
    ],
    "default"=> PREF_DRAW_CONFIRM_DISABLED,
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

  PREF_UNDO_STYLE => [
    'name' => totranslate('Undo buttons style'),
    'needReload' => false,
    'values' => [
      PREF_UNDO_STYLE_TEXT => [ 'name' => totranslate('Text'), "cssPref"=> "winter_undo_style_text",],
      PREF_UNDO_STYLE_ICON => [ 'name' => totranslate('Icon'), "cssPref"=> "winter_undo_style_icon",],
    ],
    "default"=> PREF_UNDO_STYLE_ICON,
    'attribute' => 'winter_undo_style',
  ],
 
  PREF_BACKGROUND => [
    'name' => totranslate('Background'),
    'needReload' => false,
    'values' => [
      PREF_BACKGROUND_BLUE=> [ 'name' => totranslate('Blue Theme'), "cssPref"=> "winter_background_blue"],
      PREF_BACKGROUND_BGA => [ 'name' => totranslate('BGA standard'), "cssPref"=> "winter_background_none"],
    ],
    "default"=> PREF_BACKGROUND_BLUE,
    'attribute' => 'winter_background_style',
  ],

  PREF_UI_DISPLAY_COORDINATES => [
    'name' => totranslate('Display coordinates on board'),
    'needReload' => false,
    'values' => [
      PREF_UI_DISPLAY_COORDINATES_ENABLED => ['name' => totranslate('Enabled'), "cssPref"=> "winter_display_coord_on"],
      PREF_UI_DISPLAY_COORDINATES_DISABLED => ['name' => totranslate('Disabled'), "cssPref"=> "winter_display_coord_off"],
    ],
    "default"=> PREF_UI_DISPLAY_COORDINATES_DISABLED,
    'attribute' => 'winter_display_coord',
  ],
  
];
