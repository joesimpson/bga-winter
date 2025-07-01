<?php
namespace Bga\Games\winter\Helpers;

use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Game;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Tokens;
use Bga\Games\winter\Models\Player;

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
    /**
     * @param Player $player
     * @param int $color
     */
    public static function assignPlayerColor(Player $player,int $color)
    {
        $player_color = array_search($color,PLAYER_COLORS);
        $player->setColor($player_color);
        Game::get()->reloadPlayersBasicInfos();
        Notifications::newPlayerColor($player,$color);

        $tokensOfThatColor = Tokens::DB()->where('type', $color)->get();
        foreach($tokensOfThatColor as $tokenOfThatColor){
            $tokenOfThatColor->setPId($player->getId());
            $tokenOfThatColor->setLocation(TOKEN_LOCATION_HAND);
        }
        Notifications::assignTokens($player,$tokensOfThatColor);
    }

    /**
     * @return array of available coordinates to place a card on the board
     * 
     * Example : 
     * 
     *  [
     *      [ 1,2 ],
     *      [ 0,3 ],
     *      [ 0,4 ],
     *      [ -2,-1 ],
     *  ]
     */
    public static function listPlayableSpotsForNewCard()
    {
        Game::get()->trace("listPlayableSpotsForNewCard()");
        $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);

        $usedCoordinates = $boardCards->map(function ($card) {
            return $card->coordArray();
        })->toArray();
        //$intersectUnavailable = $boardCards->map(function ($card) {
        //    return Utils::gridIntersectionListFrom($card->getRow(), $card->getCol());
        //})->toArray();
        $intersectUnavailable = [];
        foreach($boardCards as $card){
            $intersectUnavailable = array_merge($intersectUnavailable,Utils::gridIntersectionListFrom($card->getRow(), $card->getCol()));
        }
        
        $lookingAtSpots = [];
        foreach($boardCards as $card){
            $newSpots = $card->getNeighbouringSpots();
            foreach($newSpots as $spot){
                if(!in_array($spot, $usedCoordinates) && !in_array($spot, $intersectUnavailable)){
                    $lookingAtSpots[] = $spot;
                }
            }
        }

        //$possible = array_diff($lookingAtSpots, $usedCoordinates);
        Game::get()->trace("listPlayableSpotsForNewCard() for used ".json_encode($usedCoordinates)." and intersectUnavailable ".json_encode($intersectUnavailable)." result = ".json_encode($lookingAtSpots));
        //Game::get()->trace("listPlayableSpotsForNewCard() =>".json_encode($lookingAtSpots));
        
        return $lookingAtSpots;
    }
    
    /**
     * @param int $row
     * @param int $col
    * @return array of coordinates of card overlaping position [row, col] (because card size is 2 cols/2rows )
    */
    public static function gridIntersectionListFrom(int $row, int $col)
    {
        return [
            [$row -1 , $col -1],
            [$row -1 , $col ],
            [$row -1 , $col +1],
            
            [$row  , $col -1],
            //[$row  , $col ], // SAME cell
            [$row  , $col +1],

            [$row +1 , $col -1],
            [$row +1 , $col ],
            [$row +1 , $col +1],
        ];
    }
}
