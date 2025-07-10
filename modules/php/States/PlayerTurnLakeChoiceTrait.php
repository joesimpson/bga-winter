<?php

namespace Bga\Games\winter\States;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Exceptions\UnexpectedException;
use Bga\Games\winter\Game;
use Bga\Games\winter\Helpers\Collection;
use Bga\Games\winter\Helpers\Utils;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Players;
use Bga\Games\winter\Managers\Tokens;

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
        $boardCards = Cards::getInLocation(CARD_LOCATION_BOARD);
        $smallestLakes = Utils::listBoardLakes($boardCards);

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
            $this->gamestate->nextState('pass');
            return;
        }
        $lakes = $args['lakes'];
        $lakesDifferentSize = (count($lakes[1]) != count($lakes[2]) );
        if ($lakesDifferentSize){
            //GAME RUle : if one lake is smaller, this is automatically chosen
            $smallest = 1;
            if( count($lakes[1]) > count($lakes[2])) $smallest = 2;

            //Automatic action
            Notifications::smallestLake($smallest, $lakes[$smallest]);
            $this->actLake($smallest, 0, true);

            return;
        }
    }
    /**
     * Player action in phase 2 : choose a lake to discard
     * @param int $lakeIndex : index of lake to melt
     * @param bool $auto : (optional) is this action automatic ?
     * 
     * @throws BgaUserException
     */
    public function actLake(int $lakeIndex, #[IntParam(name: 'v')] int $version, bool $auto = false): void
    {
        if (!$auto) {
            Game::get()->checkVersion($version);
        }
        self::trace("actPlaceCard($lakeIndex,)");

        $player = Players::getCurrent();
        $pId = $player->getId();
        if (!$auto) {
            $this->addStep();
        }
        
        // check input values
        $args = $this->argLakeChoice();
        $lakes = $args['lakes'];
        if (!in_array($lakeIndex, array_keys($lakes))) {
            throw new UnexpectedException(105,"Invalid lake choice $lakeIndex");
        }
    
        //ACTION EFFECT : discard cards AND tokens
        $cardsIdsToDiscard = $lakes[$lakeIndex];
        $cards = Cards::getMany($cardsIdsToDiscard);
        $tokens = Tokens::getBoardTokens();
        $removedTokens = new Collection();
        foreach( $cards as $card){
            //Remove TOKENS on card
            $tokensSpotsOnCard = Utils::gridOverlappingTokensFromCard($card->getRow(), $card->getCol());
            foreach($tokensSpotsOnCard as $coord){
                $token = $tokens->filter(function ($token) use ($coord) {
                    return $token->coordArray() === $coord;
                })->first();
                if(isset($token)){
                    $fromLocation = $token->coordName();
                    $token->setRow(null);
                    $token->setCol(null);
                    $token->setLocation(TOKEN_LOCATION_HAND);
                    //Notifications::removeToken($player,$token, $fromLocation);
                    $removedTokens->append($token);
                }
            }
            //THEN Remove card
            $fromLocation = $card->coordName();
            $card->setRow(null);
            $card->setCol(null);
            $card->setLocation(CARD_LOCATION_DISCARD);
            //Notifications::removeCard($player,$card, $fromLocation);
        }
        //send 1 notif for all cards and tokens
        Notifications::removeLakeGroup($player,$cards,$removedTokens);
            
        Globals::setLastPlayedTokens([]);
        Globals::setLastPlayedCards([]);
        Notifications::refreshLastPlayed(Globals::getLastPlayedDatas());

        // at the end of the action, move to the next state
        $this->gamestate->nextState("next");
    }

}
