USE heroku_5036cf2b7f42600;

/*
  автоинкремент = 10 это фича ClearDB
*/

SET time_zone = '+03:00';

CREATE TABLE subscribers
(
    chat_id INT UNSIGNED NOT NULL,
    time_everyday_repeat TIME NULL,
    PRIMARY KEY (chat_id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
;

CREATE TABLE notebook
(
    id INT UNSIGNED AUTO_INCREMENT NOT NULL,
    chat_id INT UNSIGNED NOT NULL,
    film_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
;

CREATE TABLE genres
(
    id INT UNSIGNED NOT NULL,
    genre VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
;

CREATE TABLE filter_genre
(
    id INT AUTO_INCREMENT NOT NULL,
    chat_id INT UNSIGNED NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
;

CREATE TABLE filter_rating
(
    chat_id INT UNSIGNED NOT NULL,
    min_rating INT NULL,
    max_rating INT NULL,
    PRIMARY KEY (chat_id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
;

CREATE TABLE films
(
    id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
;


