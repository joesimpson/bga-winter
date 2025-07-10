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
        $lakes = Utils::listBoardLakes($boardCards);
        $biggestLakes = [];
        $maxSize = 0;
        foreach($lakes as $lakeId => $lake){
            if(count($lake) > $maxSize){
                $maxSize = count($lake);
                $biggestLakes = [$lakeId];
            }
            else if(count($lake) == $maxSize){
                $biggestLakes[] = $lakeId;
            }
        }

        $args = [
            'lakes' => $lakes,
            'lakes_choice' => $biggestLakes,
                
            //AUTO SKIP STATE when no decision
            '_no_notify' => count($lakes) < 2,
        ];
        
        $this->addArgsForUndo($args);
        return $args;
    }

    public function stLakeChoice(): void
    {
        $args = $this->argLakeChoice();
        $player = Players::getActive();
        if ($args['_no_notify']) {
            $cardToPlace = Cards::getDrawnCard($player);
            if(isset($cardToPlace)){
                $this->gamestate->nextState("place");
            }
            else {
                $this->gamestate->nextState("next");
            }
            return;
        }
        $biggestLakes = $args['lakes_choice'];

        if (count($biggestLakes) == 1){
            //GAME Rule : if one lake is smaller, this is automatically chosen
            //-> keep only the biggest one, because we could have more than 2
            $biggest = $biggestLakes[0];

            //Automatic action
            Notifications::biggestLake($biggest);
            $this->actLake($biggest, 0, true);

            return;
        }
    }
    /**
     * Player action in phase 2 : choose a lake to KEEP
     * @param int $lakeIndex : index of lake to KEEP
     * @param bool $auto : (optional) is this action automatic ?
     * 
     * @throws BgaUserException
     */
    public function actLake(int $lakeIndex, #[IntParam(name: 'v')] int $version, bool $auto = false): void
    {
        if (!$auto) {
            Game::get()->checkVersion($version);
        }
        self::trace("actLake($lakeIndex,)");

        $player = Players::getCurrent();
        $pId = $player->getId();
        if (!$auto) {
            $this->addStep();
        }
        
        // check input values
        $args = $this->argLakeChoice();
        $lakes = $args['lakes'];
        $lakes_choice = $args['lakes_choice'];
        if (!in_array($lakeIndex, $lakes_choice)) {
            throw new UnexpectedException(105,"Invalid lake choice $lakeIndex");
        }
    
        //ACTION EFFECT : SELECT THIS LAKE and discard others
        foreach($lakes as $index => $lake){
            if($lakeIndex == $index) continue;
            // EFFECT : discard cards AND tokens
                
            $cardsIdsToDiscard = $lake;
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
        }
            
        Globals::setLastPlayedTokens([]);
        Globals::setLastPlayedCards([]);
        Notifications::refreshLastPlayed(Globals::getLastPlayedDatas());

        // at the end of the action, move to the next state
        $cardToPlace = Cards::getDrawnCard($player);
        if(isset($cardToPlace)){
            $this->gamestate->nextState("place");
        }
        else {
            $this->gamestate->nextState("next");
        }
    }

}
