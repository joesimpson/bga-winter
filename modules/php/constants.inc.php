<?php
 
const BGA_GAMESTATE_GAMEVERSION = 300;

const PHASE_BEGINNING = 0;
const PHASE_FREEZING = 1;
const PHASE_THAWING = 2;

const SCORE_LOOSER = 0;
const SCORE_WINNER = 10;

/*
 * Game Constants
 */
  
/////////////////////////////////////////////////////////
//          CARDS
/////////////////////////////////////////////////////////

const CARD_LOCATION_DISCARD = 'discard';
const CARD_LOCATION_DECK = 'deck';
const CARD_LOCATION_BOARD = 'board';
const CARD_LOCATION_HAND = 'hand';

const CARD_COLOR_BLUE_LIGHT = 1;
const CARD_COLOR_BLUE_DARK = 2;

const CARD_DIRECTION_UP = 1;
const CARD_DIRECTION_DOWN = 2;

/////////////////////////////////////////////////////////
//          MEEPLES
/////////////////////////////////////////////////////////
const TOKEN_LOCATION_DECK = 'deck';
const TOKEN_LOCATION_BOARD = 'board';
const TOKEN_LOCATION_HAND = 'hand';

const TOKEN_COUNTER_BLUE_LIGHT = 1;
const TOKEN_COUNTER_BLUE_DARK = 2;

const PLAYER_COLORS = [
   //dark blue
   '5396c9' => TOKEN_COUNTER_BLUE_DARK,
   //light blue
   '86cada' => TOKEN_COUNTER_BLUE_LIGHT,
];

//9 per color
const NB_COUNTER_COPIES = 9;

/////////////////////////////////////////////////////////
//          Game options
/////////////////////////////////////////////////////////  



/////////////////////////////////////////////////////////
//          User preferences
/////////////////////////////////////////////////////////  
const PREF_UNDO_STYLE = 101;
const PREF_UNDO_STYLE_TEXT = 1;
const PREF_UNDO_STYLE_ICON = 2;

const PREF_CONFIRM = 102;
const PREF_CONFIRM_DISABLED = 0;
//const PREF_CONFIRM_TIMER = 1;
const PREF_CONFIRM_ENABLED = 2;
const PREF_CONFIRM_ENABLED_START = 3;

const PREF_DRAW_CONFIRM = 103;
const PREF_DRAW_CONFIRM_DISABLED = 0;
const PREF_DRAW_CONFIRM_ENABLED = 2;

const PREF_UI_DISPLAY_COORDINATES = 110;
const PREF_UI_DISPLAY_COORDINATES_DISABLED = 0;
const PREF_UI_DISPLAY_COORDINATES_ENABLED = 2;
 
const ALL_PREFERENCES = [
   PREF_UNDO_STYLE,
   PREF_CONFIRM,
   PREF_DRAW_CONFIRM,
];
/////////////////////////////////////////////////////////
//          GAME STATES
/////////////////////////////////////////////////////////  
const ST_START_CARD = 2;
const ST_SECOND_PLAYER = 3;
const ST_COLOR_CHOICE = 5;

const ST_NEXT_TURN = 10;
const ST_PLAYER_TURN = 20;

const ST_PLAYER_TURN_PLACE_CARD = 30;
const ST_PLAYER_TURN_LAKE_CHOICE = 40;

const ST_CONFIRM_CHOICES = 70;
const ST_CONFIRM_TURN = 71;
const ST_END_TURN = 80;

const ST_END_SCORING = 90;
const ST_PRE_END_OF_GAME = 98;
const ST_END_GAME = 99;
 