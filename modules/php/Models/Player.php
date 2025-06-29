<?php

namespace Bga\Games\winter\Models;

use Bga\Games\winter\Game;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Players;
use Bga\Games\winter\Managers\Tokens;

/*
 * Player: all utility functions concerning a player
 */

class Player extends \Bga\Games\winter\Helpers\DB_Model
{
  private $map = null;
  protected $table = 'player';
  protected $primary = 'player_id';
  protected $attributes = [
    'id' => ['player_id', 'int'],
    'no' => ['player_no', 'int'],
    'name' => 'player_name',
    'color' => 'player_color',
    'eliminated' => 'player_eliminated',
    'score' => ['player_score', 'int'],
    'scoreAux' => ['player_score_aux', 'int'],
    'zombie' => 'player_zombie',

    //GAME SPECIFIC : 
  ];

  public function getUiData($currentPlayerId = null)
  {
    $data = parent::getUiData();

    $data["nbtokens"] = $this->getNbTokensOnBoard();
    $data["t_color"] = $this->getTokensColor();
 
    return $data;
  }

  public function getPref($prefId)
  {
    //return Preferences::get($this->id, $prefId);
    //BGA framework :
    return Game::get()->userPreferences->get($this->getId(),$prefId);
  }

  public function getStat($name)
  {
    $name = 'get' . \ucfirst($name);
    return Stats::$name($this->getId());
  }
  
  // /**
  //  * @param int $points
  //  * @param bool $sendNotif (Default true)
  //  */
  // public function addPoints($points, $sendNotif = true)
  // {
  //   if($points == 0) return;
  //   //REAL INC in DB in case of not up to date score in object
  //   Players::incPlayerScore($this->getId(), $points);
  //   Stats::inc( "score", $this->getId(), $points );
  //   if($sendNotif) Notifications::addPoints($this,$points);
  // }

  public function setTieBreakerPoints($points)
  {
    $this->setScoreAux($points);
  }
  public function addTieBreakerPoints($points)
  {
    if($points == 0) return;
    $this->incScoreAux($points);
  }

  /**
   * Sets player datas related to turn number $turnIndex
   * @param int $turnIndex
   */
  public function startTurn($turnIndex)
  { 
  }
  
  public function giveExtraTime(){
    Game::get()->giveExtraTime($this->getId());
  }

    /**
   * @return int number of player tokens on board
   */
  public function getNbTokensOnBoard()
  {
    $n = Tokens::countPlayerTokens($this->getId(),TOKEN_LOCATION_BOARD);
    return $n;
  }
  
    /**
   * @return int color of player tokens, 0 if unknown
   */
  public function getTokensColor()
  {
    if(array_key_exists($this->getColor(), PLAYER_COLORS)){
      return PLAYER_COLORS[$this->getColor()];
    }
    else return 0;
  }
}
