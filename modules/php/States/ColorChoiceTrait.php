<?php

namespace Bga\Games\winter\States;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Exceptions\UnexpectedException;
use Bga\Games\winter\Game;
use Bga\Games\winter\Managers\Players;

trait ColorChoiceTrait
{
  /**
   * Game state arguments 
   *
   * @return array
   * @see states.inc.php
   */
  public function argColorChoice(): array
  {
    $args = [
      "playableColors" => [TOKEN_COUNTER_BLUE_DARK, TOKEN_COUNTER_BLUE_LIGHT],
    ];
      
    $this->addArgsForUndo($args);
    return $args;
  }
  
  /**
   * Player action
   *
   * @throws BgaUserException
   */
  public function actChooseColor(int $color, #[IntParam(name: 'v')] int $version,): void
  {
    Game::get()->checkVersion($version);
    self::trace("actCollectDraw($color)");

    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();

    // check input values
    $args = $this->argColorChoice();
    $playableColors = $args['playableColors'];
    if (!in_array($color, $playableColors)) {
      throw new UnexpectedException(110,"Invalid color $color");
    }

    //TODO JSA ACTION EFFECT : change players colors and refresh ui


    // Notify all players 
    Notifications::colorChosen($player,$color);

    // at the end of the action, move to the next state
    $this->addCheckpoint(ST_NEXT_TURN);
    $this->gamestate->nextState("next");
  }
 
}
