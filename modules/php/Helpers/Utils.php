<?php
namespace Bga\Games\winter\Helpers;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Stats;
use Bga\Games\winter\Game;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Tokens;
use Bga\Games\winter\Models\Card;
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
        Stats::set("playedColor",$player,$color);

        $tokensOfThatColor = Tokens::DB()->where('type', $color)->get();
        foreach($tokensOfThatColor as $tokenOfThatColor){
            $tokenOfThatColor->setPId($player->getId());
            $tokenOfThatColor->setLocation(TOKEN_LOCATION_HAND);
        }
        Notifications::assignTokens($player,$tokensOfThatColor);
    }

    /**
     * 
     * @param array $flyingCards (Optional) : list of cards ids we don't consider as placed on the table
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
    public static function listPlayableSpotsForNewCard( 
        Collection $boardCards, ?array $flyingCards = null 
    ): array
    {
        //Game::get()->trace("listPlayableSpotsForNewCard()");

        $usedCoordinates = $boardCards->filter(function ($card) {
            return !isset($flyingCards) || !in_array($card->getId(),$flyingCards);
        })->map(function ($card) {
            return $card->coordArray();
        })->toArray();
        $intersectUnavailable = [];
        foreach($boardCards as $card){
            if(isset($flyingCards) && in_array($card->getId(),$flyingCards)) continue;
            $intersectUnavailable = array_merge($intersectUnavailable,Utils::gridOverlappingCardsFrom($card->getRow(), $card->getCol()));
        }
        
        $lookingAtSpots = [];
        foreach($boardCards as $card){
            if(isset($flyingCards) && in_array($card->getId(),$flyingCards)) continue;
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
        //Game::get()->trace("listPlayableSpotsForNewCard() for used ".json_encode($usedCoordinates)." and intersectUnavailable ".json_encode($intersectUnavailable)." result = ".json_encode($lookingAtSpots));
        //Game::get()->trace("listPlayableSpotsForNewCard() =>".json_encode($lookingAtSpots));
        
        return $lookingAtSpots;
    }

    
    /**
     * 
     * @return array of available coordinates to place a card on the board + add tokens
     * 
     * Example : 
     * 
     *  [
     *      [ 'row' => 1, 'col' => 2, 'dirs' => [1, 2] ],
     *      [ 'row' => 0, 'col' => 3, 'dirs' => [1,] ],
     *  ]
     */
    public static function listPlayableSpotsForNewCardAndTokens(
        Card $card, 
        int $token_color, 
        Collection $boardCards, Collection $boardTokens,
    ): array
    {
        $cardId = $card->getId();
        //Game::get()->trace("listPlayableSpotsForNewCardAndTokens($cardId,$token_color)");
        $targetsForCard = [];
        $spotsForCard = Utils::listPlayableSpotsForNewCard($boardCards,[$cardId]);

        $cardCoordBeforeMove = Globals::getBeforeMoveRowCol();
        foreach($spotsForCard as $coord){ 
            //Check ALL possible DIRS
            $allDirs = [CARD_DIRECTION_UP, CARD_DIRECTION_DOWN];
            $playableDirs = [];

            //We cannot move a card to its previous place
            if(isset($cardCoordBeforeMove) && $coord == $cardCoordBeforeMove) continue;

            $cardRow = $coord[0];
            $cardCol = $coord[1];

            foreach( $allDirs as $dir){
                $isSquare = Utils::isMovableCard($card,$cardRow, $cardCol, $dir,$token_color,$boardCards, $boardTokens);
                if($isSquare) $playableDirs [] = $dir;
            }
            $targetForCard = [ 'row' => $cardRow, 'col' => $cardCol, 'dirs' => $playableDirs ];
            if(count($playableDirs)>0 && !in_array($targetForCard,$targetsForCard)) $targetsForCard[] = $targetForCard;

        }

        //Game::get()->trace("listPlayableSpotsForNewCardAndTokens($cardId,$token_color) => ".json_encode($targetsForCard));
        return $targetsForCard;
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
        $addSnowflakesInGrid = function ( &$snowflakesGrid,$snowflakes,$cardRow, $cardCol) {
            foreach( $snowflakes as $snowflake){
                $snowflakeCoords = $snowflake->coordArrayFromBase($cardRow, $cardCol);
                $snowflakeCoordsLabel = $snowflake->coordNameFromBase($cardRow, $cardCol);

                $snowflakesGrid[$snowflakeCoordsLabel] = [ 
                    'coords' => $snowflakeCoords, 
                    'type' => $snowflake->type,
                ];
            };
        };
        foreach($boardCards as $cardId => $card){
            $snowflakes = $card->getOrientedSnowflakes();
            $cardRow = $card->getRow();
            $cardCol = $card->getCol();
            if(array_key_exists($cardId, $tempCardLocations)){
                $cardRow = $tempCardLocations[$cardId]['row'];
                $cardCol = $tempCardLocations[$cardId]['col'];
                $snowflakes = $card->getOrientedSnowflakes( $tempCardLocations[$cardId]['dir']);
            }
            $addSnowflakesInGrid($snowflakesGrid,$snowflakes,$cardRow, $cardCol);

        }
        //Look at cards which are no more on board :
        foreach($tempCardLocations as $cardId => $tempCardDatas){
            if(!in_array($cardId, $boardCards->getIds())){
                $card = $tempCardDatas['o'];
                $cardRow = $tempCardDatas['row'];
                $cardCol = $tempCardDatas['col'];
                $snowflakes = $card->getOrientedSnowflakes( $tempCardDatas['dir']);
                //Notifications::message("SEARCH $cardId not in : ".json_encode($boardCards->getIds()),['json' => $boardCards->getIds(), 'sn'=> $snowflakes]);
            }
            $addSnowflakesInGrid($snowflakesGrid,$snowflakes,$cardRow, $cardCol);
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
        //Game::get()->trace("listPlayableSpotsForNewToken($color)");

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

        //Game::get()->trace("listPlayableSpotsForNewToken($color) for snowflakesGrid ".json_encode($snowflakesGrid)." and squareBottomRightCorners ".json_encode($squareBottomRightCorners)." => result = ".json_encode($spots));
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

        //Game::get()->trace("isSnowflakesSquare($type, ".json_encode($targetSquare).") => TRUE");
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
        
        //Game::get()->trace("listRemovableCardsOnBoard() for existingTokensCoords ".json_encode($existingTokensCoords));

        //step 3 : LOOP Cards on Board 
        $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
        foreach($boardCards as $card){
            $tokenOnCard = false;
            $row = $card->getRow();
            $col = $card->getCol();
            $intersectUnavailable = Utils::gridOverlappingTokensFromCard($row, $col);

            //Game::get()->trace("listRemovableCardsOnBoard() -> intersectUnavailable ($row, $col) = ".json_encode($intersectUnavailable));

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
        //Game::get()->trace("listRemovableCardsOnBoard() -> result ".json_encode($list));

        return $list;
    }

    /**
     * @param Card $card
     * @param int $row
     * @param int $col
     * @param int $dir
     * @param int $token_color
     * @param Collection $boardCards read Card list in DB
     * @param Collection $boardTokens read Token list in DB
     * 
     * @return array list only if this destination is valid for that card 
     */
    public static function listMovableCardNewTokens(Card $card, int $row, int $col, int $dir,
        int $token_color,
        Collection $boardCards,
        Collection $boardTokens,
        ): array
    {
        $squareSpots = [];

        $existingTokensCoords = $boardTokens->map(function ($token) {
          return $token->coordArray();
        })->toArray();

        $tokensSpots = Utils::gridOverlappingTokensFromCard($row, $col);
        $tempCardLocations = [
            $card->getId() => [
                'row' => $row, 
                'col' => $col, 
                'dir' => $dir,
                'o' => $card,
            ]];
        $snowflakesGrid = Utils::gridComputeSnowflakesGrid($boardCards, $tempCardLocations); 

        foreach( $tokensSpots as $spot){
            $targetSquare = Utils::computeTargetSquareBottomRight($spot);
            $isSquare = Utils::isSnowflakesSquare($token_color, $targetSquare, $snowflakesGrid);
            if($isSquare && !in_array($spot,$existingTokensCoords)){
                $squareSpots[] = $spot;
            }
        }
        return $squareSpots;
    }

    /**
     * @param Card $card
     * @param int $row
     * @param int $col
     * @param int $dir
     * @param int $token_color
     * @param Collection $boardCards read Card list in DB
     * @param Collection $boardTokens read Token list in DB
     * 
     * @return bool true only if this destination is valid for that card moved by this player
     */
    public static function isMovableCard(Card $card, int $row, int $col, int $dir,
        int $token_color,
        Collection $boardCards,
        Collection $boardTokens,
        ): bool
    { 
        $squareSpots = Utils::listMovableCardNewTokens( $card, $row, $col, $dir,$token_color, $boardCards, $boardTokens);
        $isMovable = (count($squareSpots) >0);
        //Game::get()->trace("isMovableCard($cardId, $row, $col, $dir,$token_color) -> result ".json_encode($isMovable));

        return $isMovable;
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

        if($availableTokens <1) return $list;

        //Step 1 look at targets for card
        //No, it depends on selected card because a card can slide a little bit to +1/-1 row/col
        //$spotsForCard = Utils::listPlayableSpotsForNewCard();

        //step 2 : LOOP Cards 
        $boardTokens = Tokens::getBoardTokens();
        $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
        $removableCards = Cards::getMany($removableCardsIds);
        foreach($removableCards as $card){ 
            
            $targetsForCard = [];

            //STEP 2.5 : Does moving imply dividing the board into 2 lakes ?
            $lakesAroundCard = Utils::listBoardLakesAroundCard($boardCards, $card->getId());
            //$list[ $card->getId()]['split'] = Utils::isCardBetween2Lakes($boardCards, $card);
            $list[ $card->getId()]['split'] = (count($lakesAroundCard) > 1);

            //STEP 3 : can we move the card to a place where we can add this color token(s) ?
            if($list[ $card->getId()]['split']){
                //Step 3.2 : check each future lake to find a new spot if those cards are removed
                $biggestLakes = Utils::filterBiggestLakes($boardCards, $lakesAroundCard);
                //Notifications::message("lakes from  ".$card->getId()." ->".json_encode($lakesAroundCard),['json' => $lakesAroundCard]);
                foreach($lakesAroundCard as $lakeid => $lakeAroundCard){
                    if(1 == count($biggestLakes) && $lakeid == array_keys($biggestLakes) [0]) {
                        //don't ignore a solo big lake
                        continue;
                    }
                    $boardCardsFiltered = $boardCards->filter(function($card) use($lakeAroundCard) { return !in_array($card->getId(),$lakeAroundCard);});
                    $targetsForCardWithoutLake = Utils::listPlayableSpotsForNewCardAndTokens($card, $token_color, $boardCardsFiltered, $boardTokens);
                    $targetsForCard = array_merge($targetsForCard, $targetsForCardWithoutLake);
                    //Notifications::message("targetsForCardWithoutLake : ".$card->getId()." ->".json_encode($targetsForCardWithoutLake),['json' => $targetsForCardWithoutLake]);
                }
                //Notifications::message("targetsForCard : ".$card->getId()." ->".json_encode($targetsForCard),['json' => $targetsForCard]);
            }
            else {
                //Notifications::message("CARD DOesn'T SPLIT : ".json_encode($card->getId()),['json' => $card->getId()]);
                $targetsForCard = Utils::listPlayableSpotsForNewCardAndTokens($card, $token_color, $boardCards, $boardTokens);
            }

            if(count($targetsForCard)>0) $list[ $card->getId()]['targets'] = $targetsForCard;
            else unset($list[ $card->getId()]);
        }
        //Game::get()->trace("listMovableCardsOnBoard($token_color,$availableTokens) -> result ".json_encode($list));

        return $list;
    }

    /**
     * 
     * @param Collection $boardCards : cards placed on Board (already read from DB)
     * @param Card $card : the card to analyze
     * 
     * @return bool true if the specified card is placed on a "bridge" between 2 lakes, false otherwise
     */
    public static function isCardBetween2Lakes(
        Collection $boardCards,
        Card $card,
        ): bool
    { 
        
        $lakes = Utils::listBoardLakesAroundCard($boardCards, $card->getId());
        return count($lakes) > 1;
    }

    
    /**
     * @param Collection $boardCards : cards placed on Board (already read from DB)
     * 
     * @return array of array of cards ids grouped by lake 
     * 
     * Example :
     *  [ 
     *      1=> [ 10,11,12,13,],
     *      2=> [ 20,21,22,23,24],
     *      3=> [ 75,84],
     *      4=> [ 100],
     *  ]
     * 
     */
    public static function listBoardLakes(
        Collection $boardCards,
        ): array
    { 
        $usedCoordinates = $boardCards->map(function ($c) {
            return $c->coordArray();
        })->toArray();

        $maxMoves = count($usedCoordinates);
        $moveCostCallback = function ($source, $target, $d) use ($usedCoordinates) {
            $spot = [$target['y'], $target['x']];
            if(!in_array($spot, $usedCoordinates)) return 10000;//not valid position: we cannot move through empty spot
            return 1;
        };

        $firstCard = $boardCards->first();
        $lake1 = [$firstCard->getId()];
        $lake2 = [];
        foreach($boardCards as $card){
            if($card->getId() == $firstCard->getId() ) continue;

            $startingCell = [ 'x' => $card->getCol(), 'y' => $card->getRow(), ];
            $cellsMarkers = GridUtils::getReachableCellsAtDistance($startingCell,$maxMoves, $moveCostCallback, $usedCoordinates);
            $cells = $cellsMarkers[0];
            $reacheableCellInLake1 = GridUtils::searchCell($cells, $firstCard->getCol(), $firstCard->getRow());
            if ($reacheableCellInLake1 === false) {
                $lake2[] = $card->getId();
            }
            else {
                $lake1[] = $card->getId();
            }
        }

        $lakes = [1 => $lake1];
        if(count($lake2) > 0){
            //$lakes[2] = $lake2;
            //!WRONG : we could have 4 (or more ?) lakes if we remove a centered card
            //==> recursive call to get more details on this 'lake2'
            $cardsOnOtherLakes = $boardCards->filter(function ($c) use ($lake2) {
                return in_array($c->getId(),$lake2);
            });
            $otherLakes = self::listBoardLakes($cardsOnOtherLakes);
            if(count($otherLakes) >= 1){
                //$lakes = array_merge($lakes, $otherLakes);
                foreach($otherLakes as $otherLake) $lakes[] = $otherLake;
            }
        }
        return $lakes;
    }

    
    /**
     * @param Collection $boardCards : cards placed on Board (already read from DB)
     * @param int $cardId : a card we could remove to create a separation between lakes
     * 
     * @return array of array of cards ids grouped by lake 
     * 
     * Example :
     *  [ 
     *      1=> [ 10,11,12,13,],
     *      2=> [ 20,21,22,23,24],
     *      3=> [ 75,84],
     *      4=> [ 100],
     *  ]
     * 
     */
    public static function listBoardLakesAroundCard(
        Collection $boardCards,
        int $cardId,
        ): array
    { 
        $boardCardsFiltered = $boardCards->filter(function($card) use($cardId) { return $cardId != $card->getId();});
        return Utils::listBoardLakes($boardCardsFiltered);
    }

    /**
     * @return array only the biggest lakes 
     */
    public static function filterBiggestLakes(
        Collection $boardCards,
        array $lakes,
    ): array
    { 
        $lakes = Utils::listBoardLakes($boardCards);
        $biggestLakes = [];
        $maxSize = 0;
        foreach($lakes as $lakeId => $lake){
            if(count($lake) > $maxSize){
                $maxSize = count($lake);
                $biggestLakes[$lakeId] = $lake;
            }
            else if(count($lake) == $maxSize){
                $biggestLakes[$lakeId] = $lake;
            }
        }
        return $biggestLakes;
    }
}
