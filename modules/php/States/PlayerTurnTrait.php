<?php

namespace Bga\Games\winter\States;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Exceptions\UnexpectedException;
use Bga\Games\winter\Game;
use Bga\Games\winter\Helpers\Collection;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Players;

trait PlayerTurnTrait
{
  /**
   * Game state arguments, example content.
   *
   * This method returns some additional information that is very specific to the `playerTurn` game state.
   *
   * @return array
   * @see ./states.inc.php
   */
  public function argPlayerTurn(): array
  {
    $args = [
        "playableCardsIds" => [1, 2],
    ];
      
    $this->addArgsForUndo($args);
    return $args;
  }
  /**
   * Player action, example content.
   *
   * In this scenario, each time a player plays a card, this method will be called. This method is called directly
   * by the action trigger on the front side with `bgaPerformAction`.
   *
   * @throws BgaUserException
   */
  public function actPlayCard(int $card_id): void
  {
      // Retrieve the active player ID.
      $player_id = (int)$this->getActivePlayerId();

      // check input values
      $args = $this->argPlayerTurn();
      $playableCardsIds = $args['playableCardsIds'];
      if (!in_array($card_id, $playableCardsIds)) {
          throw new \BgaUserException('Invalid card choice');
      }

      // Add your game logic to play a card here.
      $card_name = Game::$CARD_TYPES[$card_id]['card_name'];

      // Notify all players about the card played.
      $this->notify->all("cardPlayedEXAMPLE", clienttranslate('${player_name} plays ${card_name}'), [
          "player_id" => $player_id,
          "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
          "card_name" => $card_name, // remove this line if you uncomment notification decorator
          "card_id" => $card_id,
          "i18n" => ['card_name'], // remove this line if you uncomment notification decorator
      ]);

      // at the end of the action, move to the next state
      $this->gamestate->nextState("playCard");
  }

  public function actPass(): void
  {
      // Retrieve the active player ID.
      $player_id = (int)$this->getActivePlayerId();

      // Notify all players about the choice to pass.
      $this->notify->all("pass", clienttranslate('${player_name} passes'), [
          "player_id" => $player_id,
          "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
      ]);

      // at the end of the action, move to the next state
      $this->gamestate->nextState("pass");
  }
   
}
