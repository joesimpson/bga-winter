<?php

namespace Bga\Games\winter\States;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\Games\winter\Exceptions\UnexpectedException;
use Bga\Games\winter\Game;
use Bga\Games\winter\Managers\Players;

/**
 * All about 'lakeChoice' game state 
 */
trait PlayerTurnLakeChoiceTrait
{
    /**
     * Game state arguments 
     *
     * @return array
     * @see ./states.inc.php
     */
    public function argLakeChoice(): array
    {
        $player = Players::getActive();
        $smallestLakes = [
            1 => [2868, 2880],
            2 => [2873, 2882],
        ]; //TODO JSA compute LAKES cards ids

        $args = [
            'lakes' => $smallestLakes,
                
            //AUTO SKIP STATE when no decision
            '_no_notify' => count($smallestLakes) < 2,
        ];
        
        $this->addArgsForUndo($args);
        return $args;
    }

    public function stLakeChoice(): void
    {
        $args = $this->argLakeChoice();
        if ($args['_no_notify']) {

            //TODO JSA MELT SMALLEST LAKE
            $this->gamestate->nextState('pass');
            return;
        }
    }
    /**
     * Player action in phase 2 : choose a lake to discard
     * 
     * @throws BgaUserException
     */
    public function actLake(int $lakeIndex, #[IntParam(name: 'v')] int $version,): void
    {
        Game::get()->checkVersion($version);
        self::trace("actPlaceCard($lakeIndex,)");

        $player = Players::getCurrent();
        $pId = $player->getId();
        $this->addStep();
        
        // check input values
        $args = $this->argLakeChoice();
        $lakes = $args['lakes'];
        if (!in_array($lakeIndex, array_keys($lakes))) {
            throw new UnexpectedException(105,"Invalid lake choice $lakeIndex");
        }
    
        //TODO JSA ACTION EFFECT : discard cards AND tokens

        // at the end of the action, move to the next state
        $this->gamestate->nextState("next");
    }
   
}
