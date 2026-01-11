const GAME_STATES = {
    WAITING: 'waiting_for_registration',
    RUNNING: 'running',
    PAUSED: 'paused',
    STOPPED: 'stopped',
    RESET: 'reset'
};

const WebSocket = require('ws');
const wss = new WebSocket.Server({ port: 8080 });
const hostClients = new Map(); // Отдельный набор для хостов

console.log('WebSocket server is running on ws://localhost:8080');

// Хранилище для игр: ключ — идентификатор игры, значение — объект с состоянием игры
const games = new Map(); // { gameId: { state, clients, hostClients, fastestPlayer } }

wss.on('connection', (ws) => {
    console.log('New client connected');

    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);
            console.log('Received:', data);

            const { type, gameId, isHost } = data;

            if (isHost) { // Команда от управляющего
                switch (type) {
                    case 'getPlayers':
                        sendPlayersListToHosts(ws, gameId);
                        break;
                    case 'getGames':
                        getGames(ws);
                        break;
                    case 'create_game':
                        createGame(ws, data);
                        break;
                    case 'deleteGame':
                        deleteGame(ws, data);
                        break;
                    case 'startGame':
                        startGame(ws, data);
                        break;
                    case 'registrationMode':
                        registrationMode(ws, data);
                        break;
                    case 'stopGame':
                        stopGame(ws, data);
                        break;
                    case 'newRound':
                        newRound(ws, data);
                        break;
                    case 'registrationHost':
                        joinHostToGame(ws, data);
                        break;
                    case 'deletePlayer':
                        deletePlayer(ws, data);
                        break;
                    default:
                        ws.send(JSON.stringify({ type: 'error', message: `Неизвестная команда от хоста (${type})` }));
                }
            } else { // Команда от игрока
                switch (type) {
                    case 'getGames':
                        getGames(ws);
                        break;
                    case 'joinGame':
                        joinGame(ws, data);
                        break;
                    case 'fire':
                        fire(ws, data);
                        break;
                    default:
                        handleGameMessage(ws, data);
                }
            }
        } catch (error) {
            console.error('Error parsing message:', error);
            ws.send(JSON.stringify({ type: 'error', message: 'Некорректное сообщение' }));
        }
    });

    ws.on('close', () => {
        handleClientDisconnect(ws);
    });
});

// Отправка списка игроков ведущим
function sendPlayersListToHosts(ws, gameId) {
    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const game = games.get(gameId);
    if (!game.hostClients || game.hostClients.size === 0) {
        console.log(`В игре с ID ${gameId} нет ведущих`);
        ws.send(JSON.stringify({ type: 'error', message: `В игре с ID ${gameId} нет ведущих` }));
        return;
    }

    const playerList = Array.from(game.clients.values()).map(client => client.name);
    console.log(`Обновление списка игроков для игры ${gameId}. Клиентов: ${game.clients.size}`);

    const data = JSON.stringify({
        type: 'playerList',
        gameId: gameId,
        players: playerList
    });

    game.hostClients.forEach((hostClient, hostWs) => {
        if (hostWs.readyState === WebSocket.OPEN) {
            try {
                hostWs.send(data);
                console.log(`Список игроков отправлен хосту в игре ${gameId}`);
            } catch (error) {
                console.error(`Ошибка при отправке списка игроков хосту в игре ${gameId}:`, error);
            }
        }
    });
}

