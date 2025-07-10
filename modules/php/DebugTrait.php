<?php
namespace Bga\Games\winter;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Helpers\GridUtils;
use Bga\Games\winter\Helpers\Log;
use Bga\Games\winter\Helpers\QueryBuilder;
use Bga\Games\winter\Helpers\Utils;
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
    Game::get()->trace("debug_Setup - START ////////////////////////////////////////////////////");
    $this->debug_ClearLogs();
    Log::disable();
    $options = ["DEBUG_SETUP"=> true,  ];
    $players = self::loadPlayersBasicInfos();
    $playersDatas = Players::getAll();
    
    Stats::DB()->delete()->run();
    Cards::DB()->delete()->run();
    Tokens::DB()->delete()->run();
    Globals::DB()->delete()->run();
    Notifications::refreshUI($this->getAllDatas());
    //* V1
    Players::DB()->delete()->run();
    Game::get()->setupNewGame($players,$options);

    /* V2
    Globals::setupNewGame($players, $options);
    Stats::setupNewGame($playersDatas);
    Cards::setupNewGame($playersDatas,$options);
    Tokens::setupNewGame($players,$options);
    */

    Log::enable();

    $players = self::loadPlayersBasicInfos();
    $playersDatas = Players::getAll();
    Notifications::refreshUI($this->getAllDatas());
    foreach($playersDatas as $playerData){
      $expectedColor = $playerData->getTokensColor();
      Notifications::newPlayerColor($playerData,$expectedColor);
    }
    
    $this->addCheckpoint(ST_START_CARD);
    $this->gamestate->jumpToState(ST_START_CARD);
    Game::get()->trace("debug_Setup - END ////////////////////////////////////////////////////");
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
      
      $card->setDirection(CARD_DIRECTION_UP);
    } 

    Notifications::refreshUI($this->getAllDatas());
  }
  
  function debug_GridTokens(int $nbTokens){
    Tokens::moveAllInLocation(TOKEN_LOCATION_HAND,TOKEN_LOCATION_DECK);
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


  function debug_spotsForNewCard(){

    $playableCoords = Utils::listPlayableSpotsForNewCard();
    Notifications::message("playableCoords ? ".json_encode($playableCoords),['json' => $playableCoords]);
  }
  
  function debug_isMovableCard(int $cardId, int $row, int $col,){
    $player = Players::getActive();
    $token_color = $player->getTokensColor();
    // $removableCards = Utils::listRemovableCardsOnBoard();
    // $movableCards = Utils::listMovableCardsOnBoard($token_color, $player->getNbTokensInHand(),$removableCards);
    //Notifications::message("movableCards".json_encode($movableCards),['json' => $movableCards]);

    $card = Cards::get($cardId);
    //$spotsForCard = Utils::listPlayableSpotsForNewCard([$cardId]);
    //Notifications::message("spotsForCard".json_encode($spotsForCard),['json' => $spotsForCard]);

    ////////////////////---------------------------------------------------------------------
    $boardTokens = Tokens::getBoardTokens();
    $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
    $allDirs = [CARD_DIRECTION_UP, CARD_DIRECTION_DOWN];
    
    foreach( $allDirs as $dir){
      $isMovableCard = Utils::isMovableCard($card,$row, $col, $dir,$token_color,$boardCards, $boardTokens);
      Notifications::message("isMovableCard with dir $dir ? ".json_encode($isMovableCard),['json' => $isMovableCard]);
    }
    ////////////////////---------------------------------------------------------------------



  }

  
  function debug_PlaceToken(int $row, int $col){

    $player = Players::getCurrent();
    $token = Tokens::getPlayerHand($player->getId())->first();
    $token->setLocation(TOKEN_LOCATION_BOARD);
    $token->setRow($row);
    $token->setCol($col);
    Notifications::placeToken($player,$token);
  }

  function debug_SnowflakesGrid(int $cardId, int $row, int $col, int $dir){

    $player = Players::getCurrent();
    $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);

    $tempCardLocations = [
        $cardId => [
            'row' => $row, 
            'col' => $col, 
            'dir' => $dir,
        ]];
    $snowflakesGrid = Utils::gridComputeSnowflakesGrid($boardCards, $tempCardLocations); 
    Notifications::message("gridComputeSnowflakesGrid : ".json_encode($snowflakesGrid),['json' => $snowflakesGrid]);
  }

  function debug_listLakes(){

    $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
    $lakes = Utils::listBoardLakes($boardCards);
    Notifications::message("lakes : ".json_encode($lakes),['json' => $lakes]);
  }

  // Objective : test different lakes configurations, and confirm we have a choice when 2 lakes of same size
  function debug_SelectFrozenLake(bool $withChoice, bool $preview){

    $player = Players::getCurrent();
    Globals::setPhase(PHASE_THAWING);
    $coords = [ [-3,-2], [-2,-0], [-1,-4], [-1,-2], [0,0], [2,0], 
        [2,4],  [3,-4], [3,-2], [4,0], [4,3], [5,5], [5,7], [6,3], [7,6], [7,8], [7,10], 
        [2,2],  
      ];
    if($withChoice){
      $coords = [ [-3,-2], [-2,-0], [-1,-4], [-1,-2], [0,0], [-2,2], 
          [2,-4], [2,-2], 
          [4,0], 
          [2,2], [4,3], [5,5], [5,7], [7,6], [7,8],  
          [2,0],//Bridge between 4 Lakes !
        ];
    }

    $tokensCoord = [
      [4,0], [0,-0], [5,6], [7,8], 
    ];

    $cards = Cards::getAll();
    $k=0;
    foreach($cards as $card){
      if($k >= count($coords)){
        //cards we don't need for this test
        $card->setRow(null);
        $card->setCol(null);
        $card->setLocation(CARD_LOCATION_DISCARD);
        continue;
      }
      $coord = $coords[$k];
      $card->setRow($coord[0]);
      $card->setCol($coord[1]);
      $card->setLocation(CARD_LOCATION_BOARD);
      $k++;

      if($k == count($coords)){
        //LAST card is for prepared move
        $card->setRow(null);
        $card->setCol(null);
        $card->setLocation(CARD_LOCATION_HAND);
      }
    }

    //Tokens coords :
    Tokens::moveAllInLocation(TOKEN_LOCATION_BOARD,TOKEN_LOCATION_HAND);
    foreach($tokensCoord as $coord){
      $token = Tokens::getPlayerHand($player->getId())->first();
      $token->setLocation(TOKEN_LOCATION_BOARD);
      $token->setRow($coord[0]);
      $token->setCol($coord[1]);
    }
    
    $this->debug_UI();
    
    if(!$preview){
      $this->gamestate->jumpToState(ST_PLAYER_TURN_LAKE_CHOICE);
    }
  }
}
