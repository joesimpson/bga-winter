<?php
namespace Bga\Games\winter\Core;
use Bga\Games\winter\Game;

/**
 * User preferences module from boilerplate, but now framework exposes an access to user prefs
 */
class Preferences extends \Bga\Games\winter\Helpers\DB_Manager
{
  // protected static $table = 'user_preferences';
  // protected static $primary = 'id';
  // protected static $log = false; // Turn off log to avoid undoing changes in this table
  // protected static function cast($row)
  // {
  //   return $row;
  // }

  // /*
  //  * Setup new game
  //  */
  // public static function setupNewGame($players, $prefs)
  // {
  //   // Load user preferences
  //   include dirname(__FILE__) . '/../gameoptions.inc.php';

  //   $values = [];
  //   if(count($game_preferences) == 0) return;
  //   foreach ($game_preferences as $id => $data) {
  //     $defaultValue = $data['default'] ?? array_keys($data['values'])[0];

  //     foreach ($players as $pId => $infos) {
  //       $values[] = [
  //         'player_id' => $pId,
  //         'pref_id' => $id,
  //         'pref_value' => $prefs[$pId][$id] ?? $defaultValue,
  //       ];
  //     }
  //   }

  //   self::DB()
  //     ->multipleInsert(['player_id', 'pref_id', 'pref_value'])
  //     ->values($values);
  // }

  // /*
  //  * Check if stored user preferences match declared preferences, and create otherwise
  //  */
  // public static function checkExistence()
  // {
  //   // Load user preferences
  //   include dirname(__FILE__) . '/../gameoptions.inc.php';

  //   $playerIds = array_keys(Game::get()->loadPlayersBasicInfos());
  //   $values = [];
  //   foreach ($game_preferences as $id => $data) {
  //     $defaultValue = $data['default'] ?? array_keys($data['values'])[0];

  //     foreach ($playerIds as $pId) {
  //       if (self::get($pId, $id) == null) {
  //         $values[] = [
  //           'player_id' => $pId,
  //           'pref_id' => $id,
  //           'pref_value' => $defaultValue,
  //         ];
  //       }
  //     }
  //   }

  //   if (!empty($values)) {
  //     self::DB()
  //       ->multipleInsert(['player_id', 'pref_id', 'pref_value'])
  //       ->values($values);
  //   }
  // }

  /**
   * Get UI data (useful to check inconsistency)
   */
  public static function getUiData($pId)
  {
    // self::checkExistence();
    // return self::DB()
    //   ->where('player_id', $pId)
    //   ->get()
    //   ->toArray();
    $playerPrefs = [];
    foreach (ALL_PREFERENCES as $prefId)
    {
      $pref_value = Game::get()->userPreferences->get($pId,$prefId);
      $playerPrefs[] = [
        "id" => "$pId--$prefId",
        "player_id" => $pId,
        "pref_id" => $prefId,
        "pref_value" => $pref_value,
      ];
    }
    return $playerPrefs;
  }

  // /*
  //  * Get a user preference
  //  */
  // public static function get($pId, $prefId)
  // {
  //   return self::DB()
  //     ->select(['pref_value'])
  //     ->where('player_id', $pId)
  //     ->where('pref_id', $prefId)
  //     ->get(true)['pref_value'] ?? null;
  // }

  // /*
  //  * Set a user preference
  //  */
  // public static function set($pId, $prefId, $value)
  // {
  //   return self::DB()
  //     ->update(['pref_value' => $value])
  //     ->where('player_id', $pId)
  //     ->where('pref_id', $prefId)
  //     ->run();
  // }
}
