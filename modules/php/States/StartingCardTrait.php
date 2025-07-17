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

    $playableDirs = [ CARD_DIRECTION_UP, CARD_DIRECTION_DOWN];
    $neighbouringSpots = $firstCard->getNeighbouringSpots();
    $playableCoords = [];
    foreach($neighbouringSpots as $coord){
      $playableCoords[] = [ 'row' => $coord[0], 'col' => $coord[1], 'dirs' => $playableDirs ];
    }

    $args = [
      "card" => $card,
      "playableCoords" => $playableCoords,
    ];
      
    $this->addArgsForUndo($args);
    return $args;
  }
  
  /**
   * Player action
   *
   * @throws BgaUserException
   */
  public function actPlayStartingCard(int $dir, int $row, int $col, #[IntParam(name: 'v')] int $version,
    bool $auto = false
  ): void
  {
    if(!$auto) Game::get()->checkVersion($version);
    self::trace("actPlayStartingCard($dir,$row, $col,$auto)");

    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();

    // check input values
    $args = $this->argStartingCard();
    $playableCoords = $args['playableCoords'];
    $foundPossibleTarget = false;
    foreach($playableCoords as $moveTarget){
      if($moveTarget['row'] != $row ) continue;
      if($moveTarget['col'] != $col ) continue;
      if(!in_array($dir,$moveTarget['dirs'] )) continue;
      $foundPossibleTarget = true;
    }
    if (!$foundPossibleTarget) {
      throw new UnexpectedException(150,"Invalid target [$row, $col, $dir] ");
    }

    //ACTION EFFECT
    $card = Cards::getDrawnCard($player);
    $card->setRow($row);
    $card->setCol($col);
    $card->setLocation(CARD_LOCATION_BOARD);
    $card->setDirection($dir);

    // Notify all players about the card played.
    Notifications::playAtPosition($player,$row, $col);
    Notifications::cardPlayed($player,$card);

    // at the end of the action, move to the next state
    //$this->addCheckpoint(ST_SECOND_PLAYER);
    $this->gamestate->nextState("next");
  }
 
}
