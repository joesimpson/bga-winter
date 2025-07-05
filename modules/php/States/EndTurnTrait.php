<?php

namespace Bga\Games\winter\States;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Managers\Cards;

trait EndTurnTrait
{
   
  public function stEndTurn()
  { 
    self::trace("stEndTurn()");

    $this->addCheckpoint(ST_END_TURN);

    $phase = Globals::getPhase();
    switch($phase){
      case PHASE_FREEZING:
        $deckSize = Cards::countInLocation(CARD_LOCATION_DECK);
        if($deckSize === 0){
          //trigger END OF PHASE
          Globals::setPhase(PHASE_THAWING);
          Notifications::newPhase(PHASE_THAWING);
        }
        break;
      case PHASE_THAWING:
        
        break;
      default: break;
    }
    

    $this->addCheckpoint(ST_NEXT_TURN);
    $this->gamestate->nextState('next');
  }
  
  
}
