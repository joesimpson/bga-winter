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

/**
 * All about 'placeCard' game state 
 */
trait PlayerTurnPlaceCardTrait
{
  /**
   * Game state arguments 
   *
   * @return array
   * @see ./states.inc.php
   */
  public function argPlaceCard(): array
  {
    $player = Players::getActive();
    $card = Cards::getDrawnCard($player);

    $phase = Globals::getPhase();
    $playableCoords = [];
    
    switch($phase){
      case PHASE_FREEZING:
        $spots = Utils::listPlayableSpotsForNewCard();
        foreach($spots as $coord ){
          $playableCoords[] = [ 'row' => $coord[0], 'col' => $coord[1], 'dirs' => [ CARD_DIRECTION_UP, CARD_DIRECTION_DOWN] ];
        }
        break;
      case PHASE_THAWING:
        //In this case the card comes from a move and we NEED to add tokens
        $token_color = $player->getTokensColor();
        $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
        $boardTokens = Tokens::getBoardTokens();
        $targetsForCard = Utils::listPlayableSpotsForNewCardAndTokens($card, $token_color, $boardCards, $boardTokens);

        //$playableCoords = [];
        //foreach($targetsForCard as $target ){
        //  $playableCoords[] = [$target['row'], $target['col']];
        //}
        $playableCoords = $targetsForCard;
        break;
      default: 
        break;
    }

    $args = [
      "card" => $card,
      "playableCoords" => $playableCoords,

    ];
      
    $this->addArgsForUndo($args);
    return $args;
  }

  /**
   * Player action in phase 1 : place the drawn card
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
    
    $phase = Globals::getPhase();

    $card = $args['card'];//getDrawnCard
    $card->setRow($row);
    $card->setCol($col);
    $card->setLocation(CARD_LOCATION_BOARD);
    $card->setDirection($dir);

    // Notify all players about the card played.
    Notifications::cardPlayed($player,$card);
    
    Globals::setLastPlayedTokens([]);
    Globals::setLastPlayedCards([$card->getId()]);

    if(PHASE_THAWING === $phase){
      //In this case the card comes from a move and we NEED to add tokens
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

      if($newTokens->count() == 0) {
        throw new UnexpectedException(115,"No tokens to place on this card");
      }

      Globals::setLastPlayedTokens($newTokens->map(function ($token) {
          return $token->getId();
        })->toArray());
    }

    Notifications::refreshLastPlayed(Globals::getLastPlayedDatas());

    // at the end of the action, move to the next state
    $this->gamestate->nextState("playCard");
  }
   
}
