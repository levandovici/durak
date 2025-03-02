CREATE DATABASE IF NOT EXISTS durak_game;

USE durak_game;

CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    session_id VARCHAR(50) UNIQUE,
    is_online TINYINT(1) DEFAULT 0
);

CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('waiting', 'active', 'finished') DEFAULT 'waiting',
    trump_suit VARCHAR(10),
    max_players INT DEFAULT 6
);

CREATE TABLE game_players (
    game_id INT,
    player_id INT,
    turn_order INT, -- 1 to 6, defines sequence
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    PRIMARY KEY (game_id, player_id)
);

CREATE TABLE game_state (
    game_id INT,
    player_id INT,
    hand TEXT, -- JSON-encoded array of cards
    `table` TEXT DEFAULT '[]', -- JSON-encoded attack/defense cards
    is_turn TINYINT(1) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    PRIMARY KEY (game_id, player_id)
);