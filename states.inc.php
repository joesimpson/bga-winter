<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * winter implementation : © joesimpson <1324811+joesimpson@users.noreply.github.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * winter game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: $this->checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

use Bga\GameFramework\GameStateBuilder;
use Bga\GameFramework\StateType;

/*
    "Visual" States Diagram :

                SETUP
                |
                startingCard
                |
                v
                secondPlayer
                |
                v
            colorChoice
              |  
              |  
 /<-----------|nextPlayer  <-------------------\
 |            | |                              ^
 |            v v                              |
 |            playerTurn  --\                  |
 |                          |                  |
 |                          v                  |
 |                   confirm --> endTurn ----->/
 v  
 \-> scoring
        | 
        v
        preEndOfGame
        | 
        v
        END
*/

$machinestates = [
 
    // only keep this line if your initial state is not 2. In that case, uncomment and replace '2' by your first state id.
    // 1 => GameStateBuilder::gameSetup(2)->build(),
    // Note: ID=2 => your first state

    ST_START_CARD => GameStateBuilder::create()
        ->name('startingCard')
        ->description(clienttranslate('${actplayer} must place the starting card'))
        ->descriptionmyturn(clienttranslate('${you} must place the starting card'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argStartingCard')
        ->possibleactions([
            'actPlayStartingCard', 
        ])
        ->transitions([
            'next' => ST_SECOND_PLAYER, 
            'zombiePass' => ST_SECOND_PLAYER,
        ])
        ->build(),

    ST_SECOND_PLAYER => GameStateBuilder::create()
        ->name('secondPlayer')
        ->type(StateType::GAME)
        ->action("stSecondPlayer")
        ->transitions([
            'next' => ST_COLOR_CHOICE,
            'zombiePass' => ST_COLOR_CHOICE,
        ])
        ->build(),

    ST_COLOR_CHOICE => GameStateBuilder::create()
        ->name('colorChoice')
        ->description(clienttranslate('${actplayer} must choose a color of snowflakes'))
        ->descriptionmyturn(clienttranslate('${you} must choose a color of snowflakes'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argColorChoice')
        ->possibleactions([
            'actChooseColor', 
        ])
        ->transitions([
            'next' => ST_PLAYER_TURN, 
            'zombiePass' => ST_PLAYER_TURN,
        ])
        ->build(),

    ST_NEXT_TURN => GameStateBuilder::create()
        ->name('nextPlayer')
        ->type(StateType::GAME)
        ->action("stNextPlayer")
        ->transitions([
            'endGame' => ST_END_GAME, 
            'nextPlayer' => ST_PLAYER_TURN,
        ])
        ->updateGameProgression(true)
        ->build(),

    //PLAYER turn for Phase 1 & phase 2, see Globals to know in which phase we are
    ST_PLAYER_TURN => GameStateBuilder::create()
        ->name('playerTurn')
        ->description(clienttranslate('${actplayer} must draw and play a card or place 1 counter'))
        ->descriptionmyturn(clienttranslate('${you} must draw and play a card or place 1 counter'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argPlayerTurn')
        ->possibleactions([
            'actDraw', 
            'actPlaceToken', 
            'actUndoToStep', 'actRestart',
        ])
        ->transitions([
            'draw' => ST_PLAYER_TURN_PLACE_CARD, 
            'next' => ST_CONFIRM_TURN,
        ])
        ->build(),

    ST_PLAYER_TURN_PLACE_CARD => GameStateBuilder::create()
        ->name('placeCard')
        ->description(clienttranslate('${actplayer} must play a card'))
        ->descriptionmyturn(clienttranslate('${you} must play a card'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argPlaceCard')
        ->possibleactions([
            'actPlaceCard', 
            'actUndoToStep', 'actRestart',
        ])
        ->transitions([
            'playCard' => ST_CONFIRM_TURN, 
            'pass' => ST_CONFIRM_TURN,
        ])
        ->build(),
        
    ST_CONFIRM_TURN => GameStateBuilder::create()
        ->name('confirmTurn')
        ->description(clienttranslate('${actplayer} must confirm or restart'))
        ->descriptionmyturn(clienttranslate('${you} must confirm or restart'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argsConfirmTurn')
        ->action("stConfirmTurn")
        ->possibleactions([
            'actConfirmTurn', 
            'actUndoToStep', 'actRestart',
        ])
        ->transitions([
            'confirm' => ST_END_TURN, 
            'zombiePass'=> ST_END_TURN,
        ])
        ->build(),

    ST_END_TURN => GameStateBuilder::create()
        ->name('endTurn')
        ->description(clienttranslate('End turn'))
        ->type(StateType::GAME)
        ->action("stEndTurn")
        ->transitions([
            'next' => ST_NEXT_TURN, 
        ])
        ->updateGameProgression(true)
        ->build(),
        
    ST_END_SCORING => GameStateBuilder::create()
        ->name('scoring')
        //->description(clienttranslate('Scoring'))
        ->type(StateType::GAME)
        ->action("stScoring")
        ->transitions([
            'next' => ST_PRE_END_OF_GAME, 
        ])
        ->updateGameProgression(true)
        ->build(),

    ST_PRE_END_OF_GAME => GameStateBuilder::create()
        ->name('preEndOfGame')
        ->type(StateType::GAME)
        ->action("stPreEndOfGame")
        ->transitions([
            'next' => ST_END_GAME, 
            //'next' => 96, 
        ])
        ->updateGameProgression(true)
        ->build(),

    //END GAME TESTING STATE for DEBUG ONLY
    /*
    96 => [
        "name" => "playerGameEnd",
        "description" => ('${actplayer} Game Over'),
        "descriptionmyturn" => ('${you} Game Over'),
        'type' => 'activeplayer',
        "args" => "argPlayerTurn",
        "args" => "argCardCollect",
        "possibleactions" => ["endGame"],
        "transitions" => [
            "next" => ST_END_GAME,
            "loopback" => 96 
        ] 
    ],
    */
];



