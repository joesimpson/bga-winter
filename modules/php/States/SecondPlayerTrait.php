<?php

namespace Bga\Games\winter\States;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Managers\Players;

trait SecondPlayerTrait
{
   
  /**
   * Game state action 
   *
   */
  public function stSecondPlayer(): void {

    $activePlayer = Players::getActive();
    $player = Players::getNextPlayerNotEliminated($activePlayer->getId());
    
    Players::changeActive($player->getId());
    $player->giveExtraTime();

    $this->addCheckpoint(ST_COLOR_CHOICE);
    $this->gamestate->nextState("next");
  }
}
