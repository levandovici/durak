<?php
include 'config.php';
session_start();

// Проверка, авторизован ли игрок
if (!isset($_SESSION['player_id'])) {
    if (isset($_POST['username'])) {
        $username = $_POST['username'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_ENV['APP_URL'] . '/join_game.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => $username]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if (isset($data['player_id'])) {
            $_SESSION['player_id'] = $data['player_id'];
            $_SESSION['game_id'] = $data['game_id'];
            $_SESSION['session_id'] = $data['session_id'];
        }
    }
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
            background-color: #2e7d32; /* Зеленый фон стола */
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
        #turn-indicator {
            color: white;
            font-size: 24px;
            margin: 10px 0;
        }
        #trump-suit {
            color: white;
            font-size: 18px;
            margin: 5px 0;
        }
        #table-area {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin: 20px 0;
            min-height: 150px;
        }
        #hand-area {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin: 20px 0;
        }
        .card {
            width: 80px;
            height: 120px;
            margin: 5px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: scale(1.05);
        }
        #login-form {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 10px;
        }
        #login-form input, #login-form button {
            margin: 10px;
            padding: 5px;
            font-size: 16px;
        }
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
    </div>

    <script>
        <?php if (isset($_SESSION['player_id'])): ?>
        const gameId = <?php echo $_SESSION['game_id']; ?>;
        const playerId = <?php echo $_SESSION['player_id']; ?>;
        const sessionId = '<?php echo $_SESSION['session_id']; ?>';

        // Получение состояния игры
        function pollGameState() {
            fetch('get_game_state.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `game_id=${gameId}&player_id=${playerId}&session_id=${sessionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(data.error);
                    return;
                }
                updateUI(data);
            })
            .catch(error => console.error('Poll error:', error));
        }

        // Обновление UI
        function updateUI(state) {
            // Turn indicator
            const currentPlayer = state.state.find(s => s.is_turn);
            document.getElementById('turn-indicator').textContent = `Turn: Player ${currentPlayer ? currentPlayer.player_id : 'Unknown'}`;

            // Trump suit
            document.getElementById('trump-suit').textContent = `Trump: ${state.trump_suit}`;

            // Table area
            const tableArea = document.getElementById('table-area');
            tableArea.innerHTML = '';
            const opponentState = state.state.find(s => s.player_id !== playerId);
            if (opponentState && opponentState.table) {
                const tableCards = JSON.parse(opponentState.table);
                tableCards.forEach(card => {
                    const img = document.createElement('img');
                    img.src = `cards/${card.suit}_${card.rank}.png`;
                    img.className = 'card';
                    tableArea.appendChild(img);
                });
            }

            // Hand area
            const handArea = document.getElementById('hand-area');
            handArea.innerHTML = '';
            const playerState = state.state.find(s => s.player_id === playerId);
            if (playerState && playerState.hand) {
                const handCards = JSON.parse(playerState.hand);
                handCards.forEach(card => {
                    const img = document.createElement('img');
                    img.src = `cards/${card.suit}_${card.rank}.png`;
                    img.className = 'card';
                    img.onclick = () => playCard(card);
                    handArea.appendChild(img);
                });
            }
        }

        // Отправка карты на сервер
        function playCard(card) {
            const formData = new FormData();
            formData.append('game_id', gameId);
            formData.append('player_id', playerId);
            formData.append('session_id', sessionId);
            formData.append('card', JSON.stringify(card));

            fetch('play_card.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(data.redirected ? 'Card redirected!' : 'Card played!');
                    pollGameState(); // Обновить состояние после хода
                } else {
                    console.error(data.error);
                }
            })
            .catch(error => console.error('Play error:', error));
        }

        // Запуск опроса состояния
        setInterval(pollGameState, 2000); // Каждые 2 секунды
        pollGameState(); // Немедленный первый вызов
        <?php endif; ?>
    </script>
</body>
</html>