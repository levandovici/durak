<?php
include 'config.php';
header('Content-Type: application/json');

$game_id = $_POST['game_id'] ?? 0;
$player_id = $_POST['player_id'] ?? 0;
$session_id = $_POST['session_id'] ?? '';

// Validate session
$stmt = $pdo->prepare("SELECT session_id FROM players WHERE id = ?");
$stmt->execute([$player_id]);
if ($stmt->fetchColumn() !== $session_id) {
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

if (!$game_id || !$player_id) {
    echo json_encode(['error' => 'Invalid game or player ID']);
    exit;
}

// Get game info
$stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    echo json_encode(['error' => 'Game not found']);
    exit;
}

// Get player hands and table
$stmt = $pdo->prepare("SELECT player_id, hand, table, is_turn FROM game_state WHERE game_id = ?");
$stmt->execute([$game_id]);
$state = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => $game['status'],
    'trump_suit' => $game['trump_suit'],
    'players' => [$game['player1_id'], $game['player2_id']],
    'state' => $state
]);
?>