<?php

namespace Bga\Games\winter\Models;

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

}
