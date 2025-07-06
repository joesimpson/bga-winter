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
use Bga\Games\winter\Managers\Tokens;

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
    $phase = Globals::getPhase();
    $player = Players::getActive();
    $token_color = $player->getTokensColor();
    $availableTokens = $player->getNbTokensInHand() > 0; 
    $spots = ($availableTokens ? Utils::listPlayableSpotsForNewToken($token_color) : []);

    $actionsMessage = '';
    $possibleActions = [];
    $movableCards = [];
    $removableCards = [];
    $removableTokens = [];
    switch($phase){
      case PHASE_FREEZING:
        $possibleActions = ['actDraw', 'actPlaceToken'];
        //$actionsMessage = clienttranslate('draw and play a card or place 1 counter');
        break;
      case PHASE_THAWING:
        $possibleActions = ['actMoveCard','actRemoveCard', 'actRemoveToken'];
        //$actionsMessage = clienttranslate('move a card, remove');
        $removableCards = Utils::listRemovableCardsOnBoard();
        $movableCards = Utils::listMovableCardsOnBoard($token_color, $availableTokens,$removableCards);
        $removableTokens = Tokens::getBoardTokens($player->getId())->getIds();

        break;
      default: break;
    }

    $args = [
      "t_color" => $player->getTokensColor(),
      "m_cards" => $movableCards,
      "r_cards" => $removableCards,
      "spots_for_tokens" => $spots,
      "removableTokens" => $removableTokens,
      //"actionsMessage" => $actionsMessage,
      "pActions" => $possibleActions,
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

    $args = $this->argPlayerTurn();
    if (!in_array('actDraw', $args['pActions'])) {
      throw new UnexpectedException(130,"Invalid action actDraw");
    }

    //Deck should not be empty in phase 1
    $card = Cards::pickOneForLocation(CARD_LOCATION_DECK, CARD_LOCATION_HAND);

    Notifications::cardDrawn($player,$card);
    $player->giveExtraTime();

    $this->addCheckpoint(ST_PLAYER_TURN_PLACE_CARD);
    $this->gamestate->nextState("draw");
  }

  
  /**
   * Player action of removing a card in phase 2
   *
   * @throws BgaUserException
   */
  public function actRemoveCard(int $cardId,#[IntParam(name: 'v')] int $version,): void
  {
    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();

    // check input values
    $args = $this->argPlayerTurn();
    if (!in_array('actRemoveCard', $args['pActions'])) {
      throw new UnexpectedException(130,"Invalid action actRemoveCard");
    }
    $removableCards = $args['r_cards'];
    if (!in_array($cardId, $removableCards)) {
      throw new UnexpectedException(141,"Invalid card id $cardId");
    }

    //ACTION EFFECT
    $card = Cards::get($cardId);
    $fromLocation = $card->coordName();
    $card->setRow(null);
    $card->setCol(null);
    $card->setLocation(CARD_LOCATION_DISCARD);

    //TODO JSA Check if 2 disconnected groups of cards => ask player to keep 1 when equality

    Notifications::removeCard($player,$card, $fromLocation);

    $this->gamestate->nextState("next");
  }
   
  
  /**
   * Player action of placing a token in phase 1
   *
   * @throws BgaUserException
   */
  public function actPlaceToken(int $row, int $col,#[IntParam(name: 'v')] int $version,): void
  {
    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();

    // check input values
    $args = $this->argPlayerTurn();
    if (!in_array('actPlaceToken', $args['pActions'])) {
      throw new UnexpectedException(130,"Invalid action actPlaceToken");
    }
    $spots_for_tokens = $args['spots_for_tokens'];
    if (!in_array([$row, $col], $spots_for_tokens)) {
      throw new UnexpectedException(101,"Invalid coordinates choice [$row, $col]");
    }

    //ACTION EFFECT
    $token = Tokens::getPlayerHand($pId)->first();
    $token->setRow($row);
    $token->setCol($col);
    $token->setLocation(TOKEN_LOCATION_BOARD);

    Notifications::placeToken($player,$token);
    $player->giveExtraTime();

    $this->gamestate->nextState("next");
  }
  
  /**
   * Player action of removing a token in phase 2
   *
   * @throws BgaUserException
   */
  public function actRemoveToken(int $tokenId,#[IntParam(name: 'v')] int $version,): void
  {
    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();

    // check input values
    $args = $this->argPlayerTurn();
    if (!in_array('actRemoveToken', $args['pActions'])) {
      throw new UnexpectedException(130,"Invalid action actPlaceToken");
    }
    $removableTokens = $args['removableTokens'];
    if (!in_array($tokenId, $removableTokens)) {
      throw new UnexpectedException(140,"Invalid token id $tokenId");
    }

    //ACTION EFFECT
    $token = Tokens::get($tokenId);
    $fromLocation = $token->coordName();
    $token->setRow(null);
    $token->setCol(null);
    $token->setLocation(TOKEN_LOCATION_HAND);

    Notifications::removeToken($player,$token, $fromLocation);

    $this->gamestate->nextState("next");
  }
}
