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
     * @return string
     */
    public static function gridCoordName(int $row, int $col)
    {
        if( !isset($row)) return "";
        if( !isset($col)) return "";
        return "[".$row.",".$col."]";
    } 

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
    public static function listPlayableSpotsForNewCard(): array
    {
        Game::get()->trace("listPlayableSpotsForNewCard()");
        $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);

        $usedCoordinates = $boardCards->map(function ($card) {
            return $card->coordArray();
        })->toArray();
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

        //SORT Array to help ui & help debug
        sort($lookingAtSpots);

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
    public static function gridIntersectionListFrom(int $row, int $col): array
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

    
    /**
     * 
     * RULE : Counters are always PLACED on top of a square made up of 4 snowflake sections of the same color.
     * 
     * @return array of available coordinates to place a token on the board
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
    public static function listPlayableSpotsForNewToken(int $color): array
    {
        Game::get()->trace("listPlayableSpotsForNewToken($color)");

        $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);

        $spots = [];
        //step 1 : convert cards coords to an (array) GRID of SNOWFLAKES coords
        $snowflakesGrid = [];
        foreach($boardCards as $card){
            $snowflakes = $card->getOrientedSnowflakes();
            foreach( $snowflakes as $snowflake){
                $snowflakeCoords = $snowflake->coordArrayFromBase($card->getRow(), $card->getCol());
                $snowflakeCoordsLabel = $snowflake->coordNameFromBase($card->getRow(), $card->getCol());

                $snowflakesGrid[$snowflakeCoordsLabel] = [ 
                   'coords' => $snowflakeCoords, 
                   'type' => $snowflake->type,
                ];
            }
        }
        //Sort array and keep associtive keys
        asort($snowflakesGrid);

        //step 2 : LOOK at SNOWFLAKES coords to find a square of 4 of the given color
        $squareBottomRightCorners = [];
        foreach($snowflakesGrid as $snowflakeDatas){
            $coords = $snowflakeDatas['coords'];

            //Question is "is this spot the bottom right corner of a 4-square ?"
            $targetSquare = [
                Utils::gridCoordName( $coords[0] -1, $coords[1]-1   ), 
                Utils::gridCoordName( $coords[0] -1, $coords[1]     ), 
                Utils::gridCoordName( $coords[0],    $coords[1]-1   ), 
                Utils::gridCoordName( $coords[0],    $coords[1]     ), //SAME CELL
            ];
            $isSquare = Utils::isSnowflakesSquare($color, $targetSquare, $snowflakesGrid);
            if($isSquare) $squareBottomRightCorners[] = $coords;
        }

        //step 3 : LOOP each found square to FILTER empty spots
        $existingTokensCoords = Tokens::getBoardTokens()->map(function ($token) {
            return $token->coordArray();
        })->toArray();
        foreach($squareBottomRightCorners as $squareBottomRightCorner){
            if(!in_array($squareBottomRightCorner,$existingTokensCoords)) $spots [] = $squareBottomRightCorner;
        }

        Game::get()->trace("listPlayableSpotsForNewToken($color) for snowflakesGrid ".json_encode($snowflakesGrid)." and squareBottomRightCorners ".json_encode($squareBottomRightCorners)." => result = ".json_encode($spots));
        return $spots;
    }

    /**
     *  Question is "is this spot the top left corner of a 4-square ?"
     * 
     * @param int $type token color
     * @param array $targetSquare
     * @param array $snowflakesGrid complete grid of snowflakes array datas
     * @return bool true if found, else false
     */
    public static function isSnowflakesSquare(int $type, array $targetSquare, array $snowflakesGrid)
    {
        Game::get()->trace("isSnowflakesSquare($type, ".json_encode($targetSquare));

        foreach($targetSquare as $targetSquareSpot){
            if(!array_key_exists($targetSquareSpot, $snowflakesGrid )) return false;
            $snowflakeDatas = $snowflakesGrid[$targetSquareSpot];
            if($type !== $snowflakeDatas['type']) return false;
        }

        Game::get()->trace("isSnowflakesSquare($type, ".json_encode($targetSquare).") => TRUE");
        return true;
    } 

    
    /**
    * @return Collection of Card
    */
    public static function listRemovableCardsOnBoard(): Collection
    {
        $list = new Collection();
        //TEST
        $list[2881] = Cards::get(2881);
        $list[2884] = Cards::get(2884);

        //TODO JSA Step 1 READ TOKENS on board
        //TODO JSA Step 2 : LOOP Tokens and save coordinate
        //TODO JSA step 3 : LOOP Cards on Board 
        //TODO JSA step 4 : filter cards with neighbouring tokens spots not from step 1

        return $list;
    }
}
