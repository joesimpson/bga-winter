<?php

namespace Bga\Games\winter\States;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Managers\Players;

trait NextTurnTrait
{
   
  /**
   * Game state action, example content.
   *
   * The action method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
   */
  public function stNextPlayer(): void {

    $phase = Globals::getPhase();

    if(false){
      //TODO JSA END GAME TRIGGER
      $this->addCheckpoint(ST_END_SCORING);
      $this->gamestate->nextState('end');
      return;
    }

    Globals::setupNewTurn();
    $turn = Globals::getTurn();
    if($turn==1){
      $player = Players::get(Globals::getFirstPlayer());
    }
    else {
      $activePlayer = Players::getActive();
      $player = Players::getNextPlayerNotEliminated($activePlayer->getId());
    }
    Players::changeActive($player->getId());
    $player->giveExtraTime();
    Players::setupNewTurn($player);

    $this->addCheckpoint(ST_PLAYER_TURN);
    $this->gamestate->nextState("nextPlayer");
  }
}
