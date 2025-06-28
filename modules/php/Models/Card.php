<?php

namespace Bga\Games\winter\Models;

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
   
  public function __construct($row, $datas)
  {
    parent::__construct($row);
    foreach ($datas as $attribute => $value) {
      $this->$attribute = $value;
    }
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
   * @return string
   */
  public function coordName()
  {
    $row = $this->getRow();
    $col = $this->getCol();
    if( !isset($row)) return "";
    if( !isset($col)) return "";
    return "[".$row.",".$col."]";
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

}
