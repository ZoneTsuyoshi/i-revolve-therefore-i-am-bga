-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- IRevolveThereforeIAm implementation : © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- データベースのスキーマ（仕様書 007 §3）。静的な定義（コスト、鍵、スコア、効果）は持たず、Material/ を card_type などで引く。
-- global / stats / gamelog / player は作成済みなので作らない。player には ALTER TABLE で列だけを足す。
-- 注意: コードと同じ行にコメントを書かない（行全体が無効になりうる）。

CREATE TABLE IF NOT EXISTS `memory_card` (
  `card_id` SMALLINT UNSIGNED NOT NULL,
  `card_type` SMALLINT UNSIGNED NOT NULL,
  `card_loc` VARCHAR(12) NOT NULL,
  `card_loc_arg` INT NOT NULL DEFAULT 0,
  `card_pos` INT NOT NULL DEFAULT 0,
  `card_tapped` TINYINT(1) NOT NULL DEFAULT 0,
  `card_face_up` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`card_id`),
  KEY `idx_card_loc` (`card_loc`, `card_loc_arg`, `card_pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `room` (
  `room_pos` TINYINT UNSIGNED NOT NULL,
  `room_floor` TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`room_pos`),
  UNIQUE KEY `uq_room_floor` (`room_floor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `spirit_card` (
  `spirit_id` VARCHAR(3) NOT NULL,
  `spirit_tier` TINYINT UNSIGNED NOT NULL,
  `spirit_slot` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `spirit_light` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`spirit_id`),
  UNIQUE KEY `uq_spirit_place` (`spirit_tier`, `spirit_slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ego_card` (
  `ego_id` VARCHAR(3) NOT NULL,
  `ego_loc` VARCHAR(10) NOT NULL,
  `ego_owner` INT UNSIGNED NULL DEFAULT NULL,
  `ego_pos` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`ego_id`),
  KEY `idx_ego_loc` (`ego_loc`, `ego_owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `objective_card` (
  `obj_id` TINYINT UNSIGNED NOT NULL,
  `obj_state` VARCHAR(6) NOT NULL,
  `obj_slot` TINYINT UNSIGNED NULL DEFAULT NULL,
  `obj_owner` INT UNSIGNED NULL DEFAULT NULL,
  `obj_claim_turn` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`obj_id`),
  KEY `idx_obj_owner` (`obj_owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `effect_frame` (
  `frame_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `frame_order` INT NOT NULL,
  `frame_parent` INT UNSIGNED NULL DEFAULT NULL,
  `frame_kind` VARCHAR(24) NOT NULL,
  `frame_owner` INT UNSIGNED NOT NULL,
  `frame_source` VARCHAR(8) NULL DEFAULT NULL,
  `frame_spec` VARCHAR(12) NULL DEFAULT NULL,
  `frame_op` VARCHAR(24) NULL DEFAULT NULL,
  `frame_step` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `frame_group` INT UNSIGNED NULL DEFAULT NULL,
  `frame_origin` VARCHAR(12) NOT NULL DEFAULT '',
  `frame_unit` TINYINT(1) NOT NULL DEFAULT 0,
  `frame_cancel_to` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `frame_status` VARCHAR(7) NOT NULL DEFAULT 'READY',
  `frame_params` TEXT NULL DEFAULT NULL,
  `frame_payload` TEXT NULL DEFAULT NULL,
  `frame_choice` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`frame_id`),
  KEY `idx_frame_order` (`frame_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

ALTER TABLE `player` ADD `player_persona` TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_spirit` VARCHAR(3) NULL DEFAULT NULL;
ALTER TABLE `player` ADD `player_light` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_wild_up` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_wild_used` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_compose_tok` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `player_analysis_tok` INT UNSIGNED NOT NULL DEFAULT 0;
