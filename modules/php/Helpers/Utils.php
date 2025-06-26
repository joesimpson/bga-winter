<?php
namespace Bga\Games\winter\Helpers;

use Bga\Games\winter\Game;

abstract class Utils extends \APP_DbObject
{
    public static function filter(&$data, $filter)
    {
        $data = array_values(array_filter($data, $filter));
    }

    /**
     * @param int $num1 
     * @param int $num2
     * @return int
     */
    public static function positive_modulo($num1,$num2)
    {
        $r = $num1 % $num2;
        if ($r < 0)
        {
            $r += abs($num2);
        }
        return $r;
    }
    
    public static function array_of_uniquearrays(array $array): array {
        return array_intersect_key($array, array_unique(array_map('serialize', $array)));
    }

    ////////////////////////////////////////////////////////////////
    //////// GAME SPECIFIC
    ////////////////////////////////////////////////////////////////
 
}
