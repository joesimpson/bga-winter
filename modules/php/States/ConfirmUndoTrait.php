<?php

namespace Bga\Games\winter\States;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Exceptions\UnexpectedException;
use Bga\Games\winter\Game;
use Bga\Games\winter\Helpers\Log;
use Bga\Games\winter\Managers\Players;

trait ConfirmUndoTrait
{
    /**
     * Add a NOT undoable step in Log module
     * @param int $state
     */
    public function addCheckpoint($state)
    {
        Globals::setChoices(0);
        Log::checkpoint($state);
    }

    /**
     * Add an undoable step in Log module
     */
    public function addStep()
    {
        $stepId = Log::step($this->gamestate->state_id());
        Globals::incChoices();
    }

    public function argsConfirmTurn()
    {
        $activePlayer = Players::getActive();
        $data = [];
        $this->addArgsForUndo($data);
        return $data;
    }
    function addArgsForUndo(&$args)
    {
        $args['previousSteps'] = Log::getUndoableSteps();
        $args['previousChoices'] = Globals::getChoices();
    }

    public function stConfirmTurn()
    {
        $player = Players::getActive();
        $phase = Globals::getPhase();
        $prefConfirm = $player->getPref(PREF_CONFIRM);
        if (Globals::getChoices() == 0 
            || $prefConfirm == PREF_CONFIRM_DISABLED 
            || $prefConfirm == PREF_CONFIRM_ENABLED_START && PHASE_BEGINNING != $phase 
        ) {//AUTO CONFIRM
            $this->actConfirmTurn(0,true);
        }
    }

    public function actConfirmTurn(#[IntParam(name: 'v')] int $version, bool $auto = false)
    {
        if (!$auto) {
            Game::get()->checkVersion($version);
            //self::checkAction('actConfirmTurn');
        }

        $player = Players::getActive();
        $pId = $player->getId(); 

        $phase = Globals::getPhase();
        if( PHASE_BEGINNING == $phase){
            $this->gamestate->nextState('confirmStart');
            return;
        }
        
        $this->gamestate->nextState('confirm');
    }


    public function actRestart(#[IntParam(name: 'v')] int $version)
    {
        Game::get()->checkVersion($version);
        $player = Players::getActive();
        $pId = $player->getId();
        if (Globals::getChoices($pId) < 1) {
            throw new UnexpectedException(404,'No choice to undo. You may need to reload the page.');
        }
        Log::undoTurn();
        Notifications::restartTurn($player);
    }

    public function actUndoToStep(int $stepId, #[IntParam(name: 'v')] int $version): void
    {
        Game::get()->checkVersion($version);
        $player = Players::getActive();
        $pId = $player->getId();
        $steps = Log::getUndoableSteps($pId);
        if(!in_array($stepId,$steps)){
            throw new UnexpectedException(404,'This step is not undoable anymore. You may need to reload the page.');
        }
        Log::undoToStep($stepId);
        Notifications::undoStep($player, $stepId);
    }
}
