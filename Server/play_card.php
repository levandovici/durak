<?php
include 'config.php';
header('Content-Type: application/json');

$game_id = $_POST['game_id'] ?? 0;
$player_id = $_POST['player_id'] ?? 0;
$session_id = $_POST['session_id'] ?? '';
$card = json_decode($_POST['card'] ?? '{}', true);

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

$stmt = $pdo->prepare("SELECT is_turn, hand FROM game_state WHERE game_id = ? AND player_id = ?");
$stmt->execute([$game_id, $player_id]);
$player_state = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player_state['is_turn']) {
    echo json_encode(['error' => 'Not your turn']);
    exit;
}

$hand = json_decode($player_state['hand'] ?: '[]', true);
if (!in_array($card, $hand)) {
    echo json_encode(['error' => 'Card not in hand']);
    exit;
}

$stmt = $pdo->prepare("SELECT trump_suit FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$trump_suit = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT gp.turn_order, gs.`table` FROM game_players gp JOIN game_state gs ON gp.player_id = gs.player_id WHERE gp.game_id = ? AND gp.turn_order = (SELECT turn_order - 1 FROM game_players WHERE game_id = ? AND player_id = ?) OR (SELECT turn_order - 1 FROM game_players WHERE game_id = ? AND player_id = ?) = 0 AND gp.turn_order = (SELECT MAX(turn_order) FROM game_players WHERE game_id = ?)");
$stmt->execute([$game_id, $game_id, $player_id, $game_id, $player_id, $game_id]);
$prev_player = $stmt->fetch(PDO::FETCH_ASSOC);
$last_attack = $prev_player ? json_decode($prev_player['table'] ?: '[]', true) : [];
$last_attack_card = end($last_attack);

if ($last_attack_card) {
    $can_defend = canDefend($card, $last_attack_card, $trump_suit);
    $can_redirect = $card['rank'] === $last_attack_card['rank'];

    if (!$can_defend && !$can_redirect) {
        echo json_encode(['error' => 'Invalid move']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE game_state SET `table` = JSON_ARRAY_APPEND(`table`, '$', ?), hand = JSON_REMOVE(hand, JSON_UNQUOTE(JSON_SEARCH(hand, 'one', ?))) WHERE game_id = ? AND player_id = ?");
    $stmt->execute([json_encode($card), json_encode($card), $game_id, $player_id]);

    $stmt = $pdo->prepare("SELECT turn_order FROM game_players WHERE game_id = ? AND player_id = ?");
    $stmt->execute([$game_id, $player_id]);
    $current_turn_order = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM game_players WHERE game_id = ?");
    $stmt->execute([$game_id]);
    $max_players = $stmt->fetchColumn();

    $next_turn_order = $can_redirect ? $prev_player['turn_order'] : (($current_turn_order % $max_players) + 1);

    $stmt = $pdo->prepare("UPDATE game_state SET is_turn = 0 WHERE game_id = ?");
    $stmt->execute([$game_id]);
    $stmt = $pdo->prepare("UPDATE game_state SET is_turn = 1 WHERE game_id = ? AND player_id = (SELECT player_id FROM game_players WHERE game_id = ? AND turn_order = ?)");
    $stmt->execute([$game_id, $game_id, $next_turn_order]);

    echo json_encode(['success' => true, 'redirected' => $can_redirect]);
} else {
    $stmt = $pdo->prepare("UPDATE game_state SET `table` = JSON_ARRAY_APPEND(`table`, '$', ?), hand = JSON_REMOVE(hand, JSON_UNQUOTE(JSON_SEARCH(hand, 'one', ?))) WHERE game_id = ? AND player_id = ?");
    $stmt->execute([json_encode($card), json_encode($card), $game_id, $player_id]);

    $stmt = $pdo->prepare("SELECT turn_order FROM game_players WHERE game_id = ? AND player_id = ?");
    $stmt->execute([$game_id, $player_id]);
    $current_turn_order = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM game_players WHERE game_id = ?");
    $stmt->execute([$game_id]);
    $max_players = $stmt->fetchColumn();

    $next_turn_order = ($current_turn_order % $max_players) + 1;

    $stmt = $pdo->prepare("UPDATE game_state SET is_turn = 0 WHERE game_id = ?");
    $stmt->execute([$game_id]);
    $stmt = $pdo->prepare("UPDATE game_state SET is_turn = 1 WHERE game_id = ? AND player_id = (SELECT player_id FROM game_players WHERE game_id = ? AND turn_order = ?)");
    $stmt->execute([$game_id, $game_id, $next_turn_order]);

    echo json_encode(['success' => true, 'redirected' => false]);
}

function canDefend($defense, $attack, $trump_suit) {
    $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    $defense_value = array_search($defense['rank'], $ranks);
    $attack_value = array_search($attack['rank'], $ranks);

    return ($defense['suit'] === $attack['suit'] && $defense_value > $attack_value) ||
           ($defense['suit'] === $trump_suit && $attack['suit'] !== $trump_suit);
}
?>