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
    'row' => ['x', 'int'],
    'col' => ['y', 'int'],
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
    //$data['coord'] = $this->getCoordinates();
    return $data;
  }

  // public function setCoordinates(int $row, int $column){
  //   //NOOOOOOOOOOOOOO STATE INT => use LOCATION
  //   //$this->setState($row*COORD_ROW_MULT + $column);
  // }
  // public function getCoordinates(){
  //   $state = $this->getState();
  //   if(isset($state)) {
  //     $row = 0;
  //     $col = 0;
  //     return ['row' =>$row, 'col' =>$col];
  //   }
  //   return null;
  // }
}
