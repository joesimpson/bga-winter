<?php
namespace Bga\Games\winter;

use Bga\GameFramework\Table;
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
    $player = Players::getCurrent();
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
    
    Notifications::playAtPosition($player,0,0);

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
  
  /**
   * Example of debug function.
   * Here, jump to a state you want to test (by default, jump to next player state)
   * You can trigger it on Studio using the Debug button on the right of the top bar.
   */
  public function debug_goToState(int $state = ST_START_CARD) {
      $this->gamestate->jumpToState($state);
  }

  /**
   * Another example of debug function, to easily test the zombie code.
   */
  public function debug_playOneMove() {
      $this->debug->playUntil(fn(int $count) => $count == 1);
  }

  /**
   * TEST ZOMBIE BOT UNTIL END
   */
  public function debug_playToEnd() {
    $security =0;
    $end = false;
    $this->debug_ClearLogs();
    $isEndSQL = "SELECT count(*) FROM `gamelog` where gamelog_notification like '%endTriggered%'"; 
      
    while (!$end && $security < 25) {
      $security++;
      //$this->debug_playOneMove();
      foreach($this->gamestate->getActivePlayerList() as $playerId) {
          $playerId = (int)$playerId;
          $this->zombieTurn($this->gamestate->getCurrentState($playerId)->toArray(), $playerId);
      }
      
      $res = (int) Table::getUniqueValueFromDB($isEndSQL) ;
      $end = ($res > 0);
    }
  }
  
  function debug_Zombie(){
    $player = Players::getActive();
    $playerId = $player->getId();
    $state = Game::get()->gamestate->getCurrentMainState()->toArray();
    Game::get()->zombieTurn($state,$playerId);
  }
  
  ////////////////////////////////////////////////////
  
  ////////////////////////////////////////////////////
   
  function debug_GoToPhase(int $phase){
    Globals::setPhase($phase);
    Notifications::newPhase($phase);
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

    $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
    $playableCoords = Utils::listPlayableSpotsForNewCard($boardCards);
    Notifications::message("playableCoords ? ".json_encode($playableCoords),['json' => $playableCoords]);
  }
  
  function debug_isMovableCard(int $cardId, int $row, int $col,){
    Game::get()->trace("debug_isMovableCard - START ////////////////////////////////////////////////////");
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
    Game::get()->trace("debug_isMovableCard - END ////////////////////////////////////////////////////");
  }

  //test before isMovable
  //function debug_willBeSnowflakesSquare(int $cardId, string $squareJson,){
  //  Game::get()->trace("debug_isSnowflakesSquare - START ////////////////////////////////////////////////////");
  //  
  //  $card = Cards::get($cardId);
  //  $square = json_decode($squareJson);
  //  Utils::isSnowflakesSquare()
  //  Game::get()->trace("debug_isSnowflakesSquare - END ////////////////////////////////////////////////////");
  //}

  
  function debug_BeforeDrawCard(){

    $player = Players::getCurrent();
    $card = Cards::getDrawnCard($player);
    $card->setLocation(CARD_LOCATION_DECK);
    $this->debug_UI();
    $this->debug_GoToPlayerTurn();
  }
  function debug_PlaceCard(int $row, int $col){

    $player = Players::getCurrent();
    $card = Cards::getAll()->first();
    $card->setLocation(CARD_LOCATION_BOARD);
    $card->setRow($row);
    $card->setCol($col);
    Notifications::playAtPosition($player,$row,$col);
    //Notifications::cardPlayed($player,$card);
    Notifications::cardMoved($player,$card,'TEST');
  }
  
  function debug_PlaceToken(int $row, int $col){

    $player = Players::getCurrent();
    $token = Tokens::getPlayerHand($player->getId())->first();
    $token->setLocation(TOKEN_LOCATION_BOARD);
    $token->setRow($row);
    $token->setCol($col);
    Notifications::playAtPosition($player,$row,$col);
    Notifications::placeToken($player,$token);
  }

  function debug_SnowflakesGrid(int $cardId, int $row, int $col, int $dir){

    $player = Players::getCurrent();
    $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
    $card = Cards::get($cardId);
    
    $tokensSpots = Utils::gridOverlappingTokensFromCard($row, $col);
    Notifications::message("tokensSpots : ".json_encode($tokensSpots),['json' => $tokensSpots]);
    $tempCardLocations = [
        $cardId => [
            'row' => $row, 
            'col' => $col, 
            'dir' => $dir,
            'o' => $card,
        ]];
    $snowflakesGrid = Utils::gridComputeSnowflakesGrid($boardCards, $tempCardLocations); 
    Notifications::message("gridComputeSnowflakesGrid : ".json_encode($snowflakesGrid),['json' => $snowflakesGrid]);

    $spot = $tokensSpots[0];//depends on test
    $targetSquare = Utils::computeTargetSquareBottomRight($spot);
    $isSquare = Utils::isSnowflakesSquare($player->getTokensColor(), $targetSquare, $snowflakesGrid);
    Notifications::message("isSquare first spot ? : ".json_encode($isSquare),['targetSquare'=>$targetSquare]);
  }

  //Display list of lakes 
  function debug_listLakes(?int $cardId){

    $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
    $lakes = Utils::listBoardLakesAroundCard($boardCards, $cardId);
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
    $tokensHand = Tokens::getInLocation(TOKEN_LOCATION_HAND);
    foreach($tokensHand as $t){
      // reset row /col in them
      $t->setRow(null);
      $t->setCol(null);
    }
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
  
  //Objective : test that DARK BLUE can select a card to move to an unknown destination that would be revealed after a lake melts (because a card of that lake may be 1 row / 1 col ahead of another lake)
  function debug_lakeGivesSpotsForMove(int $testCase=0){
    Game::get()->trace("debug_lakeGivesSpotsForMove - START ////////////////////////////////////////////////////");
    Notifications::message("debug_lakeGivesSpotsForMove ////////////////////////////////////////////////////");
    $player = Players::getCurrent();
    Tokens::moveAllInLocation(TOKEN_LOCATION_BOARD,TOKEN_LOCATION_HAND);
    Cards::moveAllInLocation(CARD_LOCATION_HAND,CARD_LOCATION_DECK);
    Cards::moveAllInLocation(CARD_LOCATION_BOARD,CARD_LOCATION_DECK);
    Cards::moveAllInLocation(CARD_LOCATION_DISCARD,CARD_LOCATION_DECK);

    switch ($testCase)
    {
      case 1: //NO LAKE CHOICE
        $coords = [ //array of [row, col, dir, card_type]
          [0, 2, CARD_DIRECTION_UP,   4], 
          [0, 4, CARD_DIRECTION_DOWN,   8],
          [1, 0, CARD_DIRECTION_DOWN,   3],
          [3, 0, CARD_DIRECTION_DOWN,   6],
          [3, 2, CARD_DIRECTION_UP,   8],   //<== that central card cannot be moved EVEN after biggest lake melts
          [5, 3, CARD_DIRECTION_DOWN,   5],
          [3, 4, CARD_DIRECTION_UP,   6],
          [3, 6, CARD_DIRECTION_DOWN,   4], 
          [3, 8, CARD_DIRECTION_UP,   1], 
          [1, 7, CARD_DIRECTION_DOWN,   3], 
        ];
        $tokensCoord = [
          [3,1,  TOKEN_COUNTER_BLUE_DARK],  
          [3,8,  TOKEN_COUNTER_BLUE_LIGHT],  
        ];
        break;
      case 2:
        //Test from a bug when live testing
        $coords = [ //array of [row, col, dir, card_type]
            [-2, -22, CARD_DIRECTION_UP,   5], 
            [-2, -20, CARD_DIRECTION_DOWN,   2],
            [-3, -18, CARD_DIRECTION_UP,   7],
            [-2, -16, CARD_DIRECTION_UP,   1],
            [-3, -14, CARD_DIRECTION_UP,   4],  
            [-3, -12, CARD_DIRECTION_UP,   3],  
            [-1, -12, CARD_DIRECTION_UP,   8],
            [-3, -10, CARD_DIRECTION_DOWN,   3],
            [-3, -8, CARD_DIRECTION_DOWN,   4], 
            [-3, -6, CARD_DIRECTION_UP,   2],  //<== that central card SHOULD be moved even BEFORE lake melts
            [-1, -7, CARD_DIRECTION_UP,   6], 
            [1, -7, CARD_DIRECTION_UP,   7], 
            [-3, -4, CARD_DIRECTION_DOWN,   2], 
            [-3, -2, CARD_DIRECTION_UP,   2], 
            [-1, -3, CARD_DIRECTION_UP,   6], 
            [-4, 0, CARD_DIRECTION_DOWN,   8], 
            [-2, 0, CARD_DIRECTION_DOWN,   6], 
            [0, 0, CARD_DIRECTION_UP,   6], 
          ];
        $tokensCoord = [
          [-2,0,  TOKEN_COUNTER_BLUE_DARK],  
          [0,1,  TOKEN_COUNTER_BLUE_LIGHT], 
          [-2,1,  TOKEN_COUNTER_BLUE_DARK],  
          [-1,-2,  TOKEN_COUNTER_BLUE_LIGHT],  
          [-2,-2,  TOKEN_COUNTER_BLUE_LIGHT],  
          [1,-6,  TOKEN_COUNTER_BLUE_DARK], 
        ];
        break;
      default:
        $coords = [ //array of [row, col, dir, card_type]
            [0, 2, CARD_DIRECTION_UP,   4], 
            [0, 4, CARD_DIRECTION_DOWN,   8],
            [1, 0, CARD_DIRECTION_DOWN,   3],
            [3, 0, CARD_DIRECTION_DOWN,   6],
            [3, 2, CARD_DIRECTION_UP,   8],   //<== that central card cannot be moved before a lake melts
            [5, 2, CARD_DIRECTION_DOWN,   6],
            [3, 4, CARD_DIRECTION_DOWN,   6],
            [3, 6, CARD_DIRECTION_DOWN,   4], 
            [3, 8, CARD_DIRECTION_UP,   5], 
            [1, 7, CARD_DIRECTION_DOWN,   2], 
          ];
        $tokensCoord = [
          [3,1,  TOKEN_COUNTER_BLUE_DARK],  
          [3,8,  TOKEN_COUNTER_BLUE_LIGHT],  
        ];
        break;
    }

    $cards = Cards::getAll(); 
    foreach($coords as $coord){
      $cardType = $coord[3];
      $card = $cards->filter(function ($card) use($cardType){
          return $cardType == $card->getType() && CARD_LOCATION_DECK == $card->getLocation() ;
        })->first();
      $card->setRow($coord[0]);
      $card->setCol($coord[1]);
      $card->setDirection($coord[2]);
      $card->setLocation(CARD_LOCATION_BOARD);
    }
    $tokens = Tokens::getAll();
    foreach($tokensCoord as $coord){
      $tokenType = $coord[2];
      $token = $tokens->filter(function ($token) use($tokenType){
          return $tokenType == $token->getType() && TOKEN_LOCATION_HAND == $token->getLocation() ;
        })->first();
      $token->setLocation(TOKEN_LOCATION_BOARD);
      $token->setRow($coord[0]);
      $token->setCol($coord[1]);
    }

    Globals::setPhase(PHASE_THAWING);
    $this->debug_UI();

    $this->gamestate->jumpToState(ST_PLAYER_TURN);
  }

  //test css limits defining rows/cols : -100 to 100 for now
  function debug_CardsOn1Row(int $row, int $leftCol){

    $player = Players::getCurrent();
    $cards = Cards::getAll();
    $col = $leftCol +1;
    foreach($cards as $card){
      $card->setLocation(CARD_LOCATION_BOARD);
      $card->setRow($row);
      $card->setCol($col);
      Notifications::cardMoved($player,$card,'TEST');
      $col+=2;
    }
    Notifications::playAtPosition($player,$row,$col);
  }

  function debug_addPoints(int $n = 5){

    $player = Players::getCurrent();
    Notifications::addPoints($player,$n, ("TEST Score +$n"));
  }
}
