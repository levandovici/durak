<?php
include 'config.php';
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "join_game.php started\n", FILE_APPEND);

$username = $_POST['username'] ?? '';
$session_id = uniqid();

if (empty($username)) {
    echo json_encode(['error' => 'Username required']);
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Error: Username required\n", FILE_APPEND);
    exit;
}

// Register or update player
try {
    $stmt = $pdo->prepare("INSERT INTO players (username, session_id, is_online) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_online = 1, session_id = ?");
    $stmt->execute([$username, $session_id, $session_id]);
    $player_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM players WHERE username='$username'")->fetchColumn();
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Player registered: $player_id\n", FILE_APPEND);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
    exit;
}

// Find or create game
try {
    $stmt = $pdo->prepare("SELECT g.id, COUNT(gp.player_id) as player_count FROM games g LEFT JOIN game_players gp ON g.id = gp.game_id WHERE g.status = 'waiting' GROUP BY g.id HAVING player_count < g.max_players LIMIT 1");
    $stmt->execute();
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        $stmt = $pdo->prepare("INSERT INTO games (status, trump_suit) VALUES ('waiting', ?)");
        $suits = ['Spades', 'Hearts', 'Diamonds', 'Clubs'];
        $stmt->execute([$suits[array_rand($suits)]);
        $game_id = $pdo->lastInsertId();
        $player_count = 0;
    } else {
        $game_id = $game['id'];
        $player_count = $game['player_count'];
    }
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Game found/created: $game_id\n", FILE_APPEND);
} catch (Exception $e) {
    echo json_encode(['error' => 'Game creation error: ' . $e->getMessage()]);
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Game Error: " . $e->getMessage() . "\n", FILE_APPEND);
    exit;
}

// Add player to game
try {
    $stmt = $pdo->prepare("INSERT INTO game_players (game_id, player_id, turn_order) VALUES (?, ?, ?)");
    $stmt->execute([$game_id, $player_id, $player_count + 1]);
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Player added to game: $player_id\n", FILE_APPEND);
} catch (Exception $e) {
    echo json_encode(['error' => 'Player join error: ' . $e->getMessage()]);
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Join Error: " . $e->getMessage() . "\n", FILE_APPEND);
    exit;
}

// Initialize game_state
try {
    $stmt = $pdo->prepare("INSERT INTO game_state (game_id, player_id, hand, `table`, is_turn) VALUES (?, ?, '[]', '[]', 0)");
    $stmt->execute([$game_id, $player_id]);
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Game state initialized for player: $player_id\n", FILE_APPEND);
} catch (Exception $e) {
    echo json_encode(['error' => 'Game state init error: ' . $e->getMessage()]);
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "State Error: " . $e->getMessage() . "\n", FILE_APPEND);
    exit;
}

// Check if game is full
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM game_players WHERE game_id = ?");
    $stmt->execute([$game_id]);
    $num_players = $stmt->fetchColumn();
    if ($num_players >= 2 && $num_players <= 6) {
        $stmt = $pdo->prepare("UPDATE games SET status = 'active' WHERE id = ?");
        $stmt->execute([$game_id]);
        $stmt = $pdo->prepare("UPDATE game_state SET is_turn = 1 WHERE game_id = ? AND player_id = (SELECT player_id FROM game_players WHERE game_id = ? AND turn_order = 1)");
        $stmt->execute([$game_id, $game_id]);
        file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Game activated: $game_id\n", FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_ENV['APP_URL'] . '/durak/init_game.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['game_id' => $game_id]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Init game response: $response\n", FILE_APPEND);
        curl_close($ch);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Game activation error: ' . $e->getMessage()]);
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Activation Error: " . $e->getMessage() . "\n", FILE_APPEND);
    exit;
}

echo json_encode(['game_id' => $game_id, 'player_id' => $player_id, 'session_id' => $session_id]);
file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . "Success: GameID=$game_id, PlayerID=$player_id\n", FILE_APPEND);
?>