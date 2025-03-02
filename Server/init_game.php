<?php
include 'config.php';
header('Content-Type: application/json');

$game_id = $_POST['game_id'] ?? 0;

if (!$game_id) {
    echo json_encode(['error' => 'Invalid game ID']);
    exit;
}

// Проверка статуса игры
$stmt = $pdo->prepare("SELECT status FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$status = $stmt->fetchColumn();

if ($status !== 'active') {
    echo json_encode(['error' => 'Game is not active']);
    exit;
}

// Генерация колоды
$suits = ['Spades', 'Hearts', 'Diamonds', 'Clubs'];
$ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
$deck = [];
foreach ($suits as $suit) {
    foreach ($ranks as $rank) {
        $deck[] = ['suit' => $suit, 'rank' => $rank];
    }
}
shuffle($deck);

// Получение игроков
$stmt = $pdo->prepare("SELECT player_id FROM game_players WHERE game_id = ? ORDER BY turn_order");
$stmt->execute([$game_id]);
$players = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Раздача карт
$hands = array_fill_keys($players, []);
for ($i = 0; $i < 6 * count($players); $i++) {
    $player_index = $i % count($players);
    $hands[$players[$player_index]][] = array_shift($deck);
}

// Обновление trump_suit (последняя карта в колоде)
$trump_suit = end($deck)['suit'];
$stmt = $pdo->prepare("UPDATE games SET trump_suit = ? WHERE id = ?");
$stmt->execute([$trump_suit, $game_id]);

// Сохранение рук в game_state
foreach ($hands as $player_id => $hand) {
    $stmt = $pdo->prepare("UPDATE game_state SET hand = ? WHERE game_id = ? AND player_id = ?");
    $stmt->execute([json_encode($hand), $game_id, $player_id]);
}

echo json_encode(['success' => true, 'trump_suit' => $trump_suit]);
?>