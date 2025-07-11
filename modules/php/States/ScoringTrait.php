<?php

namespace Bga\Games\winter\States;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Helpers\Collection;
use Bga\Games\winter\Managers\Players;

trait ScoringTrait
{
   
  //FOR TESTING PURPOSE
  public function stPreEndOfGame()
  {
    self::trace("stPreEndOfGame()");
    Notifications::emptyNotif();
    $this->gamestate->nextState('next');
  }

  public function stScoring()
  {
    self::trace("stScoring()");

    $players = Players::getAll();
    $this->computeFinalScore($players);

    $this->gamestate->nextState('next');
  }
  
  public function computeFinalScore(Collection $players): void
  {
    self::trace("computeFinalScore()");
    //Notifications::computeFinalScore();

    foreach($players as $pid => $player){
      
      $totalScore = 0;

      if(NB_COUNTER_COPIES == $player->getNbTokensInHand()){//LOOSER
        $totalScore = 0;
      }
      else {//WINNER
        $totalScore = 1;
        Notifications::addPoints($player,$totalScore,clienttranslate('${player_name} scores ${n} points for winning the game !') );
      }

      $player->setScore($totalScore);
      Stats::set( "score", $player, $totalScore );
    }
    
  }

}
