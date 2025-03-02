<?php
include 'config.php';
header('Content-Type: application/json');

$username = $_POST['username'] ?? '';
$session_id = uniqid(); // Generate session ID for this player

if (empty($username)) {
    echo json_encode(['error' => 'Username required']);
    exit;
}

// Register or update player
$stmt = $pdo->prepare("INSERT INTO players (username, session_id, is_online) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_online = 1, session_id = ?");
$stmt->execute([$username, $session_id, $session_id]);

$player_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM players WHERE username='$username'")->fetchColumn();

// Find or create game
$stmt = $pdo->prepare("SELECT id FROM games WHERE status = 'waiting' AND player1_id != ? LIMIT 1");
$stmt->execute([$player_id]);
$game_id = $stmt->fetchColumn();

if (!$game_id) {
    $stmt = $pdo->prepare("INSERT INTO games (player1_id, status, trump_suit) VALUES (?, 'waiting', ?)");
    $suits = ['Spades', 'Hearts', 'Diamonds', 'Clubs'];
    $trump = $suits[array_rand($suits)];
    $stmt->execute([$player_id, $trump]);
    $game_id = $pdo->lastInsertId();
} else {
    $stmt = $pdo->prepare("UPDATE games SET player2_id = ?, status = 'active' WHERE id = ?");
    $stmt->execute([$player_id, $game_id]);
}

echo json_encode([
    'game_id' => $game_id,
    'player_id' => $player_id,
    'session_id' => $session_id
]);
?>