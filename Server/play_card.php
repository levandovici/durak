<?php
include 'config.php';
header('Content-Type: application/json');

$game_id = $_POST['game_id'] ?? 0;
$player_id = $_POST['player_id'] ?? 0;
$session_id = $_POST['session_id'] ?? '';
$card = json_decode($_POST['card'] ?? '{}', true);

// Validate session
$stmt = $pdo->prepare("SELECT session_id FROM players WHERE id = ?");
$stmt->execute([$player_id]);
if ($stmt->fetchColumn() !== $session_id) {
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

if (!$game_id || !$player_id || empty($card)) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Check turn
$stmt = $pdo->prepare("SELECT is_turn FROM game_state WHERE game_id = ? AND player_id = ?");
$stmt->execute([$game_id, $player_id]);
$is_turn = $stmt->fetchColumn();

if (!$is_turn) {
    echo json_encode(['error' => 'Not your turn']);
    exit;
}

// Update table and switch turns
$stmt = $pdo->prepare("UPDATE game_state SET table = JSON_ARRAY_APPEND(table, '$', ?) WHERE game_id = ? AND player_id = ?");
$stmt->execute([json_encode($card), $game_id, $player_id]);

$stmt = $pdo->prepare("UPDATE game_state SET is_turn = 0 WHERE game_id = ? AND player_id = ?");
$stmt->execute([$game_id, $player_id]);
$stmt = $pdo->prepare("UPDATE game_state SET is_turn = 1 WHERE game_id = ? AND player_id != ?");
$stmt->execute([$game_id, $player_id]);

echo json_encode(['success' => true]);
?>