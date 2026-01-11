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
            console.log(data);

            const { type, gameId, isHost } = data;
            if (isHost)//команда от управляющего
                switch (type) {
                    case 'getPlayers':
                        sendPlayersListToHosts(ws, gameId);
                        break;
                    case 'getGames':
                        getGames(ws);
                        break;
                    case 'createGame':
                        createGame(ws, data);
                        break;
                    case 'deleteGame':
                        deleteGame(ws, data);
                        break;
                    case 'startGame':
                        startGame(ws, data)
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
                        //hostClients.set(ws)
                        joinHostToGame(ws, data);
                        //ws.send(JSON.stringify({ type: 'hostRegistered', message: `Всего ведущих:${hostClients.size}` }));
                        break;
                    case 'deletePlayer':
                        deletePlayer(ws, data)
                        ws.send(JSON.stringify({ type: 'playerDeleted', message: `Игрок ${data.message} был удален` }));
                        break;
                    default:
                        ws.send(JSON.stringify({ type: 'error', message: `не известная команда от хоста(${type})` }));
                }
            else//команда не от хоста

                switch (type) {
                    case 'getGames':
                        getGames(ws);
                        break;
                    case 'joinGame':
                        joinGame(ws, data);
                        break;
                    case 'fire':
                        fire(ws, data)
                        break;
                    default:
                        handleGameMessage(ws, data);
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
function sendPlayersListToHosts(ws, gameId) {
    // Проверяем, существует ли игра
    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const game = games.get(gameId);

    // Проверяем, есть ли хосты в игре
    if (!game.hostClients || game.hostClients.size === 0) {
        console.log(`В игре с ID ${gameId} нет ведущих`);
        ws.send(JSON.stringify({ type: 'error', message: `В игре с ID ${gameId} нет ведущих` }));
        return;
    }

    // Формируем список игроков
    const playerList = Array.from(game.clients.values())
        .map(client => client.name); // Убираем фильтр по isHost, так как это клиенты, а не хосты

    console.log(`Обновление списка игроков для игры ${gameId}. Клиентов: ${game.clients.size}`);

    // Формируем данные для отправки
    const data = JSON.stringify({
        type: 'playerList',
        gameId: gameId, // Добавляем gameId в ответ
        players: playerList
    });

    // Отправляем список игроков всем хостам в игре
    game.hostClients.forEach((hostClient, hostWs) => {
        if (hostWs.readyState === WebSocket.OPEN) {
            try {
                hostWs.send(data);
                console.log(`Список игроков отправлен хосту в игре ${gameId}`);
            } catch (error) {
                console.error(`Ошибка при отправке списка игроков хосту в игре ${gameId}:`, error);
            }
        } else {
            console.log(`Не удалось отправить список игроков хосту. Состояние соединения: ${hostWs.readyState}`);
        }
    });
}




function createGame(ws, data) {
    const { gameId, name, isHost } = data;

    // Проверяем, существует ли уже игра с таким ID
    if (games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} уже существует` }));
        return;
    }

    // Создаём новую игру
    games.set(gameId, {
        state: GAME_STATES.WAITING,
        clients: new Map(),
        hostClients: new Map(),
        fastestPlayer: null
    });

    // Добавляем ведущего
    //games.get(gameId).hostClients.set(ws, { name, isHost: true });
    //games.get(gameId).clients.set(ws, { name, ready: true, hasAnswered: false });

    ws.send(JSON.stringify({
        type: 'game_created',
        message: `Игра ${gameId} создана.`
    }));
}

function deletePlayer(ws, data) {
    const { gameId, name } = data;

    // Проверяем, существует ли игра
    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const game = games.get(gameId);

    // Находим WebSocket клиента по имени
    let playerWebSocket = null;
    for (const [clientWs, client] of game.clients) {
        if (client.name === name) {
            playerWebSocket = clientWs;
            break;
        }
    }



    // Удаляем игрока из игры
    game.clients.delete(playerWebSocket);

    // Отправляем подтверждение об удалении
    ws.send(JSON.stringify({ type: 'playerDeleted', message: `Игрок ${name} был удален` }));

    // Обновляем список игроков у ведущего
    //updateHostPlayerList(gameId);
    sendPlayersListToHosts(ws, gameId);


}


function joinGame(ws, data) {
    const { gameId, name } = data;

    // Проверяем, существует ли игра
    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const game = games.get(gameId);

    if (game.state != GAME_STATES.WAITING) {
        ws.send(JSON.stringify({ type: 'reject', message: 'Идет игра. Регистрация не возможна.' }));
        return;

    }
    // Проверяем, не занято ли имя
    const isNameTaken = Array.from(game.clients.values()).some(client => client.name === name);
    if (isNameTaken) {
        ws.send(JSON.stringify({ type: 'name_taken', message: 'Это имя уже занято. Выберите другое.' }));
        return;
    }

    // Добавляем игрока
    game.clients.set(ws, { name: name, ready: true, hasAnswered: false });

    ws.send(JSON.stringify({
        type: 'registered',
        message: `Добро пожаловать, ${name}. Ожидаем начала игры. Игра:${gameId}`,
        gameId: gameId
    }));

    sendPlayersListToHosts(ws, gameId);
}

function joinHostToGame(ws, data) {
    const { gameId, name, isHost } = data;

    if (isHost == undefined || isHost == false) {
        ws.send(JSON.stringify({ type: 'error', message: `Отсутствует статус хоста` }));
        return;
    }
    // Проверяем, существует ли игра
    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const game = games.get(gameId);

    // Проверяем, не занято ли имя
    const isNameTaken = Array.from(game.hostClients.values()).some(client => client.name === name);
    if (isNameTaken) {
        ws.send(JSON.stringify({ type: 'name_taken', message: 'Это имя уже занято. Выберите другое.' }));
        return;
    }

    // Добавляем игрока
    game.hostClients.set(ws, { name: name });

    ws.send(JSON.stringify({
        type: 'registered',
        message: `Добро пожаловать, ${name}. Вы хост в игре:${gameId}`
    }));
    console.log(game.hostClients);
    //sendPlayersListToHosts(ws, gameId);
}

function getGames(ws) {
    const gameList = Array.from(games.keys());
    ws.send(JSON.stringify({ type: 'gameList', games: gameList }));
}


function handleGameMessage(ws, data) {

    const { gameId, type } = data;
    const game = games.get(gameId);
    console.log(data, gameId);
    if (!game) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const clientData = game.clients.get(ws);

    switch (type) {
        case 'get_game_state':
            ws.send(JSON.stringify({ type: 'game_state', message: game.state }));
            break;
        // case 'registrationMode':
        //     if (clientData && clientData.isHost) {
        //         game.state = GAME_STATES.WAITING;
        //         broadcast(gameId, JSON.stringify({ type: 'registration', message: "Режим регистрации" }));
        //     }
        //     break;
        case 'fire':
            if (clientData && game.state === GAME_STATES.RUNNING && !game.fastestPlayer && !clientData.hasAnswered) {
                game.fastestPlayer = clientData.name;
                clientData.hasAnswered = true;
                broadcast(gameId, JSON.stringify({ type: 'fastest', fastest: game.fastestPlayer }));
            }
            break;
        case 'play':
            //console.log(`clientData:${clientData} clientData.isHost:${clientData.isHost}`)
            //if (clientData && clientData.isHost) 
            {
                console.log('play', gameId)
                play(ws, gameId);
            }
            break;
        case 'pause':
            if (clientData && clientData.isHost) {
                pause(ws, gameId);
            }
            break;
        case 'stop':
            if (clientData && clientData.isHost) {
                stop(ws, gameId);
            }
            break;
        case 'reset':
            if (clientData && clientData.isHost) {
                reset(ws, gameId);
            }
            break;
        case 'newRound':
            if (clientData && clientData.isHost) {
                newRound(ws, gameId);
            }
            break;
        case 'getGames':
            getGames(ws);
            break;
        case 'deleteGame':
            deleteGame(ws, data);
            break;

    }
}

function handleClientDisconnect(ws) {
    // Находим игру, в которой участвовал клиент
    for (const [gameId, game] of games) {
        if (game.clients.has(ws)) {
            const client = game.clients.get(ws);
            console.log(`Player disconnected from game ${gameId}: ${client.name}`);
            game.clients.delete(ws);
            sendPlayersListToHosts(ws, gameId);
            break;
        }
        if (game.hostClients && game.hostClients.has(ws)) {
            const client = game.hostClients.get(ws);
            console.log(`Host disconnected from game ${gameId}: ${client.name}`);
            game.hostClients.delete(ws);
            sendPlayersListToHosts(ws, gameId);
            break;
        }
    }
}

function broadcastById(gameId, message, excludeNonRegistered = true, excludeAnswered = true) {
    const game = games.get(gameId);
    if (!game) return;

    let i = 0;
    game.clients.forEach((client, ws) => {
        if (excludeNonRegistered && !client.ready) return;
        if (excludeAnswered && client.hasAnswered) return;
        if (ws.readyState === WebSocket.OPEN) {
            ws.send(message);
            i++;
        }
    });
    console.log(`Отправка ${i} клиентам в игре ${gameId}`);
}



function registrationMode(ws, gameId) {
    const game = games.get(gameId);
    if (!game) return;
    {
        gameState = GAME_STATES.WAITING;
        //broadcast("Режим регистрации");
        broadcast(gameId, JSON.stringify({ type: 'registration', message: "Режим регистрации" }));
        ws.send(JSON.stringify({ type: 'registration', 'gameId': gameId }));

    }

}

function play(ws, gameId) {
    console.log(gameId)
    const game = games.get(gameId);
    if (!game) return;

    game.state = GAME_STATES.RUNNING;
    game.fastestPlayer = null;
    broadcast(JSON.stringify({ type: 'play', message: 'Играем!', gameId: gameId }));
    ws.send(JSON.stringify({ type: 'gameRunning', 'gameId': gameId }));
}

function pause(ws, gameId) {
    const game = games.get(gameId);
    if (!game) return;

    game.fastestPlayer = null;
    game.state = GAME_STATES.PAUSED;
    broadcast(JSON.stringify({ type: 'pause', message: 'Игра на паузе...', gameId: gameId }));
    ws.send(JSON.stringify({ type: 'gamePaused', 'gameId': gameId }));
}

function stop(ws, gameId) {
    const game = games.get(gameId);
    if (!game) return;

    game.state = GAME_STATES.STOPPED;
    game.fastestPlayer = null;
    broadcast(JSON.stringify({ type: 'stop', message: 'Игра остановлена...', gameId: gameId }));
    ws.send(JSON.stringify({ type: 'gameStopped', 'gameId': gameId }));
}

function reset(ws, gameId) {
    const game = games.get(gameId);
    if (!game) return;

    game.fastestPlayer = null;
    game.state = GAME_STATES.WAITING;
    game.clients.clear();
    broadcast(JSON.stringify({ type: 'reset', message: 'Игра сброшена. Пожалуйста зарегистрируйтесь заново', gameId: gameId }), false);
    UpdateClientsList(gameId);
    ws.send(JSON.stringify({ type: 'gameReset', 'gameId': gameId }));
}

function newRound(ws, data) {
    const {gameId}=data;
    console.log('New round started ', gameId);
    const game = games.get(gameId);
    if (!game) return;

    game.clients.forEach((client) => {
        client.hasAnswered = false;
        console.log(client);
    });
    game.fastestPlayer = null;
    
    //broadcast(JSON.stringify({ type: 'newRound', message: 'Новый раунд начат!', gameId: gameId }));
    ws.send(JSON.stringify({ type: 'newRound', 'gameId': gameId }));
    broadcastToAllInGame(gameId, JSON.stringify( { type: 'newRound', 'gameId': gameId }));
}

function getGames(ws) {
    const gameList = Array.from(games.keys());
    ws.send(JSON.stringify({ type: 'gameList', players: gameList }));
}

function deleteGame(ws, data) {
    const { gameId } = data;

    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    games.delete(gameId);
    broadcast(JSON.stringify({ type: 'gameDeleted', gameId: gameId }));
}

function broadcast(message) {
    wss.clients.forEach((client) => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(message);
        }
    });
}



function fire(ws, data) {
    const { gameId } = data;

    // Проверяем, существует ли игра
    if (!games.has(gameId)) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра с ID ${gameId} не найдена` }));
        return;
    }

    const game = games.get(gameId);

    // Проверяем, что игра в состоянии RUNNING
    if (game.state !== GAME_STATES.RUNNING) {
        ws.send(JSON.stringify({ type: 'error', message: `Игра не в состоянии RUNNING` }));
        return;
    }

    // Получаем данные клиента
    const clientData = game.clients.get(ws);
    console.log(clientData);
    // Проверяем, что клиент существует и еще не отвечал
    if (!clientData) {
        console.log("Клиент не найден в игре");
        return;
    }

    if (clientData.hasAnswered) {
        console.log("Клиент уже отвечал");
        return;
    }

    // Устанавливаем, что этот клиент ответил первым
    game.fastestPlayer = clientData.name;
    clientData.hasAnswered = true;

    // Формируем сообщение о самом быстром игроке
    const fastestMessage = JSON.stringify({
        type: 'fastest',
        fastest: game.fastestPlayer,
        gameId: gameId
    });

    // Рассылаем сообщение всем клиентам в игре
    broadcastToAllInGame(gameId, fastestMessage);

    // Рассылаем сообщение всем хостам в игре
    broadcastToHosts(gameId, fastestMessage);
}

// Функция для рассылки сообщений всем клиентам в игре
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

// Функция для рассылки сообщений всем хостам в игре
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