<?php

namespace Bga\Games\winter\States;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
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

    if(PHASE_THAWING === $phase) {//If last phase, check for end game trigger
      $players = Players::getAll();
      $playersWithAllTokens = $players->filter(function($player) { return NB_COUNTER_COPIES == $player->getNbTokensInHand();});
      if($playersWithAllTokens->count() > 0 ) {
        //END GAME TRIGGER : 1 player has no more tokens on board (= all tokens are in hand)
        //Due to game rules, only 1 player should be able to lost all tokens in the same turn
        Notifications::endTriggered($playersWithAllTokens->first());
        $this->addCheckpoint(ST_END_SCORING);
        $this->gamestate->nextState('end');
        return;
      }
    }

    Globals::setupNewTurn();
      $activePlayer = Players::getActive();
      $player = Players::getNextPlayerNotEliminated($activePlayer->getId());
    Players::changeActive($player->getId());
    $player->giveExtraTime();
    Players::setupNewTurn($player);

    $this->addCheckpoint(ST_PLAYER_TURN);
    $this->gamestate->nextState("nextPlayer");
  }
}
