<?php

namespace Bga\Games\winter\Core;

use Bga\Games\winter\Game;
use Bga\Games\winter\Helpers\Collection;
use Bga\Games\winter\Helpers\Utils;
use Bga\Games\winter\Managers\Tokens;
use Bga\Games\winter\Models\Card;
use Bga\Games\winter\Models\Player;
use Bga\Games\winter\Models\Token;

class Notifications
{ 
  
  /**
   * @param Player $player
   * @param Card $card
   */
  public static function cardPlayed(Player $player, Card $card)
  {
    $msg = clienttranslate('${player_name} places a card at ${coord}');
    self::notifyAll('cardPlayed', $msg, [
      'player' => $player,
      'card' => $card->getUiData(),
      'coord' => $card->coordName(),
    ]);
  }
  /**
   * @param Player $player
   * @param Card $card
   */
  public static function cardDrawn(Player $player, Card $card)
  {
    $msg = clienttranslate('${player_name} draws a card');
    self::notifyAll('cardDrawn', $msg, [
      'player' => $player,
      'card' => $card->getUiData(),
    ]);
  }
  
  /**
   * @param Player $player
   * @param Card $card
   * @param string $fromLocation
   */
  public static function removeCard(Player $player, Card $card, string $fromLocation)
  {
    $msg = clienttranslate('${player_name} removes a card at ${location}');
    self::notifyAll('removeCard', $msg, [
      'player' => $player,
      'location' => $fromLocation,
      'card' => $card->getUiData(),
    ]);
  }
  
  /**
   * @param Player $player
   * @param Card $card
   * @param string $fromLocation
   */
  public static function cardMoved(Player $player, Card $card, string $fromLocation)
  {
    $msg = clienttranslate('${player_name} moves a card from ${A} to ${B}');
    self::notifyAll('cardMoved', $msg, [
      'player' => $player,
      'card' => $card->getUiData(),
      'A' => $fromLocation,
      'B' => $card->coordName(),
    ]);
  }

  /**
   * @param Player $player
   * @param int $color
   * @param bool $choice choice by player or not
   */
  public static function colorTaken(Player $player, int $color, bool $choice)
  {
    $msg = clienttranslate('${player_name} chooses to take ${token_color} counters');
    if( !$choice) $msg = clienttranslate('${player_name} takes ${token_color} counters');
    self::notifyAll('colorTaken', $msg, [
      'player' => $player,
      'token_color' => Tokens::getColorName($color),

      'i18n' => ['token_color'],  
      'preserve' => [ 'token_color_type' ],
      'token_color_type' => $color,
    ]);
  }
  
  /**
   * @param Player $player
   * @param int $color
   */
  public static function newPlayerColor(Player $player,int $color)
  {
    self::notifyAll('newPlayerColor', '', [
      'player' => $player,
      'player_color' => $player->getColor(),
      'token_color_type' => $color,
    ]);
  }
  
  /**
   * @param Player $player
   * @param Collection $tokens
   */
  public static function assignTokens(Player $player,Collection $tokens)
  {
    self::notifyAll('assignTokens', '', [
      'player' => $player,
      'tokens' => $tokens->ui(),
    ]);
  }
  
  /**
   * @param Player $player
   * @param Token $token
   */
  public static function placeToken(Player $player, Token $token)
  {
    $msg = clienttranslate('${player_name} places a ${token_color} token at ${location}');
    self::notifyAll('placeToken', $msg, [
      'player' => $player,
      'location' => $token->coordName(),
      'token_color' => Tokens::getColorName($token->getType()),

      'token' => $token->getUiData(),
      'i18n' => ['token_color'],  
      'preserve' => [ 'token_color_type' ],
      'token_color_type' => $token->getType(),
    ]);
  }
  
  /**
   * @param Player $player
   * @param Token $token
   * @param string $fromLocation
   */
  public static function removeToken(Player $player, Token $token, string $fromLocation)
  {
    $msg = clienttranslate('${player_name} takes away a ${token_color} token at ${location}');
    self::notifyAll('removeToken', $msg, [
      'player' => $player,
      'location' => $fromLocation,
      'token_color' => Tokens::getColorName($token->getType()),

      'token' => $token->getUiData(),
      'i18n' => ['token_color'],  
      'preserve' => [ 'token_color_type' ],
      'token_color_type' => $token->getType(),
    ]);
  }

  
  /**
   * @param Player $player
   * @param Collection $cards
   * @param Collection $tokens
   * 
   */
  public static function removeLakeGroup(Player $player, Collection $cards, Collection $tokens): void
  {
    $msg = clienttranslate('${player_name} removes ${n} cards and ${m} counters from a lake');
    self::notifyAll('removeLakeGroup', $msg, [
      'player' => $player,
      'n' => $cards->count(),
      'm' => $tokens->count(),
      'cards' => $cards->ui(),
      'tokens' => $tokens->ui(),
    ]);
  }

