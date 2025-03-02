<?php
include 'config.php';

// Включение отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Функция для отправки POST-запроса через CURL
function sendPostRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Временно для теста
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    if ($response === false) {
        echo "CURL Error: " . curl_error($ch) . "\n";
    }
    curl_close($ch);
    return $response;
}

// Начало теста
echo "=== Durak Server Logic Test ===\n";

// 1. Тест подключения к базе данных
echo "\n1. Database Connection Test\n";
try {
    $pdo->query("SELECT 1");
    echo "Database connection successful.\n";
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit;
}

// 2. Тест join_game.php
echo "\n2. Join Game Test\n";
$username = "TestPlayer" . rand(1000, 9999); // Уникальный тестовый пользователь
$response = sendPostRequest($_ENV['APP_URL'] . '/join_game.php', ['username' => $username]);
echo "Raw response: $response\n";
$joinData = json_decode($response, true);
if (isset($joinData['game_id']) && isset($joinData['player_id']) && isset($joinData['session_id'])) {
    $gameId = $joinData['game_id'];
    $playerId = $joinData['player_id'];
    $sessionId = $joinData['session_id'];
    echo "Joined game successfully: GameID=$gameId, PlayerID=$playerId, SessionID=$sessionId\n";
} else {
    echo "Failed to join game: " . (isset($joinData['error']) ? $joinData['error'] : 'Unknown error') . "\n";
    exit;
}

// 3. Тест init_game.php (добавление второго игрока для активации)
echo "\n3. Init Game Test\n";
$username2 = "TestPlayer" . rand(1000, 9999);
$response = sendPostRequest($_ENV['APP_URL'] . '/join_game.php', ['username' => $username2]);
echo "Raw response (second player): $response\n";
$joinData2 = json_decode($response, true);
if (isset($joinData2['player_id'])) {
    echo "Second player joined: PlayerID=" . $joinData2['player_id'] . "\n";
    // Инициализация игры
    $response = sendPostRequest($_ENV['APP_URL'] . '/init_game.php', ['game_id' => $gameId]);
    echo "Init response: $response\n";
    $initData = json_decode($response, true);
    if (isset($initData['success']) && $initData['success']) {
        echo "Game initialized successfully. Trump suit: " . $initData['trump_suit'] . "\n";
    } else {
        echo "Failed to initialize game: " . (isset($initData['error']) ? $initData['error'] : 'Unknown error') . "\n";
        exit;
    }
} else {
    echo "Failed to join second player: " . (isset($joinData2['error']) ? $joinData2['error'] : 'Unknown error') . "\n";
    exit;
}

// 4. Тест get_game_state.php
echo "\n4. Get Game State Test\n";
$response = sendPostRequest($_ENV['APP_URL'] . '/get_game_state.php', [
    'game_id' => $gameId,
    'player_id' => $playerId,
    'session_id' => $sessionId
]);
echo "Raw response: $response\n";
$stateData = json_decode($response, true);
if (isset($stateData['status']) && $stateData['status'] === 'active') {
    echo "Game state retrieved: Status=" . $stateData['status'] . ", Trump=" . $stateData['trump_suit'] . "\n";
    echo "Player hand: " . print_r(json_decode($stateData['state'][0]['hand'], true), true) . "\n";
} else {
    echo "Failed to get game state: " . (isset($stateData['error']) ? $stateData['error'] : 'Unknown error') . "\n";
    exit;
}

// 5. Тест play_card.php
echo "\n5. Play Card Test\n";
$hand = json_decode($stateData['state'][0]['hand'], true);
$cardToPlay = $hand[0]; // Берем первую карту из руки
$response = sendPostRequest($_ENV['APP_URL'] . '/play_card.php', [
    'game_id' => $gameId,
    'player_id' => $playerId,
    'session_id' => $sessionId,
    'card' => json_encode($cardToPlay)
]);
echo "Raw response: $response\n";
$playData = json_decode($response, true);
if (isset($playData['success']) && $playData['success']) {
    echo "Card played successfully: " . json_encode($cardToPlay) . ", Redirected=" . ($playData['redirected'] ? 'Yes' : 'No') . "\n";
} else {
    echo "Failed to play card: " . (isset($playData['error']) ? $playData['error'] : 'Unknown error') . "\n";
}

// Завершение теста
echo "\n=== Test Completed ===\n";
?>