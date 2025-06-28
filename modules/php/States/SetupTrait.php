<?php

namespace Bga\Games\winter\States;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Players;
use Bga\Games\winter\Managers\Tokens;

trait SetupTrait
{
  
  /*
      setupNewGame:
      
      This method is called only once, when a new game is launched.
      In this method, you must setup the game according to the game rules, so that
      the game is ready to be played.
  */
  protected function setupNewGame($players, $options = [])
  {
    Globals::setupNewGame($players, $options);
    $playersDatas = Players::setupNewGame($players, $options);
    Stats::setupNewGame($playersDatas);
    Cards::setupNewGame($playersDatas,$options);
    Tokens::setupNewGame($players,$options);

    $this->setGameStateInitialValue('logging', true); 

    /************ End of the game initialization *****/
  }
 
}
