<?php
namespace Bga\Games\winter;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Helpers\QueryBuilder;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Players;
use Bga\Games\winter\Managers\Tokens;

/**
 * Debugging functions to be called in chat window in BGA Studio
 */
trait DebugTrait
{

  /**
   * Function to call to regenerate JSON from PHP 
   */
  function debug_JSON(){
    include dirname(__FILE__) . '/gameoptions.inc.php';

    $customOptions = $game_options;//READ from module file
    $json = json_encode($customOptions, JSON_PRETTY_PRINT);
    //Formatting options as json -> copy the DOM of this log : \n
    Notifications::message("$json",['json' => $json]);
    
    $customOptions = $game_preferences;
    $json = json_encode($customOptions, JSON_PRETTY_PRINT);
    //Formatting prefs as json -> copy the DOM of this log : \n
    Notifications::message("$json",['json' => $json]);
  }
  ////////////////////////////////////////////////////
  
  function debug_UI(){
    self::reloadPlayersBasicInfos();
    Notifications::refreshUI($this->getAllDatas());
  }
  ////////////////////////////////////////////////////

  function debug_Setup(){
    $this->debug_ClearLogs();
    $options = [];
    $players = self::loadPlayersBasicInfos();
    $playersDatas = Players::getAll();
    
    Stats::DB()->delete()->run();
    Cards::DB()->delete()->run();
    Tokens::DB()->delete()->run();
    Globals::DB()->delete()->run();
    Notifications::refreshUI($this->getAllDatas());
    /* V1
    Players::DB()->delete()->run();
    Game::get()->setupNewGame($players,$options);
    */

    //V2
    Globals::setupNewGame($players, $options);
    Stats::setupNewGame($playersDatas);
    Cards::setupNewGame($playersDatas,$options);
    Tokens::setupNewGame($players,$options);

    $players = self::loadPlayersBasicInfos();
    Notifications::refreshUI($this->getAllDatas());
    
    $this->addCheckpoint(ST_PLAYER_TURN);
    $this->gamestate->jumpToState(ST_PLAYER_TURN);
  }

  //Clear logs
  function debug_ClearLogs(){
    $query = new QueryBuilder('gamelog', null, 'gamelog_packet_id');
    $query->delete()->run();
  }

  ////////////////////////////////////////////////////
  
  function debug_GoToPlayerTurn(){
    $this->gamestate->jumpToState(ST_PLAYER_TURN);
  }
  
  function debug_GridCards(int $nbCards){
    Cards::moveAllInLocation(CARD_LOCATION_BOARD,CARD_LOCATION_DECK);
    Cards::pickForLocation($nbCards,CARD_LOCATION_DECK,CARD_LOCATION_BOARD);
    //Cards::moveAllInLocation(CARD_LOCATION_DECK,CARD_LOCATION_BOARD);
    $cards = Cards::getInLocation(CARD_LOCATION_BOARD);

    $k =0;
    foreach($cards as $card){
      $card->setCol( $k /4 ); //% (count($cards)/2)
      $card->setCol( $k );
      //Test with 2 rows of cards + row between them
      if(($k % 5) == 0){
        $card->setRow(1);
      }
      else if(($k % 4) == 0){
        $card->setRow(-2);
      }
      else if(($k % 3) == 0){
        $card->setRow(1);
        $card->setCol( -$k );
      }
      else if(($k % 2) == 0){
        $card->setRow(0);
      }
      else {
        $card->setRow(2);
      }
      $k++;
    } 

    Notifications::refreshUI($this->getAllDatas());
  }
  
  function debug_GridTokens(int $nbTokens){
    Tokens::moveAllInLocation(TOKEN_LOCATION_BOARD,TOKEN_LOCATION_DECK);
    Tokens::pickForLocation($nbTokens,TOKEN_LOCATION_DECK,TOKEN_LOCATION_BOARD);
    $tokens = Tokens::getInLocation(TOKEN_LOCATION_BOARD);

    $k =0;
    foreach($tokens as $token){
      $token->setCol( $k /4 );
      $token->setCol( $k );
      //Test with 2 rows + row between them
      if(($k % 5) == 0){
        $token->setRow(1);
      }
      else if(($k % 4) == 0){
        $token->setRow(-2);
      }
      else if(($k % 3) == 0){
        $token->setRow(1);
        $token->setCol( -$k );
      }
      else if(($k % 2) == 0){
        $token->setRow(0);
      }
      else {
        $token->setRow(2);
      }
      $k++;
    } 

    Notifications::refreshUI($this->getAllDatas());
  }

  function debug_Scoring(){
    $players = Players::getAll();
    foreach($players as $player) $player->setScore(0);
    $this->debug_UI();
    $this->computeFinalScore($players);
    
    $this->gamestate->jumpToState(ST_PLAYER_TURN);
  }
  function debug_GoToScoring(){
    $players = Players::getAll();
    foreach($players as $player) $player->setScore(0);
    $this->debug_UI();
    $this->gamestate->jumpToState(ST_END_SCORING);
  }

  function debug_Zombie(){
    $player = Players::getActive();
    $playerId = $player->getId();
    $state = Game::get()->gamestate->state();
    Game::get()->zombieTurn($state,$playerId);
  }

  function debug_RealTime(): void {
    $players = Players::getAll();
  
    $sql = [];
    //RESET TO TIMEMODE NORMAL with 120s
      $sql[] = "UPDATE `global` SET `global_value` = '180' WHERE `global`.`global_id` = 8; ";
      $sql[] = "UPDATE `global` SET `global_value` = '120' WHERE `global`.`global_id` = 9; ";
      $sql[] = "UPDATE `global` SET `global_value` = '1' WHERE `global`.`global_id` = 200; ";
      $sql[] = "UPDATE `global` SET `global_value` = '0' WHERE `global`.`global_id` = 201; ";
  
      foreach ($players as $pId => $player) {
        $sql[] = "UPDATE `player` SET `player_remaining_reflexion_time` = '150' WHERE `player`.`player_id` = $pId; ";
      }

    foreach ($sql as $q) {
      $this->DbQuery($q);
    }
  
    $this->reloadPlayersBasicInfos();
    //THEN REFRESH PAGE
  }
  
  function debug_TurnBasedNoLimit(): void {
    $players = Players::getAll();
  
    $sql = [];
      $sql[] = "UPDATE `global` SET `global_value` = '7776000' WHERE `global`.`global_id` = 8; ";
      $sql[] = "UPDATE `global` SET `global_value` = '2592000' WHERE `global`.`global_id` = 9; ";
      $sql[] = "UPDATE `global` SET `global_value` = '20' WHERE `global`.`global_id` = 200; ";
      $sql[] = "UPDATE `global` SET `global_value` = '1' WHERE `global`.`global_id` = 201; ";
  
      foreach ($players as $pId => $player) {
        $sql[] = "UPDATE `player` SET `player_remaining_reflexion_time` = '7776000' WHERE `player`.`player_id` = $pId; ";
      }

    foreach ($sql as $q) {
      $this->DbQuery($q);
    }
  
    $this->reloadPlayersBasicInfos();
    //THEN REFRESH PAGE
  }


}
