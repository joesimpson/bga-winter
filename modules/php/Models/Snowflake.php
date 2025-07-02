<?php

namespace Bga\Games\winter\Models;

use Bga\Games\winter\Managers\Tokens;

/*
 * Snowflake: all utility functions concerning a snowflake coordinate on a card
 */

class Snowflake implements \JsonSerializable
{

  /**
   * Token type
   */
  public int $type;
  public int $row;
  public int $col;

  /**
   * @param int $type
   * @param int $row
   * @param int $column
   */
  public function __construct($type,$row,$column )
  {
    $this->type = $type;
    $this->col = $column;
    $this->row = $row;
  }
  
  /**
   */
  public function getUiData()
  {
    $data = $this->jsonSerialize();
    return $data;
  }
  /**
   * Return an array of attributes
   */
  public function jsonSerialize(): mixed
  {
    $data = [];
    $data['type'] = $this->type;
    $data['row'] = $this->row;
    $data['col'] = $this->col;
    $data['color'] = Tokens::getColorName($this->type);

    return $data;
  }
}
