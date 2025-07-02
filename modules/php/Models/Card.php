<?php

namespace Bga\Games\winter\Models;

use Bga\Games\winter\Helpers\Collection;
use Bga\Games\winter\Helpers\Utils;

/*
 * Card: all utility functions concerning a card
 */

class Card extends \Bga\Games\winter\Helpers\DB_Model
{
  protected $table = 'cards';
  protected $primary = 'card_id';
  protected $attributes = [
    'id' => ['card_id', 'int'],
    'state' => ['card_state', 'int'],
    'location' => 'card_location',
    'type' => ['type', 'int'],
    'row' => ['y', 'int'],
    'col' => ['x', 'int'],
  ];

  /**
   * Snowflakes distribution on the card
   */
  //protected $snowflakes;
  
  protected $staticAttributes = [
    /**
     * array of Snowflake : distribution printed on the card (not the tokens we put on cards)
     */
    'snowflakes',
  ];

   
  public function __construct($row, $datas)
  {
    parent::__construct($row);
    foreach ($datas as $attribute => $value) {
      $this->$attribute = $value;
    }
    //$this->$snowflakes = new Collection($datas['snowflakes']);
  }

  public function getUiData()
  {
    $data = parent::getUiData();
    $data["dir"] = $this->getDirection();
    return $data;
  } 
  

  public function setDirection(int $value){
    $this->setState($value);
  }
  /**
   * @return int
   */
  public function getDirection(){
    return $this->getState();
  }

  /**
   * @return array
   */
  public function coordArray()
  {
    $row = $this->getRow();
    $col = $this->getCol();
    if( !isset($row)) return null;
    if( !isset($col)) return null;
    return [$row, $col];
  } 

  /**
   * @return string
   */
  public function coordName()
  {
    return Utils::gridCoordName($this->getRow(), $this->getCol());
  } 

  /**
   * @return array of neighbouring coordinates (with played card or not)
   */
  public function getNeighbouringSpots()
  {
    $row = $this->getRow();
    $col = $this->getCol();
    if( !isset($row)) return [];
    if( !isset($col)) return [];
    $neighbours = [
      [$row -2, $col - 1],
      [$row -2, $col],
      [$row -2, $col + 1],
      [$row +2, $col - 1],
      [$row +2, $col],
      [$row +2, $col + 1],
      [$row -1 , $col -2],
      [$row -1 , $col +2],
      [$row , $col -2],
      [$row , $col +2],
      [$row +1 , $col -2],
      [$row +1 , $col +2],
    ];
    return $neighbours;
  } 

  /**
   * @return array list of fixed snowflakes, but ordered from TOP to bottom, and from LEft to right even whenthe card is rotated
   */
  public function getOrientedSnowflakes()
  {
    $snowflakes = $this->getSnowflakes();
    $dir = $this->getDirection();
    if ($dir == CARD_DIRECTION_DOWN){
      //Reverse order and coord
      $snowflakes = [
        new Snowflake($snowflakes[3]->type,1,1),
        new Snowflake($snowflakes[2]->type,1,2),
        new Snowflake($snowflakes[1]->type,2,1),
        new Snowflake($snowflakes[0]->type,2,2),
      ];
    }
    return $snowflakes;
  } 

}
