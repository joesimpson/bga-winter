<?php

namespace Bga\Games\winter\States;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\GameFramework\Notify;
use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Exceptions\UnexpectedException;
use Bga\Games\winter\Game;
use Bga\Games\winter\Helpers\Collection;
use Bga\Games\winter\Helpers\Utils;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Players;

/**
 * All about 'placeCard' game state 
 */
trait PlayerTurnPlaceCardTrait
{
  /**
   * Game state arguments 
   *
   * This method returns some additional information that is very specific to the `playerTurn` game state.
   *
   * @return array
   * @see ./states.inc.php
   */
  public function argPlaceCard(): array
  {
    $player = Players::getActive();
    $card = Cards::getDrawnCard($player);

    $playableCoords = Utils::listPlayableSpotsForNewCard();

    $args = [
      "card" => $card,
      "playableDir" => [ CARD_DIRECTION_UP, CARD_DIRECTION_DOWN],
      "playableCoords" => $playableCoords,

    ];
      
    $this->addArgsForUndo($args);
    return $args;
  }

  /**
   * Player action, example content.
   *
   * @throws BgaUserException
   */
  public function actPlaceCard(int $dir,int $row, int $col, #[IntParam(name: 'v')] int $version,): void
  {
    Game::get()->checkVersion($version);
    self::trace("actPlaceCard($dir,$row, $col,)");

    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();
    
    // check input values
    $args = $this->argPlaceCard();
    $playableCoords = $args['playableCoords'];
    if (!in_array([$row, $col], $playableCoords)) {
      throw new UnexpectedException(101,"Invalid coordinates choice [$row, $col]");
    }
    if (!in_array($dir, $args['playableDir'])) {
      throw new UnexpectedException(102,"Invalid direction choice $dir");
    }

    $card = $args['card'];//getDrawnCard
    $card->setRow($row);
    $card->setCol($col);
    $card->setLocation(CARD_LOCATION_BOARD);
    $card->setDirection($dir);

    // Notify all players about the card played.
    Notifications::cardPlayed($player,$card);

    // at the end of the action, move to the next state
    $this->gamestate->nextState("playCard");
  }
   
}