// Создание новой игры
function createGame(ws, data) {
    const { gameId, name } = data;

    if (games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} уже существует` }));
        return;
    }

    games.set(gameId, {
        state: GAME_STATES.WAITING,
        clients: new Map(),
        hostClients: new Map(),
        fastestPlayer: null
    });

    ws.send(JSON.stringify({
        type: 'game_created',
        message: `Игра ${gameId} создана.`,
        gameId: gameId
    }));
}

// Удаление игрока
function deletePlayer(ws, data) {
    const { gameId, name } = data;

    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const game = games.get(gameId);
    let playerWebSocket = null;

    for (const [clientWs, client] of game.clients) {
        if (client.name === name) {
            playerWebSocket = clientWs;
            break;
        }
    }

    if (playerWebSocket) {
        game.clients.delete(playerWebSocket);
        ws.send(JSON.stringify({ type: 'playerDeleted', message: `Игрок ${name} был удалён` }));
        sendPlayersListToHosts(ws, gameId);
    } else {
        ws.send(JSON.stringify({ type: 'error', message: `Игрок ${name} не найден` }));
    }
}

// Подключение игрока к игре
function joinGame(ws, data) {
    const { gameId, name } = data;

    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const game = games.get(gameId);

    if (game.state !== GAME_STATES.WAITING) {
        ws.send(JSON.stringify({ type: 'reject', message: 'Идёт игра. Регистрация невозможна.' }));
        return;
    }

    const isNameTaken = Array.from(game.clients.values()).some(client => client.name === name);
    if (isNameTaken) {
        ws.send(JSON.stringify({ type: 'name_taken', message: 'Это имя уже занято. Выберите другое.' }));
        return;
    }

    game.clients.set(ws, { name: name, ready: true, hasAnswered: false });
    ws.send(JSON.stringify({
        type: 'registered',
        message: `Добро пожаловать, ${name}. Ожидаем начала игры. Игра: ${gameId}`,
        gameId: gameId
    }));

    sendPlayersListToHosts(ws, gameId);
}

// Подключение ведущего к игре
function joinHostToGame(ws, data) {
    const { gameId, name, isHost } = data;

    if (isHost === undefined || !isHost) {
        ws.send(JSON.stringify({ type: 'error', message: `Отсутствует статус хоста` }));
        return;
    }

    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const game = games.get(gameId);
    const isNameTaken = Array.from(game.hostClients.values()).some(client => client.name === name);

    if (isNameTaken) {
        ws.send(JSON.stringify({ type: 'name_taken', message: 'Это имя уже занято. Выберите другое.' }));
        return;
    }

    game.hostClients.set(ws, { name: name });
    ws.send(JSON.stringify({
        type: 'registered',
        message: `Добро пожаловать, ${name}. Вы ведущий в игре: ${gameId}`
    }));

    sendPlayersListToHosts(ws, gameId);
}

// Получение списка игр
function getGames(ws) {
    const gameList = Array.from(games.keys());
    ws.send(JSON.stringify({ type: 'gameList', games: gameList }));
}

// Обработка сообщений от клиентов
function handleGameMessage(ws, data) {
    const { gameId, type } = data;
    const game = games.get(gameId);

    if (!game) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const clientData = game.clients.get(ws);

    switch (type) {
        case 'get_game_state':
            ws.send(JSON.stringify({ type: 'game_state', message: game.state }));
            break;
        case 'fire':
            if (clientData && game.state === GAME_STATES.RUNNING && !game.fastestPlayer && !clientData.hasAnswered) {
                game.fastestPlayer = clientData.name;
                clientData.hasAnswered = true;
                broadcastToAllInGame(gameId, JSON.stringify({ type: 'fastest', fastest: game.fastestPlayer }));
                broadcastToHosts(gameId, JSON.stringify({ type: 'fastest', fastest: game.fastestPlayer }));
            }
            break;
        default:
            ws.send(JSON.stringify({ type: 'error', message: `Неизвестная команда (${type})` }));
    }
}

// Обработка отключения клиента
function handleClientDisconnect(ws) {
    for (const [gameId, game] of games) {
        if (game.clients.has(ws)) {
            const client = game.clients.get(ws);
            console.log(`Player disconnected from game ${gameId}: ${client.name}`);
            game.clients.delete(ws);
            sendPlayersListToHosts(ws, gameId);
            break;
        }
        if (game.hostClients.has(ws)) {
            const client = game.hostClients.get(ws);
            console.log(`Host disconnected from game ${gameId}: ${client.name}`);
            game.hostClients.delete(ws);
            sendPlayersListToHosts(ws, gameId);
            break;
        }
    }
}

// Запуск игры
function startGame(ws, data) {
    const { gameId } = data;
    const game = games.get(gameId);

    if (!game) return;

    game.state = GAME_STATES.RUNNING;
    game.fastestPlayer = null;

    broadcastToAllInGame(gameId, JSON.stringify({ type: 'play', message: 'Играем!', gameId: gameId }));
    ws.send(JSON.stringify({ type: 'gameRunning', gameId: gameId }));
}

// Пауза игры
function pauseGame(ws, data) {
    const { gameId } = data;
    const game = games.get(gameId);

    if (!game) return;

    game.fastestPlayer = null;
    game.state = GAME_STATES.PAUSED;

    broadcastToAllInGame(gameId, JSON.stringify({ type: 'pause', message: 'Игра на паузе...', gameId: gameId }));
    ws.send(JSON.stringify({ type: 'gamePaused', gameId: gameId }));
}

// Остановка игры
function stopGame(ws, data) {
    const { gameId } = data;
    const game = games.get(gameId);

    if (!game) return;

    game.state = GAME_STATES.STOPPED;
    game.fastestPlayer = null;

    broadcastToAllInGame(gameId, JSON.stringify({ type: 'stop', message: 'Игра остановлена...', gameId: gameId }));
    ws.send(JSON.stringify({ type: 'gameStopped', gameId: gameId }));
}

// Сброс игры
function resetGame(ws, data) {
    const { gameId } = data;
    const game = games.get(gameId);

    if (!game) return;

    game.fastestPlayer = null;
    game.state = GAME_STATES.WAITING;
    game.clients.clear();

    broadcastToAllInGame(gameId, JSON.stringify({ type: 'reset', message: 'Игра сброшена. Пожалуйста, зарегистрируйтесь заново', gameId: gameId }));
    ws.send(JSON.stringify({ type: 'gameReset', gameId: gameId }));
}

// Новый раунд
function newRound(ws, data) {
    const { gameId } = data;
    const game = games.get(gameId);

    if (!game) return;

    game.clients.forEach((client) => {
        client.hasAnswered = false;
    });

    game.fastestPlayer = null;
    broadcastToAllInGame(gameId, JSON.stringify({ type: 'newRound', message: 'Новый раунд начат!', gameId: gameId }));
    ws.send(JSON.stringify({ type: 'newRound', gameId: gameId }));
}

// Режим регистрации
function registrationMode(ws, data) {
    const { gameId } = data;
    const game = games.get(gameId);

    if (!game) return;

    game.state = GAME_STATES.WAITING;
    broadcastToAllInGame(gameId, JSON.stringify({ type: 'registration', message: "Режим регистрации" }));
    ws.send(JSON.stringify({ type: 'registration', gameId: gameId }));
}

// Удаление игры
function deleteGame(ws, data) {
    const { gameId } = data;

    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    games.delete(gameId);
    broadcast(JSON.stringify({ type: 'gameDeleted', gameId: gameId }));
}

// Рассылка сообщений всем клиентам
function broadcast(message) {
    wss.clients.forEach((client) => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(message);
        }
    });
}

// Рассылка сообщений всем клиентам в игре
function broadcastToAllInGame(gameId, message) {
    const game = games.get(gameId);

    if (!game) return;

    game.clients.forEach((client, clientWs) => {
        if (clientWs.readyState === WebSocket.OPEN) {
            try {
                clientWs.send(message);
            } catch (error) {
                console.error(`Ошибка при отправке сообщения клиенту в игре ${gameId}:`, error);
            }
        }
    });
}

// Рассылка сообщений всем ведущим в игре
function broadcastToHosts(gameId, message) {
    const game = games.get(gameId);

    if (!game) return;

    game.hostClients.forEach((hostClient, hostWs) => {
        if (hostWs.readyState === WebSocket.OPEN) {
            try {
                hostWs.send(message);
            } catch (error) {
                console.error(`Ошибка при отправке сообщения хосту в игре ${gameId}:`, error);
            }
        }
    });
}
