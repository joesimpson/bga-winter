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
            $intersectUnavailable = array_merge($intersectUnavailable,Utils::gridOverlappingCardsFrom($card->getRow(), $card->getCol()));
        }
        
        $lookingAtSpots = [];
        foreach($boardCards as $card){
            $newSpots = $card->getNeighbouringSpots();
            foreach($newSpots as $spot){
                if(!in_array($spot, $lookingAtSpots) && !in_array($spot, $usedCoordinates) && !in_array($spot, $intersectUnavailable)){
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
    * @return array of coordinates of card overlapping position [row, col] (because card size is 2 cols/2rows )
    */
    public static function gridOverlappingCardsFrom(int $row, int $col): array
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
     * @param int $row
     * @param int $col
    * @return array of coordinates of Token overlapping position [row, col] (because card size is 2 cols/2rows )
    */
    public static function gridOverlappingTokensFromCard(int $row, int $col): array
    {
        return [
            [$row  , $col ], // CARD cell = Token on Top left corner
            [$row  , $col +1],
            [$row  , $col +2],

            [$row +1 , $col ],
            [$row +1 , $col +1],
            [$row +1 , $col +2],

            [$row +2 , $col ],
            [$row +2 , $col +1],
            [$row +2 , $col +2], // Bottom right
            
        ];
    }

    /**
     * @param Collection $boardCards cards already read from DB
     * @param array $tempCardLocations (Optional) array of different cards location [card_id => [row,col, dir]] 
     * @return array of datas about Snowflakes on card where the array key is the coord label
     */
    public static function gridComputeSnowflakesGrid(Collection $boardCards, array $tempCardLocations = []): array
    {
        $snowflakesGrid = [];
        foreach($boardCards as $cardId => $card){
            $snowflakes = $card->getOrientedSnowflakes();
            $cardRow = $card->getRow();
            $cardCol = $card->getCol();
            if(array_key_exists($cardId, $tempCardLocations)){
                $cardRow = $tempCardLocations[$cardId]['row'];
                $cardCol = $tempCardLocations[$cardId]['col'];
                $snowflakes = $card->getOrientedSnowflakes( $tempCardLocations[$cardId]['dir']);
            }

            foreach( $snowflakes as $snowflake){
                $snowflakeCoords = $snowflake->coordArrayFromBase($cardRow, $cardCol);
                $snowflakeCoordsLabel = $snowflake->coordNameFromBase($cardRow, $cardCol);

                $snowflakesGrid[$snowflakeCoordsLabel] = [ 
                   'coords' => $snowflakeCoords, 
                   'type' => $snowflake->type,
                ];
            }
        }
        //Sort array and keep associtive keys
        asort($snowflakesGrid);
        return $snowflakesGrid;
    }
    /**
     * @param array $coords source coordinates
     * @return array of 4 coordinates [row,col]
     */
    public static function computeTargetSquareBottomRight(array $coords): array
    {
        //Question is "is this spot the bottom right corner of a 4-square ?"
        return [
            Utils::gridCoordName( $coords[0] -1, $coords[1]-1   ), 
            Utils::gridCoordName( $coords[0] -1, $coords[1]     ), 
            Utils::gridCoordName( $coords[0],    $coords[1]-1   ), 
            Utils::gridCoordName( $coords[0],    $coords[1]     ), //SAME CELL
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
        $snowflakesGrid = Utils::gridComputeSnowflakesGrid($boardCards); 

        //step 2 : LOOK at SNOWFLAKES coords to find a square of 4 of the given color
        $squareBottomRightCorners = [];
        foreach($snowflakesGrid as $snowflakeDatas){
            $coords = $snowflakeDatas['coords'];

            //Question is "is this spot the bottom right corner of a 4-square ?"
            $targetSquare = Utils::computeTargetSquareBottomRight($coords);
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
        //Game::get()->trace("isSnowflakesSquare($type, ".json_encode($targetSquare));

        foreach($targetSquare as $targetSquareSpot){
            if(!array_key_exists($targetSquareSpot, $snowflakesGrid )) return false;
            $snowflakeDatas = $snowflakesGrid[$targetSquareSpot];
            if($type !== $snowflakeDatas['type']) return false;
        }

        Game::get()->trace("isSnowflakesSquare($type, ".json_encode($targetSquare).") => TRUE");
        return true;
    } 

    /**
     * Find which cards can be removed from the game board
     * 
    * @return array of Card id
    */
    public static function listRemovableCardsOnBoard(): array
    {
        $list = [];
        //TEST
        //$list = new Collection();
        //$list->append(Cards::get(2881));
        //$list->append(Cards::get(2884));

        //Step 1 READ TOKENS on board
        $tokens = Tokens::getBoardTokens();
        //Step 2 : LOOP Tokens and save coordinate
        $existingTokensCoords = $tokens->map(function ($token) {
            return $token->coordArray();
        })->toArray();
        
        Game::get()->trace("listRemovableCardsOnBoard() for existingTokensCoords ".json_encode($existingTokensCoords));

        //step 3 : LOOP Cards on Board 
        $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
        foreach($boardCards as $card){
            $tokenOnCard = false;
            $row = $card->getRow();
            $col = $card->getCol();
            $intersectUnavailable = Utils::gridOverlappingTokensFromCard($row, $col);

            Game::get()->trace("listRemovableCardsOnBoard() -> intersectUnavailable ($row, $col) = ".json_encode($intersectUnavailable));

            //step 4 : filter cards with neighbouring tokens spots not from step 1
            //$tokensOnCard = array_diff($intersectUnavailable, $existingTokensCoords);
            //if(count($tokensOnCard) == 0){
            //    $list->append($card);
            //}
            foreach($intersectUnavailable as $coord){
                if(in_array($coord,$existingTokensCoords)){
                    $tokenOnCard = true;
                }
            }
            if(!$tokenOnCard){
                $list[] = $card->getId();
            }
        }
        Game::get()->trace("listRemovableCardsOnBoard() -> result ".json_encode($list));

        return $list;
    }
    
    /**
     * Find which cards can be moved from the game board : it is a subset of removable cards
     * 
     * @param int $token_color : Color of token to place after move
     * @param int $availableTokens : number of available tokens to place after move
     * @param array $removableCardsIds : cards ids we can remove
     * 
    * @return array of destinations for cards : [card_id1 => [[row,col,dirs], [row,col,dirs], [row,col,dirs]], card_id2 => [[row,col,dirs], ], ... ]
    */
    public static function listMovableCardsOnBoard(int $token_color, int $availableTokens, array $removableCardsIds): array
    {
        $list = [];

        //Step 1 look at targets for card
        $spotsForCard = Utils::listPlayableSpotsForNewCard();

        //step 2 : LOOP Cards 
        $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
        $removableCards = Cards::getMany($removableCardsIds);
        foreach($removableCards as $card){ 
            //STEP 3 : can we move the card to a place where we can add this color token(s) ?
            //TODO JSA check we can slide the card a little bit to +1/-1 row/col in spotsForCard
            
            $targetsForCard = [];

            foreach($spotsForCard as $coord){ 
                //Check ALL possible DIRS
                $allDirs = [CARD_DIRECTION_UP, CARD_DIRECTION_DOWN];
                $playableDirs = [];

                $cardRow = $coord[0];
                $cardCol = $coord[1];

                foreach( $allDirs as $dir){
                    $isSquare = false;

                    $tempCardLocations = [
                        $card->getId() => [
                            'row' => $cardRow, 
                            'col' => $cardCol, 
                            'dir' => $dir,
                        ]];
                    $snowflakesGrid = Utils::gridComputeSnowflakesGrid($boardCards, $tempCardLocations); 

                    $snowflakes = $card->getOrientedSnowflakes($dir);
                    foreach( $snowflakes as $snowflake){
                        $snowflakeCoords = $snowflake->coordArrayFromBase($cardRow, $cardCol);
                        $targetSquare = Utils::computeTargetSquareBottomRight($snowflakeCoords);
                        $isSquare = $isSquare || Utils::isSnowflakesSquare($token_color, $targetSquare, $snowflakesGrid);
                        if($isSquare) break;//Don't compute all squares for now
                    }

                    //TODO JSA REMOVE TEST
                    //if(CARD_DIRECTION_DOWN == $dir) $isSquare = true;
                    
                    if($isSquare) $playableDirs [] = $dir;
                }
                $targetForCard = [ 'row' => $cardRow, 'col' => $cardCol, 'dirs' => $playableDirs ];
                if(count($playableDirs)>0 && !in_array($targetForCard,$targetsForCard)) $targetsForCard[] = $targetForCard;

            }

            if(count($targetsForCard)>0) $list[ $card->getId()] = $targetsForCard;
        }
        Game::get()->trace("listMovableCardsOnBoard($token_color,$availableTokens) -> result ".json_encode($list));

        return $list;
    }
}
