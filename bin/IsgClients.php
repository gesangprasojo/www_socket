<?php
/**
 * Script to start the Chat server. The default port is 9191 and listen to all interfaces.
 */
require __DIR__ . '/../vendor/autoload.php';
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$port = isset($_SERVER['TEST']) ? $_SERVER['TEST'] : 9191;
$bindAddr = isset($_SERVER['CHAT_BIND_ADDR']) ? $_SERVER['CHAT_BIND_ADDR'] : '0.0.0.0';
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new IsG\IsgClients()
        )
    ),
    $port, $bindAddr
);
printf("Chat server running on %s:%s.\n--\n", $bindAddr, $port);
$server->run();