<?php

namespace Bga\Games\winter\Managers;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Game;
use Bga\Games\winter\Helpers\Collection;
use Bga\Games\winter\Helpers\Utils;
use Bga\Games\winter\Models\Card;
use Bga\Games\winter\Models\Player;
use Bga\Games\winter\Models\Snowflake;

/* Class to manage all the cards */

class Cards extends \Bga\Games\winter\Helpers\Pieces
{
  protected static $table = 'cards';
  protected static $prefix = 'card_';
  protected static $autoIncrement = true;
  protected static $autoremovePrefix = false;
  protected static $customFields = [ 'type', 'x', 'y'];
  protected static $autoreshuffle = true;
  protected static $autoreshuffleCustom = [CARD_LOCATION_DECK => CARD_LOCATION_DISCARD];

  protected static function cast($row)
  {
    $type = isset($row['type']) ? $row['type'] : null;
    $data = self::getSnowflakesCardsTypes()[$type];
    return new Card($row, $data);
  }

  /**
   * @param int $currentPlayerId Id of current player loading the game
   * @return array all cards visible by this player
   */
  public static function getUiData($currentPlayerId)
  {
    //$privateCards = self::getPlayerHand($currentPlayerId);

    return 
      self::getInLocation(CARD_LOCATION_BOARD)
      //->merge($privateCards)
      ->merge(self::getInLocation(CARD_LOCATION_HAND))
      ->map(function ($card) {
        return $card->getUiData();
      })
      ->toArray();
  } 
  
  public static function getByType(int $cardType)
  {
    return self::DB()
      ->where('type', $cardType)
      ->get();
  }
  
  /**
   * @return Card $card
   */
  public static function getDrawnCard(Player $player)
  {
    return self::getTopOf(CARD_LOCATION_HAND);
  }
  
  public static function discard(Player $player, Card $card)
  {
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
  }
   
  ///////////////////////////////////////////////////////////////////////////////////////
   
  /** Creation of the cards
   * @param Collection $players
   */
  public static function setupNewGame($players, $options)
  {
    $cards = [];

    foreach (self::getSnowflakesCardsTypes() as $type => $card) {
      $cards[] = [
        'location' => CARD_LOCATION_DECK,
        'type' => $type,
        'nbr' => Cards::getNumberOfCards($type),
      ];
    } 

    self::create($cards);
    self::shuffle(CARD_LOCATION_DECK);

    //Pick 1 card TO BEGIN, 
    $card = Cards::pickOneForLocation(CARD_LOCATION_DECK, CARD_LOCATION_BOARD);
    $card->setRow(0);
    $card->setCol(0);
    $card->setDirection(CARD_DIRECTION_UP);
    
    //Prepare next step : 
    $card = Cards::pickOneForLocation(CARD_LOCATION_DECK, CARD_LOCATION_HAND);

  }
  
  /**
   * @return array of all the different types of Snowflakes Cards
   */
  public static function getSnowflakesCardsTypes(): array
  {
    $f = function ($t) {
      return [
        'snowflakes' => $t[0],
      ];
    };
    return [
      1 => $f([ [
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,  1, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,   1, 2),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,   2, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,  2, 2),
        ] ]), 
      2 => $f([ [
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,  1, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,   1, 2),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,   2, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,  2, 2),
        ] ]), 
      3 => $f([ [
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,  1, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,   1, 2),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,   2, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,  2, 2),
        ] ]), 
      4 => $f([ [
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,  1, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,   1, 2),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,   2, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,  2, 2),
        ] ]), 
      5 => $f([ [
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,  1, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,   1, 2),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,   2, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,  2, 2),
        ] ]), 
      6 => $f([ [
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,  1, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,   1, 2),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,   2, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,  2, 2),
        ] ]), 
      7 => $f([ [
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,  1, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,   1, 2),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,   2, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,  2, 2),
        ] ]),  
      8 => $f([ [
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,  1, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,   1, 2),
          new Snowflake(TOKEN_COUNTER_BLUE_DARK,   2, 1),
          new Snowflake(TOKEN_COUNTER_BLUE_LIGHT,  2, 2),
        ] ]), 
      
    
    ];
  }
  
  /**
   * @param int $type : type of card
   * @return int number of cards of this kind in the deck
   */
  public static function getNumberOfCards(int $type):int
  {
    switch($type){
      case 1: return 1;
      case 2: return 4;
      case 3: return 2;
      case 4: return 2;
      case 5: return 1;
      case 6: return 4;
      case 7: return 2;
      case 8: return 2;
    }
    return 0;
  }
}
