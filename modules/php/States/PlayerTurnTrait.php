<?php

namespace Bga\Games\winter\States;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Exceptions\UnexpectedException;
use Bga\Games\winter\Game;
use Bga\Games\winter\Helpers\Collection;
use Bga\Games\winter\Helpers\Utils;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Players;

trait PlayerTurnTrait
{
  /**
   * Game state arguments 
   *
   * This method returns some additional information that is very specific to the `playerTurn` game state.
   *
   * @return array
   * @see ./states.inc.php
   */
  public function argPlayerTurn(): array
  {
    $player = Players::getActive();
    $token_color = $player->getTokensColor();
    $args = [
      "t_color" => $player->getTokensColor(),
      "spots_for_tokens" => Utils::listPlayableSpotsForNewToken($token_color),
    ];
      
    $this->addArgsForUndo($args);
    return $args;
  }

  /**
   * Player action of drawing a card in phase 1
   *
   * @throws BgaUserException
   */
  public function actDraw(#[IntParam(name: 'v')] int $version,): void
  {
    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();

    //Deck should not be empty in phase 1
    $card = Cards::pickOneForLocation(CARD_LOCATION_DECK, CARD_LOCATION_HAND);

    Notifications::cardDrawn($player,$card);
    $player->giveExtraTime();

    $this->addCheckpoint(ST_PLAYER_TURN_PLACE_CARD);
    $this->gamestate->nextState("draw");
  }
   
}
