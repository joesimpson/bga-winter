<?php

namespace Bga\Games\winter\Models;

use Bga\Games\winter\Helpers\Utils;
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
  
  /**
   * @return string coordinates of the snowflake on the board map
   */
  public function coordNameFromBase(int $cardRow, int $cardCol)
  {
    $coords = $this->coordArrayFromBase($cardRow, $cardCol);
    return Utils::gridCoordName($coords[0], $coords[1]);
  } 

  /**
   * @return array [row,col] coordinates of the snowflake on the board map
   */
  public function coordArrayFromBase(int $cardRow, int $cardCol)
  {
    return [$cardRow-1 + $this->row, $cardCol-1 + $this->col];
  } 
}
