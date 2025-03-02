<?php
include 'config.php';
session_start();

// Включение отладки PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log'); // Логи будут записываться в debug.log в public_html

// Функция для записи отладочных сообщений
function debug_log($message) {
    file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

debug_log("Script started");

// Проверка авторизации игрока
if (!isset($_SESSION['player_id'])) {
    if (isset($_POST['username'])) {
        $username = $_POST['username'];
        debug_log("Username submitted: $username");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_ENV['APP_URL'] . '/join_game.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => $username]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true); // Включаем подробный вывод CURL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Временно отключаем проверку SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Для теста

        // Захват вывода CURL для отладки
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        $response = curl_exec($ch);
        if ($response === false) {
            $curl_error = curl_error($ch);
            debug_log("CURL Error: $curl_error");
            echo "CURL Error: $curl_error";
        } else {
            $data = json_decode($response, true);
            debug_log("CURL Response: " . print_r($data, true));
            if (isset($data['player_id'])) {
                $_SESSION['player_id'] = $data['player_id'];
                $_SESSION['game_id'] = $data['game_id'];
                $_SESSION['session_id'] = $data['session_id'];
                debug_log("Player logged in: ID=" . $data['player_id'] . ", Game=" . $data['game_id']);
            } else {
                debug_log("Join Game failed: " . $response);
                echo "Join Game Error: " . htmlspecialchars($response);
            }
        }

        // Вывод отладочной информации CURL
        rewind($verbose);
        $verbose_log = stream_get_contents($verbose);
        debug_log("CURL Verbose Output: " . $verbose_log);
        fclose($verbose);

        curl_close($ch);
    }
} else {
    debug_log("Session active: PlayerID=" . $_SESSION['player_id'] . ", GameID=" . $_SESSION['game_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Durak Web Game</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #2e7d32;
            margin: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 600px;
            padding: 10px;
        }
        #turn-indicator { color: white; font-size: 24px; margin: 10px 0; }
        #trump-suit { color: white; font-size: 18px; margin: 5px 0; }
        #table-area { display: flex; flex-wrap: wrap; justify-content: center; margin: 20px 0; min-height: 150px; }
        #hand-area { display: flex; flex-wrap: wrap; justify-content: center; margin: 20px 0; }
        .card { width: 80px; height: 120px; margin: 5px; cursor: pointer; transition: transform 0.2s; }
        .card:hover { transform: scale(1.05); }
        #login-form { display: flex; flex-direction: column; align-items: center; background-color: rgba(255, 255, 255, 0.8); padding: 20px; border-radius: 10px; }
        #login-form input, #login-form button { margin: 10px; padding: 5px; font-size: 16px; }
        #debug-output { color: red; font-size: 14px; margin-top: 20px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['player_id'])): ?>
            <form id="login-form" method="POST">
                <input type="text" name="username" placeholder="Enter your username" required>
                <button type="submit">Join Game</button>
            </form>
        <?php else: ?>
            <div id="turn-indicator">Turn: Loading...</div>
            <div id="trump-suit">Trump: Loading...</div>
            <div id="table-area"></div>
            <div id="hand-area"></div>
        <?php endif; ?>
        <div id="debug-output"></div>
    </div>

    <script>
        <?php if (isset($_SESSION['player_id'])): ?>
        const gameId = <?php echo $_SESSION['game_id']; ?>;
        const playerId = <?php echo $_SESSION['player_id']; ?>;
        const sessionId = '<?php echo $_SESSION['session_id']; ?>';
        const BASE_URL = 'https://cloud.limonadoent.com';

        function logDebug(message) {
            console.log(message);
            const debugOutput = document.getElementById('debug-output');
            debugOutput.textContent += message + '\n';
        }

        function pollGameState() {
            logDebug('Polling game state...');
            fetch(BASE_URL + '/get_game_state.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `game_id=${gameId}&player_id=${playerId}&session_id=${sessionId}`
            })
            .then(response => {
                logDebug('Fetch response status: ' + response.status);
                return response.text(); // Сначала получаем как текст для отладки
            })
            .then(text => {
                logDebug('Raw response: ' + text);
                const data = JSON.parse(text);
                if (data.error) {
                    logDebug('Game state error: ' + data.error);
                    return;
                }
                updateUI(data);
            })
            .catch(error => logDebug('Poll error: ' + error));
        }

        function updateUI(state) {
            logDebug('Updating UI with state: ' + JSON.stringify(state));
            const currentPlayer = state.state.find(s => s.is_turn);
            document.getElementById('turn-indicator').textContent = `Turn: Player ${currentPlayer ? currentPlayer.player_id : 'Unknown'}`;
            document.getElementById('trump-suit').textContent = `Trump: ${state.trump_suit}`;

            const tableArea = document.getElementById('table-area');
            tableArea.innerHTML = '';
            const opponentState = state.state.find(s => s.player_id !== playerId);
            if (opponentState && opponentState.table) {
                const tableCards = JSON.parse(opponentState.table);
                logDebug('Table cards: ' + JSON.stringify(tableCards));
                tableCards.forEach(card => {
                    const img = document.createElement('img');
                    img.src = `cards/${card.suit}_${card.rank}.png`;
                    img.className = 'card';
                    tableArea.appendChild(img);
                });
            }

            const handArea = document.getElementById('hand-area');
            handArea.innerHTML = '';
            const playerState = state.state.find(s => s.player_id === playerId);
            if (playerState && playerState.hand) {
                const handCards = JSON.parse(playerState.hand);
                logDebug('Hand cards: ' + JSON.stringify(handCards));
                handCards.forEach(card => {
                    const img = document.createElement('img');
                    img.src = `cards/${card.suit}_${card.rank}.png`;
                    img.className = 'card';
                    img.onclick = () => playCard(card);
                    handArea.appendChild(img);
                });
            }
        }

        function playCard(card) {
            logDebug('Playing card: ' + JSON.stringify(card));
            const formData = new FormData();
            formData.append('game_id', gameId);
            formData.append('player_id', playerId);
            formData.append('session_id', sessionId);
            formData.append('card', JSON.stringify(card));

            fetch(BASE_URL + '/play_card.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                logDebug('Play card response status: ' + response.status);
                return response.text();
            })
            .then(text => {
                logDebug('Play card raw response: ' + text);
                const data = JSON.parse(text);
                if (data.success) {
                    logDebug(data.redirected ? 'Card redirected!' : 'Card played!');
                    pollGameState();
                } else {
                    logDebug('Play card error: ' + data.error);
                }
            })
            .catch(error => logDebug('Play error: ' + error));
        }

        setInterval(pollGameState, 2000);
        pollGameState();
        <?php endif; ?>
    </script>
</body>
</html>