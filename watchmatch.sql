SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `Blocked_users` (
  `id` int NOT NULL,
  `user_id_blocker` int NOT NULL,
  `user_id_blocked` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Friends` (
  `id` int NOT NULL,
  `user_id_1` int NOT NULL,
  `user_id_2` int NOT NULL,
  `num_of_movies_watched` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Match_online_rooms` (
  `id` int NOT NULL,
  `host_user_id` int NOT NULL,
  `guest_user_id` int DEFAULT NULL,
  `region_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'waiting',
  `matched_movie_id` int DEFAULT NULL,
  `host_decision` varchar(20) DEFAULT NULL,
  `guest_decision` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Match_online_room_providers` (
  `id` int NOT NULL,
  `room_id` int NOT NULL,
  `provider_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Match_online_room_swipes` (
  `id` int NOT NULL,
  `room_id` int NOT NULL,
  `user_id` int NOT NULL,
  `movie_id` int NOT NULL,
  `swipe` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Movies` (
  `id` int NOT NULL,
  `tmdb_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `popularity` double NOT NULL,
  `picture` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Providers` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `tmdb_id` int NOT NULL,
  `logo` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Regions` (
  `id` int NOT NULL,
  `iso_code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Users` (
  `id` int NOT NULL,
  `auth_token` varchar(64) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `username` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `region_id` int NOT NULL,
  `profil_icon` varchar(1) NOT NULL,
  `icon_color` varchar(8) NOT NULL,
  `icon_bg_color` varchar(8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Watched_movies` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `movie_id` int NOT NULL,
  `rating` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `Where_to_watch` (
  `id` int NOT NULL,
  `movie_id` int NOT NULL,
  `regio_id` int NOT NULL,
  `provider_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


ALTER TABLE `Blocked_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id_blocker` (`user_id_blocker`),
  ADD KEY `user_id_blocked` (`user_id_blocked`);

ALTER TABLE `Friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `Friends_fk1` (`user_id_1`),
  ADD KEY `Friends_fk2` (`user_id_2`);

ALTER TABLE `Match_online_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Match_online_rooms_host_fk` (`host_user_id`),
  ADD KEY `Match_online_rooms_guest_fk` (`guest_user_id`),
  ADD KEY `Match_online_rooms_region_fk` (`region_id`),
  ADD KEY `Match_online_rooms_movie_fk` (`matched_movie_id`);

ALTER TABLE `Match_online_room_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_provider_unique` (`room_id`,`provider_id`),
  ADD KEY `Match_online_room_providers_room_fk` (`room_id`),
  ADD KEY `Match_online_room_providers_provider_fk` (`provider_id`);

ALTER TABLE `Match_online_room_swipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_user_movie_unique` (`room_id`,`user_id`,`movie_id`),
  ADD KEY `Match_online_room_swipes_room_fk` (`room_id`),
  ADD KEY `Match_online_room_swipes_user_fk` (`user_id`),
  ADD KEY `Match_online_room_swipes_movie_fk` (`movie_id`);

ALTER TABLE `Movies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `tmdb_id` (`tmdb_id`);

ALTER TABLE `Providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `tmdb_id` (`tmdb_id`);

ALTER TABLE `Regions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `iso_code` (`iso_code`);

ALTER TABLE `Users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `auth_token_unique` (`auth_token`),
  ADD KEY `Users_fk4` (`region_id`);

ALTER TABLE `Watched_movies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `Watched_movies_fk1` (`user_id`),
  ADD KEY `Watched_movies_fk2` (`movie_id`);

ALTER TABLE `Where_to_watch`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `Where_to_watch_fk1` (`movie_id`),
  ADD KEY `Where_to_watch_fk2` (`regio_id`),
  ADD KEY `Where_to_watch_fk3` (`provider_id`);


ALTER TABLE `Blocked_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Friends`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Match_online_rooms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Match_online_room_providers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Match_online_room_swipes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Movies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Providers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Regions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Watched_movies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `Where_to_watch`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;


ALTER TABLE `Blocked_users`
  ADD CONSTRAINT `Blocked_users_ibfk_1` FOREIGN KEY (`user_id_blocker`) REFERENCES `Users` (`id`),
  ADD CONSTRAINT `Blocked_users_ibfk_2` FOREIGN KEY (`user_id_blocked`) REFERENCES `Users` (`id`);

ALTER TABLE `Friends`
  ADD CONSTRAINT `Friends_fk1` FOREIGN KEY (`user_id_1`) REFERENCES `Users` (`id`),
  ADD CONSTRAINT `Friends_fk2` FOREIGN KEY (`user_id_2`) REFERENCES `Users` (`id`);

ALTER TABLE `Match_online_rooms`
  ADD CONSTRAINT `Match_online_rooms_guest_fk` FOREIGN KEY (`guest_user_id`) REFERENCES `Users` (`id`),
  ADD CONSTRAINT `Match_online_rooms_host_fk` FOREIGN KEY (`host_user_id`) REFERENCES `Users` (`id`),
  ADD CONSTRAINT `Match_online_rooms_movie_fk` FOREIGN KEY (`matched_movie_id`) REFERENCES `Movies` (`id`),
  ADD CONSTRAINT `Match_online_rooms_region_fk` FOREIGN KEY (`region_id`) REFERENCES `Regions` (`id`);

ALTER TABLE `Match_online_room_providers`
  ADD CONSTRAINT `Match_online_room_providers_provider_fk` FOREIGN KEY (`provider_id`) REFERENCES `Providers` (`id`),
  ADD CONSTRAINT `Match_online_room_providers_room_fk` FOREIGN KEY (`room_id`) REFERENCES `Match_online_rooms` (`id`);

ALTER TABLE `Match_online_room_swipes`
  ADD CONSTRAINT `Match_online_room_swipes_movie_fk` FOREIGN KEY (`movie_id`) REFERENCES `Movies` (`id`),
  ADD CONSTRAINT `Match_online_room_swipes_room_fk` FOREIGN KEY (`room_id`) REFERENCES `Match_online_rooms` (`id`),
  ADD CONSTRAINT `Match_online_room_swipes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `Users` (`id`);

ALTER TABLE `Users`
  ADD CONSTRAINT `Users_fk4` FOREIGN KEY (`region_id`) REFERENCES `Regions` (`id`);

ALTER TABLE `Watched_movies`
  ADD CONSTRAINT `Watched_movies_fk1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`id`),
  ADD CONSTRAINT `Watched_movies_fk2` FOREIGN KEY (`movie_id`) REFERENCES `Movies` (`id`);

ALTER TABLE `Where_to_watch`
  ADD CONSTRAINT `Where_to_watch_fk1` FOREIGN KEY (`movie_id`) REFERENCES `Movies` (`id`),
  ADD CONSTRAINT `Where_to_watch_fk2` FOREIGN KEY (`regio_id`) REFERENCES `Regions` (`id`),
  ADD CONSTRAINT `Where_to_watch_fk3` FOREIGN KEY (`provider_id`) REFERENCES `Providers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
