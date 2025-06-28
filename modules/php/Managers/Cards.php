<?php

namespace Bga\Games\winter\Managers;

use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Game;
use Bga\Games\winter\Helpers\Collection;
use Bga\Games\winter\Helpers\Utils;
use Bga\Games\winter\Models\Card;
use Bga\Games\winter\Models\Player;

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
        'nbr' => $card['nbr'],
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
  public static function getSnowflakesCardsTypes()
  {
    $f = function ($t) {
      return [
        'nbr' => $t[0],
      ];
    };
    return [
      1 => $f([ 1, ]), 
      2 => $f([ 4, ]), 
      3 => $f([ 2, ]), 
      4 => $f([ 2, ]), 
      5 => $f([ 1, ]), 
      6 => $f([ 4, ]), 
      7 => $f([ 2, ]), 
      8 => $f([ 2, ]), 
      
    
    ];
  }
}
