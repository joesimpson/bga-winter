<?php
 
const BGA_GAMESTATE_GAMEVERSION = 300;

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


/////////////////////////////////////////////////////////
//          MEEPLES
/////////////////////////////////////////////////////////
const TOKEN_LOCATION_BOARD = 'board';
const TOKEN_LOCATION_HAND = 'hand';

const TOKEN_COUNTER_BLUE_LIGHT = 1;
const TOKEN_COUNTER_BLUE_DARK = 2;

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
 
const ALL_PREFERENCES = [
   PREF_UNDO_STYLE,
   PREF_CONFIRM,
];
/////////////////////////////////////////////////////////
//          GAME STATES
/////////////////////////////////////////////////////////  
const ST_NEXT_TURN = 2;

const ST_PLAYER_TURN = 20;

const ST_CONFIRM_CHOICES = 70;
const ST_CONFIRM_TURN = 71;
const ST_END_TURN = 80;

const ST_END_SCORING = 90;
const ST_PRE_END_OF_GAME = 98;
const ST_END_GAME = 99;
 