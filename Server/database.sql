CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    session_id VARCHAR(50) UNIQUE,
    is_online TINYINT(1) DEFAULT 0
);

CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player1_id INT,
    player2_id INT,
    status ENUM('waiting', 'active', 'finished') DEFAULT 'waiting',
    trump_suit VARCHAR(10),
    FOREIGN KEY (player1_id) REFERENCES players(id),
    FOREIGN KEY (player2_id) REFERENCES players(id)
);

CREATE TABLE game_state (
    game_id INT,
    player_id INT,
    hand TEXT, -- JSON-encoded array of cards
    table TEXT, -- JSON-encoded attack/defense cards
    is_turn TINYINT(1) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);