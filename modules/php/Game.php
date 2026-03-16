<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * winter implementation : © joesimpson <1324811+joesimpson@users.noreply.github.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\winter;

use Bga\Games\winter\Core\Globals;
use Bga\Games\winter\Core\Notifications;
use Bga\Games\winter\Core\Preferences;
use Bga\Games\winter\Exceptions\UserException;
use Bga\Games\winter\Helpers\Utils;
use Bga\Games\winter\Managers\Cards;
use Bga\Games\winter\Managers\Players;
use Bga\Games\winter\Managers\Tokens;

require_once 'constants.inc.php';

class Game extends \Bga\GameFramework\Table
{
    use DebugTrait;
    use States\ColorChoiceTrait;
    use States\ConfirmUndoTrait;
    use States\EndTurnTrait;
    use States\NextTurnTrait;
    use States\PlayerTurnTrait;
    use States\PlayerTurnLakeChoiceTrait;
    use States\PlayerTurnPlaceCardTrait;
    use States\ScoringTrait;
    use States\SecondPlayerTrait;
    use States\SetupTrait;
    use States\StartingCardTrait;

    public static $instance = null;

    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();
        self::$instance = $this;

        $this->initGameStateLabels([
            'logging' => 10,
        ]);        

        /* example of notification decorator.
        // automatically complete notification args when needed
        $this->notify->addDecorator(function(string $message, array $args) {
            if (isset($args['player_id']) && !isset($args['player_name']) && str_contains($message, '${player_name}')) {
                $args['player_name'] = $this->getPlayerNameById($args['player_id']);
            }
        
            if (isset($args['card_id']) && !isset($args['card_name']) && str_contains($message, '${card_name}')) {
                $args['card_name'] = self::$CARD_TYPES[$args['card_id']]['card_name'];
                $args['i18n'][] = ['card_name'];
            }
            
            return $args;
        });*/
    }

    public static function get() 
    {
        return self::$instance;
    }

    //-> See States package for game states arguments and actions

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        $progressPhase1 = 0;
        $progressPhase2 = 0;

        $initialDeckSize = Cards::countAll();

        $phase = Globals::getPhase();
        switch($phase){
            case PHASE_BEGINNING:
                break;
            case PHASE_FREEZING:
                //Phase will end when no more cards are in deck
                $currentDeckSize = Cards::countInLocation(CARD_LOCATION_DECK);
                $progressPhase1 = ($initialDeckSize - $currentDeckSize) / $initialDeckSize;
                $progressPhase2 = 0;
                break;
            case PHASE_THAWING:
                //Phase will end when no more token of 1 player are on board (ie when player tokens's hand is full)
                $progressPhase1 = 1;
                
                $players = Players::getAll();
                $maxNbTokensInHand = max($players->map(function($player) { return $player->getNbTokensInHand();})->toArray()  );
                //add average progress on remaining cards on board because in this phase the cards may be removed but not added
                $nbCardsOnBoards = Cards::countInLocation(CARD_LOCATION_BOARD);
                $progressPhase2 = max( ($maxNbTokensInHand / NB_COUNTER_COPIES), ($initialDeckSize - $nbCardsOnBoards) / $initialDeckSize);

                break;
        }

        //We expect 50% of the game in phase 1 + 50% in phase 2
        return 100 * ($progressPhase1 * 50/100 + $progressPhase2 * 50/100);
    }
     
    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
        if ($from_version <= 2602201727)
        {
            //Migrate from 'bga_globals' to 'my_global_variables'
            $sql = "CREATE TABLE IF NOT EXISTS  `DBPREFIX_my_global_variables` (
                        `name` varchar(50) NOT NULL,
                        `value` JSON,
                        PRIMARY KEY (`name`)
                    ) ENGINE = InnoDB DEFAULT CHARSET = UTF8MB4
                    SELECT * FROM `bga_globals`";
            $this->applyDbUpgradeToAllDB( $sql );

            $sql = "UPDATE `DBPREFIX_log` SET `table` = 'my_global_variables' WHERE `table` = 'bga_globals'" ;
            $this->applyDbUpgradeToAllDB( $sql );
        }
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    public function getAllDatas(): array
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        $result["players"] = Players::getUiData($current_player_id);
        $result["cards"] = Cards::getUiData($current_player_id);
        $result["deckSize"] = Cards::countInLocation(CARD_LOCATION_DECK);
        $result["phase"] = Globals::getPhase();
        $result["lastPlayed"] = Globals::getLastPlayedDatas();
        $result["tokens"] = Tokens::getUiData($current_player_id);
        $result["prefs"] = Preferences::getUiData($current_player_id);
        $result["version"] = Utils::gameVersion();

        return $result;
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $state_name = $state["name"];
        Game::get()->trace("zombieTurn($active_player) : state ".json_encode($state));

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                case 'startingCard':
                    //Avoid future technical issues if we keep the card drawn in hand !
                    $this->actPlayStartingCard(CARD_DIRECTION_UP,0,2,0,true);
                    return;
                case 'placeCard':
                    //Avoid future technical issues if we keep the card drawn in hand !
                    $stateArgs = $this->argPlaceCard();
                    $playableCoords = $stateArgs['playableCoords'];
                    $coord = $playableCoords[array_rand($playableCoords)];
                    $this->actPlaceCard($coord['dirs'][0],$coord['row'],$coord['col'],0,true);
                    return;
                case 'colorChoice':
                    //The next player will need to have colors already chosen
                    $colors = $this->argColorChoice()['playableColors'];
                    $color = $colors[array_rand($colors)];
                    $this->actChooseColor($color,0,true);
                    //The action already changes state
                    //$this->gamestate->nextState("zombiePass");
                    return;
                case 'lakeChoice':
                    //The next player will need to play on 1 lake only
                    $this->actLake(1, 0, true);
                    //The action already changes state
                    //$this->gamestate->nextState("zombiePass");
                    return;
                case 'playerTurn':
                    $args = $this->argPlayerTurn();
                    $playableTokens = $args['spots_for_tokens'];
                    if(count($playableTokens) > 0){
                        $spot = $playableTokens[array_rand($playableTokens)];
                        $this->actPlaceToken($spot[0],$spot[1], 0, true);
                        return;
                    }
                    //else use default
                default:
                {
                    $this->gamestate->nextState("zombiePass");
                    break;
                }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }

    /**
    * Check Server version to compare with client version : throw an error in case it 's not the same
    * From https://en.doc.boardgamearena.com/BGA_Studio_Cookbook#Force_players_to_refresh_after_new_deploy
    */
    public function checkVersion(int $clientVersion)
    {
        $gameVersion = Utils::gameVersion();
        if ($clientVersion != intval($gameVersion)) {
            throw new UserException(555,'!!!checkVersion');
        }
    }
    /////////////////////////////////////////////////////////////
    // Exposing protected methods, please use at your own risk //
    /////////////////////////////////////////////////////////////

    // Exposing protected method getCurrentPlayerId
    public function getCurrentPId($bReturnNullIfNotLogged = false)
    {
        return $this->getCurrentPlayerId($bReturnNullIfNotLogged);
    }
    
}
