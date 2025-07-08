<?php
namespace Bga\Games\winter\Helpers;

abstract class GridUtils
{ 
    
    /**
     * @param array $currentBoardCoords
     * 
     * @return array  [ 'ROW_MAX'=> $maxRow,'ROW_MIN' => $minRow, 'COLUMN_MAX'  => $maxCol,'COLUMN_MIN'=> $minCol,]
     */
    public static function getBoardCoordEdges(array $currentBoardCoords): array
    {
        $maxRow = null;
        $minRow = null;
        $maxCol = null;
        $minCol = null;
        foreach($currentBoardCoords as $coord){
            $maxRow = isset($maxRow) ? max($maxRow, $coord[0]) : $coord[0];
            $minRow = isset($minRow) ? min($minRow, $coord[0]) : $coord[0];
            $maxCol = isset($maxCol) ? max($maxCol, $coord[1]) : $coord[1];
            $minCol = isset($minCol) ? min($minCol, $coord[1]) : $coord[1];
        }

        return [
            'ROW_MAX'       => $maxRow,
            'ROW_MIN'       => $minRow,
            'COLUMN_MAX'    => $maxCol,
            'COLUMN_MIN'    => $minCol,
        ];
    }
    /**
     * Init grid to be used with path algorythm
     */
    public static function createGrid(array $currentBoardCoords, $defaultValue = null)
    {
        $g = [];
        
        $edges = GridUtils::getBoardCoordEdges($currentBoardCoords);
        $maxRow = $edges['ROW_MAX'];
        $minRow = $edges['ROW_MIN'];
        $maxCol = $edges['COLUMN_MAX'];
        $minCol = $edges['COLUMN_MIN']; 

        for ($y = $minRow; $y <= $maxRow; $y++) {
            for ($x = $minCol; $x <= $maxCol; $x++) {
                $g[$x][$y] = $defaultValue;
            }
        }
        return $g;
    }
    
    /**
     * @param int $row
     * @param int $column
     * @param array $currentBoardCoords
     * @return bool
     */
    public static function isCoordOutOfGrid($row, $column,array $currentBoardCoords)
    {
        $edges = self::getBoardCoordEdges($currentBoardCoords);

        if($column > $edges['COLUMN_MAX']) return true;
        if($column < $edges['COLUMN_MIN']) return true;
        if($row > $edges['ROW_MAX']) return true;
        if($row < $edges['ROW_MIN']) return true;

        return false;
    }
    protected static function isValidCell($cell,array $currentBoardCoords)
    {
        return !GridUtils::isCoordOutOfGrid($cell['y'],$cell['x'],$currentBoardCoords);
    }
    /**
     * @return array $cells : list of orthogonal neighbours to be used with path algorythm
     */
    public static function getNeighbours(array $cell,array $currentBoardCoords): array
    {
        //See getNeighbouringSpots
        $directions = [
            ['x' => -2, 'y' => -1],
            ['x' => -2, 'y' => 0],
            ['x' => -2, 'y' => +1],

            ['x' => +2, 'y' => -1],
            ['x' => +2, 'y' => 0],
            ['x' => +2, 'y' => +1],
            
            ['x' => -1,  'y' => -2],
            ['x' => -1,  'y' => +2],

            ['x' => 0,  'y' => -2],
            ['x' => 0,  'y' => +2],
            
            ['x' => +1,  'y' => -2],
            ['x' => +1,  'y' => +2],
        ];

        $cells = [];
        foreach ($directions as $dir) {
            $newCell = [
                'x' => $cell['x'] + $dir['x'],
                'y' => $cell['y'] + $dir['y'],
            ];
            if (self::isValidCell($newCell,$currentBoardCoords)) {
                $cells[] = $newCell;
            }
        }
        return $cells;
    }
    /**
     * getReachableCellsAtDistance: perform a Disjktra shortest path finding :
     * @param array $startingCell : starting pos
     * @param int $d : max distance we are looking for
     * @param function $costCallback : function used to compute cost
     * @param array $currentBoardCoords : all the coordinates used by Cards on board
     * 
     * @return array [$cells, $markers] where $cells are the reachable cells
     * 
     * //Taken from bga-memoir project used to get units movements range
     */
    public static function getReachableCellsAtDistance(
        array $startingCell,
        int $d,
        $costCallback,
        array $currentBoardCoords,
    ) : array
    {
        $queue = new \SplPriorityQueue();
        $queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        $queue->insert(['cell' => $startingCell], 0);
        $markers = self::createGrid($currentBoardCoords, false);

        while (!$queue->isEmpty()) {
            // Extract the top node and adds it to the result
            $node = $queue->extract();
            $cell = $node['data']['cell'];
            $cell['d'] = -$node['priority'];
            $pos = ['x' => $cell['x'], 'y' => $cell['y']];
            $mark = $markers[$pos['x']][$pos['y']];
            if ($mark !== false) {
                continue;
            }
            $markers[$pos['x']][$pos['y']] = $cell;

            // Look at neighbours
            $neighbours = self::getNeighbours($pos,$currentBoardCoords);
            foreach ($neighbours as $nextCell) {
                $cost = $costCallback($cell, $nextCell, $d);
                $dist = $cell['d'] + $cost;
                $t = $markers[$nextCell['x']][$nextCell['y']];
                if ($t !== false) {
                    continue;
                }

                if ($dist <= $d) {
                    //$nextCell['cost'] = $cost;
                    $queue->insert(['cell' => $nextCell], -$dist);
                }
            }
        }

        // Extract the reachable cells
        $cells = [];
        foreach ($markers as $col) {
            foreach ($col as $cell) {
                if ($cell !== false && $cell['d'] > 0) {
                    $cells[] = $cell;
                }
            }
        }

        return [$cells, $markers];
    }
}
