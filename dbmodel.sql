
-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- winter implementation : © joesimpson <1324811+joesimpson@users.noreply.github.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "hearts"):

-- CREATE TABLE IF NOT EXISTS `card` (
--   `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
--   `card_type` varchar(16) NOT NULL,
--   `card_type_arg` int(11) NOT NULL,
--   `card_location` varchar(16) NOT NULL,
--   `card_location_arg` int(11) NOT NULL,
--   PRIMARY KEY (`card_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `cards` (
  `card_id` int(2) NOT NULL AUTO_INCREMENT,
  `card_state` int(10) DEFAULT 0,
  `card_location` varchar(32) NOT NULL,
  `type` int(10) NOT NULL,
  `x` int(10) NULL COMMENT 'Column on board map',
  `y` int(10) NULL COMMENT 'Row on board map',
  PRIMARY KEY (`card_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `tokens` (
  `token_id` int(2) NOT NULL AUTO_INCREMENT,
  `token_state` int(10) DEFAULT 0,
  `token_location` varchar(32) NOT NULL,
  `type` int(10) NOT NULL,
  `player_id` int(10) NULL,
  `x` int(10) NULL COMMENT 'Column on board map',
  `y` int(10) NULL COMMENT 'Row on board map',
  PRIMARY KEY (`token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- Example 2: add a custom field to the standard "player" table
-- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';

-- CORE TABLES from tisaac boilerplate --
CREATE TABLE IF NOT EXISTS `my_global_variables` (
  `name` varchar(50) NOT NULL,
  `value` JSON,
  PRIMARY KEY (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = UTF8MB4;

CREATE TABLE IF NOT EXISTS `log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `move_id` int(10) NOT NULL,
  `table` varchar(32) NOT NULL,
  `primary` varchar(32) NOT NULL,
  `type` varchar(32) NOT NULL,
  `affected` JSON,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
