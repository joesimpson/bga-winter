<?php

namespace Bga\Games\winter\Models;

use Bga\Games\winter\Helpers\Utils;

/*
 * Token: all utility functions concerning a Token
 */

class Token extends \Bga\Games\winter\Helpers\DB_Model
{
  protected $table = 'tokens';
  protected $primary = 'token_id';
  protected $attributes = [
    'id' => ['token_id', 'int'],
    'state' => ['token_state', 'int'],
    'location' => 'token_location',
    'pId' => ['player_id', 'int'],
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
    $data['pos'] = $this->getPosition();
    return $data;
  }

  
  public function setPosition($value){
    $this->setState($value);
  }
  public function getPosition(){
    return $this->getState();
  }

  
  /**
   * @return array [row, col]
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

}