  public static function biggestLake(int $lakeIndex): void
  {
    $msg = clienttranslate('The smallest frozen lake (group of cards) automatically melts');
    self::notifyAll('biggestLake', $msg, [
      'lakeIndex' => $lakeIndex,
    ]);
  }

  /**
   * @param int $phase
   */
  public static function newPhase(int $phase)
  {
    $msg = clienttranslate('Phase ${phase} starts');
    
    self::notifyAll('newPhase', $msg, [
      'phase' => $phase,
    ]);
  }
  /**
   * @param array $datas
   */
  public static function refreshLastPlayed(array $datas)
  {
    
    self::notifyAll('refreshLastPlayed', '', [
      'datas' => $datas,
    ]);
  }
  /**
   */  
  /*************************
   **** GENERIC METHODS ****
   *************************/
  protected static function notifyAll($name, $msg, $data)
  {
    self::updateArgs($data);
    Game::get()->notifyAllPlayers($name, $msg, $data);
  }

  protected static function notify($player, $name, $msg, $data)
  {
    $pId = is_int($player) ? $player : $player->getId();
    self::updateArgs($data);
    Game::get()->notifyPlayer($pId, $name, $msg, $data);
  }

  public static function message($txt, $args = [])
  {
    self::notifyAll('message', $txt, $args);
  }

  public static function messageTo($player, $txt, $args = [])
  {
    $pId = is_int($player) ? $player : $player->getId();
    self::notify($pId, 'message', $txt, $args);
  }

  /**
   *  Empty notif to send after an action, to let framework works & refresh ui
   * (Usually not needed if we send another notif or if we change state of a player)
   * */
  public static function emptyNotif(){
    self::notifyAll('e','',[],);
  }
  /*********************
   **** UPDATE ARGS ****
   *********************/

  /*
   * Automatically adds some standard field about player and/or card
   */
  protected static function updateArgs(&$data)
  {
    if (isset($data['player'])) {
      $data['player_name'] = $data['player']->getName();
      $data['player_id'] = $data['player']->getId();
      //for playername_wrapper
      $data['player_color'] = $data['player']->getColor();
      if (!isset($data['preserve'])) {
        $data['preserve'] = [];
      }
      $data['preserve'][] = 'player_color';

      unset($data['player']);
    }
    if (isset($data['player2'])) {
      $data['player_name2'] = $data['player2']->getName();
      $data['player_id2'] = $data['player2']->getId();
      //for playername_wrapper
      $data['player_color2'] = $data['player2']->getColor();
      if (!isset($data['preserve'])) {
        $data['preserve'] = [];
      }
      $data['preserve'][] = 'player_color2';
      unset($data['player2']);
    }
    else {
      unset($data['player2']);
    }
    /* not used in this game for now
    
    if (isset($data['player3'])) {
      $data['player_name3'] = $data['player3']->getName();
      $data['player_id3'] = $data['player3']->getId();
      unset($data['player3']);
    }
    */
  }
  
  /************************************
   **** UPDATES after confirm/undo ****
   ***********************************/
  
  public static function refreshUI($datas)
  {
    // Keep only the things from getAllDatas that matters
    $gameDatas = [
      'players' => $datas['players'],
      'cards' => $datas['cards'],
      'deckSize' => $datas['deckSize'],
      'phase' => $datas['phase'],
      'lastPlayed' => $datas['lastPlayed'],
      'tokens' => $datas['tokens'],
    ];

    self::notifyAll('refreshUI', '', [
      'datas' => $gameDatas,
    ]);
  }
  
  /**
   * @param Player $player
   * @param array $notifIds
   */
  public static function clearTurn($player, $notifIds)
  {
    self::notifyAll('clearTurn', '', [
      'player' => $player,
      'notifIds' => $notifIds,
    ]);
  }
  
  /**
   * @param Player $player
   * @param int $stepId
   */
  public static function undoStep($player, $stepId)
  {
    self::notifyAll('undoStep', clienttranslate('${player_name} undoes their action'), [
      'player' => $player,
    ]);
  }
  /**
   * @param Player $player
   */
  public static function restartTurn($player)
  {
    self::notifyAll('restartTurn', clienttranslate('${player_name} restarts their turn'), [
      'player' => $player,
    ]);
  }

}
