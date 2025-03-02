<?php
include 'config.php';
header('Content-Type: application/json');

$username = $_POST['username'] ?? '';
$session_id = uniqid();

if (empty($username)) {
    echo json_encode(['error' => 'Username required']);
    exit;
}

// Register or update player
$stmt = $pdo->prepare("INSERT INTO players (username, session_id, is_online) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_online = 1, session_id = ?");
$stmt->execute([$username, $session_id, $session_id]);
$player_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM players WHERE username='$username'")->fetchColumn();

// Find or create game
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

// Add player to game
$stmt = $pdo->prepare("INSERT INTO game_players (game_id, player_id, turn_order) VALUES (?, ?, ?)");
$stmt->execute([$game_id, $player_id, $player_count + 1]);

// Initialize game_state for this player
$stmt = $pdo->prepare("INSERT INTO game_state (game_id, player_id, hand, `table`, is_turn) VALUES (?, ?, '[]', '[]', 0)");
$stmt->execute([$game_id, $player_id]);

// Check if game is full
$stmt = $pdo->prepare("SELECT COUNT(*) FROM game_players WHERE game_id = ?");
$stmt->execute([$game_id]);
$num_players = $stmt->fetchColumn();
if ($num_players >= 2 && $num_players <= 6) { // Start with 2+ players
    $stmt = $pdo->prepare("UPDATE games SET status = 'active' WHERE id = ?");
    $stmt->execute([$game_id]);
    $stmt = $pdo->prepare("UPDATE game_state SET is_turn = 1 WHERE game_id = ? AND player_id = (SELECT player_id FROM game_players WHERE game_id = ? AND turn_order = 1)");
    $stmt->execute([$game_id, $game_id]);
}

echo json_encode(['game_id' => $game_id, 'player_id' => $player_id, 'session_id' => $session_id]);
?>