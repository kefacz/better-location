CREATE DATABASE `better_location` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
USE `better_location`;

CREATE TABLE `better_location_user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_telegram_id` bigint NOT NULL,
  `user_telegram_name` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `user_registered` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_last_update` datetime NOT NULL,
  `user_location_lat` DOUBLE(10,6) NULL,
  `user_location_lon` DOUBLE(10,6) NULL,
  `user_location_last_update` DATETIME NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_telegram_id` (`user_telegram_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE `better_location_chat` (
  `chat_id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_telegram_id` bigint NOT NULL,
  `chat_telegram_type` varchar(20) COLLATE utf8mb4_bin NOT NULL,
  `chat_telegram_name` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `chat_registered` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `chat_last_update` datetime NOT NULL,
  `chat_settings_preview` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `chat_settings_output_type` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `chat_settings_show_address` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`chat_id`),
  UNIQUE KEY `chat_telegram_id` (`chat_telegram_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `better_location_favourites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT '1',
  `lat` double(10,6) NOT NULL,
  `lon` double(10,6) NOT NULL,
  `title` varchar(50) COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique` (`user_id`,`lat`,`lon`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
ALTER TABLE `better_location_favourites` ADD CONSTRAINT `favourites.user_id` FOREIGN KEY (`user_id`) REFERENCES `better_location_user` (`user_id`);

/*
 * For compatibility reasons there is used LONGTEXT instead of JSON
 * @see https://mariadb.com/kb/en/json-data-type/
 */
CREATE TABLE IF NOT EXISTS `better_location_telegram_updates` (
  `chat_id` bigint NOT NULL,
  `input_message_id` int NOT NULL,
  `bot_reply_message_id` int NOT NULL,
  `original_update_object` LONGTEXT NOT NULL,
  `autorefresh_status` tinyint NOT NULL DEFAULT '0',
  `last_update` datetime NOT NULL,
  `last_response_text` text COLLATE utf8mb4_bin,
  `last_response_reply_markup` LONGTEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE `better_location_static_map_cache` (
  `id` varchar(16) NOT NULL PRIMARY KEY,
  `url` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS better_location_chat_services
(
    id         int auto_increment primary key,
    chat_id    int NOT NULL,
    service_id int NOT NULL,
    type       int NOT NULL COMMENT 'Share, Drive, Screenshot, ...',
    `order`    int NOT NULL,
    constraint better_location_chat_services_unique_order
        unique (chat_id, type, `order`),
    constraint better_location_chat_services_unique_services
        unique (chat_id, service_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS better_location_web_login
(
    id               int auto_increment
        primary key,
    hash             varchar(64)  null,
    user_telegram_id bigint       not null,
    auth_date        int          not null,
    user_first_name  varchar(255) not null,
    user_last_name   varchar(255) null,
    user_name        varchar(255) null,
    user_photo_url   text         null,
    constraint better_location_web_login_hash_uindex
        unique (hash)
);

