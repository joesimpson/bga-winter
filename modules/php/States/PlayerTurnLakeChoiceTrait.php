<?php

namespace Bga\Games\winter\States;

use Bga\GameFramework\Actions\Types\IntParam;
use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Exceptions\UnexpectedException;
use Bga\Games\winter\Game;
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
    
        //ACTION EFFECT : discard cards AND tokens
        $cardsIdsToDiscard = $lakes[$lakeIndex];
        $cards = Cards::getMany($cardsIdsToDiscard);
        $tokens = Tokens::getBoardTokens();
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
                    Notifications::removeToken($player,$token, $fromLocation);
                }
            }
            //THEN Remove card
            $fromLocation = $card->coordName();
            $card->setRow(null);
            $card->setCol(null);
            $card->setLocation(CARD_LOCATION_DISCARD);
            Notifications::removeCard($player,$card, $fromLocation);
        }
        //TODO JSA send 1 notif for all cards and tokens ?
            
        Globals::setLastPlayedTokens([]);
        Globals::setLastPlayedCards([]);
        Notifications::refreshLastPlayed(Globals::getLastPlayedDatas());

        // at the end of the action, move to the next state
        $this->gamestate->nextState("next");
    }
   
}
