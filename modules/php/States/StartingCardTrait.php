<?php

namespace Bga\Games\winter\States;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Exceptions\UnexpectedException;
use Bga\Games\winter\Exceptions\UserException;
use Bga\Games\winter\Game;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Players;

trait StartingCardTrait
{
  /**
   * Game state arguments 
   *
   * @return array
   * @see states.inc.php
   */
  public function argStartingCard(): array
  {
    $player = Players::getActive();
    $firstCard = Cards::getTopOf(CARD_LOCATION_BOARD);
    $card = Cards::getDrawnCard($player);

    $args = [
      "card" => $card,
      "playableDir" => [ CARD_DIRECTION_UP, CARD_DIRECTION_DOWN],
      "playableCoords" => $firstCard->getNeighbouringSpots(),
    ];
      
    $this->addArgsForUndo($args);
    return $args;
  }
  
  /**
   * Player action
   *
   * @throws BgaUserException
   */
  public function actPlayStartingCard(int $dir, int $row, int $col, #[IntParam(name: 'v')] int $version,): void
  {
    Game::get()->checkVersion($version);
    self::trace("actPlayStartingCard($dir,$row, $col)");

    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();

    // check input values
    $args = $this->argStartingCard();
    $playableCoords = $args['playableCoords'];
    if (!in_array([$row, $col], $playableCoords)) {
      throw new UnexpectedException(101,"Invalid coordinates choice [$row, $col]");
    }
    if (!in_array($dir, $args['playableDir'])) {
      throw new UnexpectedException(102,"Invalid direction choice $dir");
    }

    //ACTION EFFECT
    $card = Cards::getDrawnCard($player);
    $card->setRow($row);
    $card->setCol($col);
    $card->setLocation(CARD_LOCATION_BOARD);
    $card->setDirection($dir);

    // Notify all players about the card played.
    Notifications::cardPlayed($player,$card);

    // at the end of the action, move to the next state
    $this->addCheckpoint(ST_COLOR_CHOICE);
    $this->gamestate->nextState("next");
  }
 
}
