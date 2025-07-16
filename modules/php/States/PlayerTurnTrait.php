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
    $spots = [];

    $actionsMessage = '';
    $possibleActions = [];
    $movableCards = [];
    $removableCards = [];
    $removableTokens = [];
    switch($phase){
      case PHASE_FREEZING:
        $possibleActions = ['actDraw'];
        //$actionsMessage = clienttranslate('draw and play a card or place 1 counter');
        $spots = ($availableTokens ? Utils::listPlayableSpotsForNewToken($token_color) : []);
        if(count($spots) > 0) $possibleActions[] = 'actPlaceToken';
        break;
      case PHASE_THAWING:
        $possibleActions = ['actMoveCard','actPrepareMoveCard','actRemoveCard', 'actRemoveToken'];
        //$actionsMessage = clienttranslate('move a card, remove');
        $removableCards = Utils::listRemovableCardsOnBoard();
        $movableCards = Utils::listMovableCardsOnBoard($token_color, $player->getNbTokensInHand(),$removableCards);
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
  
  public function stPlayerTurn(): void
  {
    $args = $this->argPlayerTurn();

    if (count($args['pActions']) == 1 && count($args['previousSteps'])==0 ) {
      //ONLY 1 possibleAction and no UNDO -> let's see if we can auto play
      switch($args['pActions'][0]){
        case 'actDraw': 
          $this->actDraw(0,true);
          return;
        default: break;
      }
      return;
    }

  }

  /**
   * Player action of drawing a card in phase 1
   *
     * @param bool $auto : (optional) is this action automatic ?
     * 
   * @throws BgaUserException
   */
  public function actDraw(#[IntParam(name: 'v')] int $version, bool $auto = false): void
  {
    if (!$auto) {
      Game::get()->checkVersion($version);
    }

    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();

    $args = $this->argPlayerTurn();
    if (!in_array('actDraw', $args['pActions'])) {
      throw new UnexpectedException(130,"Invalid action actDraw");
    }

    //Deck should not be empty in phase 1
    $card = Cards::pickOneForLocation(CARD_LOCATION_DECK, CARD_LOCATION_HAND);

    Stats::inc("actions_draw",$player);
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
    Game::get()->checkVersion($version);

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
    $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
    $split = Utils::isCardBetween2Lakes($boardCards, $card);
    Notifications::playAtPosition($player,$card->getRow(), $card->getCol());
    $fromLocation = $card->coordName();
    $card->setRow(null);
    $card->setCol(null);
    $card->setLocation(CARD_LOCATION_DISCARD);

    Notifications::removeCard($player,$card, $fromLocation);

    Globals::setLastPlayedTokens([]);
    Globals::setLastPlayedCards([]);
    Notifications::refreshLastPlayed(Globals::getLastPlayedDatas());
    Stats::inc("actions_discard_card",$player);

    if($split){
      $this->gamestate->nextState("lakeChoice");
    }
    else {
      $this->gamestate->nextState("next");
    }
  }
   
  
  /**
   * Player action in phase 2 : move a card and place tokens
   *
   * @throws BgaUserException
   */
  public function actMoveCard(int $cardId, int $dir,int $row, int $col, #[IntParam(name: 'v')] int $version,): void
  {
    Game::get()->checkVersion($version);
    self::trace("actMoveCard($cardId, $dir,$row, $col,)");

    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();
    
    // check input values
    $args = $this->argPlayerTurn();
    if (!in_array('actMoveCard', $args['pActions'])) {
      throw new UnexpectedException(130,"Invalid action actMoveCard");
    }
    $movableCards = $args['m_cards'];
    if (!array_key_exists($cardId, $movableCards)) {
      throw new UnexpectedException(141,"Invalid card id $cardId");
    }
    $foundPossibleTarget = false;
    $moveTargets = $movableCards[$cardId]['targets'];
    foreach($moveTargets as $moveTarget){
      if($moveTarget['row'] != $row ) continue;
      if($moveTarget['col'] != $col ) continue;
      if(!in_array($dir,$moveTarget['dirs'] )) continue;
      $foundPossibleTarget = true;
    }
    if (!$foundPossibleTarget) {
      throw new UnexpectedException(150,"Invalid target [$row, $col, $dir] ");
    }
    
    $moveWillSplit = $movableCards[$cardId]['split'];
    if ($moveWillSplit) {//UI should call actPrepareMoveCard
      throw new UnexpectedException(151,"Forbidden action actMoveCard");
    }

    //ACTION EFFECT
    $card = Cards::get($cardId);
    $fromLocation = $card->coordName();
    $card->setRow($row);
    $card->setCol($col);
    $card->setDirection($dir);
    Notifications::playAtPosition($player,$row, $col);
    Notifications::cardMoved($player,$card,$fromLocation);
    
    //PLACE TOKENS on each border of this card when matching a NEW square
    $token_color = $player->getTokensColor();
    $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
    $boardTokens = Tokens::getBoardTokens();
    $squareSpots = Utils::listMovableCardNewTokens( $card, $row, $col, $dir,$token_color, $boardCards, $boardTokens);
    
    $newTokens = new Collection();
    foreach( $squareSpots as $spot){
        //If new right spot for a token, Then add new token
        $token = Tokens::getPlayerHand($pId)->first();
        if(!isset($token)) break;
        $token->setRow($spot[0]);
        $token->setCol($spot[1]);
        $token->setLocation(TOKEN_LOCATION_BOARD);
        $newTokens->append( $token);
        Notifications::placeToken($player,$token);
    }

    Globals::setLastPlayedTokens($newTokens->map(function ($token) {
        return $token->getId();
      })->toArray());
    Globals::setLastPlayedCards([$card->getId()]);
    Notifications::refreshLastPlayed(Globals::getLastPlayedDatas());

    Stats::inc("actions_move_card",$player);

    // at the end of the action, move to the next state
    $this->gamestate->nextState("next");
  }

  
  /**
   * Player action in phase 2 : prepare the move a card when we need player action before the end of this "action"
   *
   * @throws BgaUserException
   */
  public function actPrepareMoveCard(int $cardId, #[IntParam(name: 'v')] int $version,): void
  {
    Game::get()->checkVersion($version);
    self::trace("actPrepareMoveCard($cardId, )");

    $player = Players::getCurrent();
    $pId = $player->getId();
    $this->addStep();
    
    // check input values
    $args = $this->argPlayerTurn();
    if (!in_array('actPrepareMoveCard', $args['pActions'])) {
      throw new UnexpectedException(130,"Invalid action actPrepareMoveCard");
    }
    $movableCards = $args['m_cards'];
    if (!array_key_exists($cardId, $movableCards)) {
      throw new UnexpectedException(141,"Invalid card id $cardId");
    }
    

    //ACTION EFFECT
    $card = Cards::get($cardId);
    $card->setLocation(CARD_LOCATION_HAND);
    Globals::setBeforeMoveRowCol([$card->getRow(), $card->getCol()]);
    Notifications::playAtPosition($player,$card->getRow(), $card->getCol());
    $fromLocation = $card->coordName();
    $card->setRow(null);
    $card->setCol(null);
    Notifications::prepareMoveCard($player,$card, $fromLocation);

    $moveWillSplit = $movableCards[$cardId]['split'];

    Globals::setLastPlayedTokens([]);
    Globals::setLastPlayedCards([]);
    Notifications::refreshLastPlayed(Globals::getLastPlayedDatas());
    
    Stats::inc("actions_move_card",$player);

    // at the end of the action, move to the next state
    if($moveWillSplit){
      $this->gamestate->nextState("lakeChoice");
    }
    else {
      $this->gamestate->nextState("prepareMove");
    }
  }
  
  /**
   * Player action of placing a token in phase 1
   *
   * @throws BgaUserException
   */
  public function actPlaceToken(int $row, int $col,#[IntParam(name: 'v')] int $version,): void
  {
    Game::get()->checkVersion($version);

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

    Notifications::playAtPosition($player,$row, $col);
    Notifications::placeToken($player,$token);

    Globals::setLastPlayedTokens([$token->getId()]);
    Globals::setLastPlayedCards([]);
    Notifications::refreshLastPlayed(Globals::getLastPlayedDatas());
    
    Stats::inc("actions_place_token",$player);

    $this->gamestate->nextState("next");
  }
  
  /**
   * Player action of removing a token in phase 2
   *
   * @throws BgaUserException
   */
  public function actRemoveToken(int $tokenId,#[IntParam(name: 'v')] int $version,): void
  {
    Game::get()->checkVersion($version);
    
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
    Notifications::playAtPosition($player,$token->getRow(), $token->getCol());
    $fromLocation = $token->coordName();
    $token->setRow(null);
    $token->setCol(null);
    $token->setLocation(TOKEN_LOCATION_HAND);

    Notifications::removeToken($player,$token, $fromLocation);

    Globals::setLastPlayedTokens([]);
    Globals::setLastPlayedCards([]);
    Notifications::refreshLastPlayed(Globals::getLastPlayedDatas());
    
    Stats::inc("actions_discard_token",$player);

    $this->gamestate->nextState("next");
  }
}
