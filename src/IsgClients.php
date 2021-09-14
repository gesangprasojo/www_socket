<?php
/**
 * Simple chat server which uses Ratchet library.
 *
 * @author Rio Astamal <rio@rioastamal.net>
 * @link https://github.com/rioastamal-examples/simple-chat-server-ratchet-php
 * @license MIT
 */
namespace IsG;
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Carbon\Carbon;

class IsgClients implements MessageComponentInterface
{
    protected $clients = [];
    protected $prefix = 'SERVER:';
    public function onOpen(ConnectionInterface $conn)
    {
        $data = [
            'id' => $conn->resourceId,
            'nickname' => 'user_' . $conn->resourceId,
            'logged_in' => false,
            'conn' => $conn
        ];
        $this->addClient($data);

        $message = null;
        $content['id']              = 'me@gesangprasojo.com';
        $content['id_look']         = 'Gesang Prasojo';
        $content['content']         = 'Hallo Sayang siapa di situ ?';
        $content['extra_content']   =  $data;
        $content['time']            = Carbon::now();
        $content['step_message']    = 'new_client_stranger_01_wa';
        $this->debug('New connection -> ' . Carbon::now());
        $message = array(
            "status" => "success",
            "data"   => $content
        );
        $conn->send(json_encode($message));
    }
    public function onMessage(ConnectionInterface $from, $message)
    {
        $dataReciver = json_decode($message);
        $trimmedMessage = $message;
        $this->debug('Client id -> ' . $from->resourceId . ' sent a message -> ' . gettype($dataReciver->category) );

        switch ($message) {
            case trim($message) === '/quit':
                $from->close();
                break;
            default:
                $from->send("\nERROR: Unknown command.\n");
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $nickname = $this->clients[$conn->resourceId]['nickname'];
        if ($this->clients[$conn->resourceId]['logged_in']) {
            $this->broadcastMessage("User `{$nickname}` has quit chatroom.", $conn);
        }

        $this->debug('Connection closed -> ' . $conn->resourceId);
        unset($this->clients[$conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
    }

    protected function debug($message, $newline = "\n")
    {
        printf("%s %s%s", $this->prefix, $message, $newline);
    }

    protected function addClient($clientData)
    {
        if (array_key_exists($clientData['id'], $this->clients)) {
            return;
        }

        $this->clients[$clientData['id']] = $clientData;
    }

    protected function handleNickname(ConnectionInterface $from, $message)
    {
        if (! preg_match('#^/nick ([a-zA-z0-9_\-]+)#', $message, $matches)) {
            $from->send("\nERROR: Nickname can not contains space.\n");
            return;
        }

        $nickname = $matches[1];

        // Make sure their nickname is unique
        foreach ($this->clients as $id => $client) {
            if ($nickname === $client['nickname']) {
                $from->send("\nERROR: Nickname already taken, please use other nickname.\n");
                return;
            }
        }

        // Change his/her nickname
        foreach ($this->clients as $id => $client) {
            if ((string)$id === (string)$from->resourceId) {
                $from->send("\nSUCCESS: Your nickname has changed to {$nickname}.\n");
                break;
            }
        }

        $this->clients[$from->resourceId]['logged_in'] = true;
        $this->clients[$from->resourceId]['nickname'] = $nickname;
        $this->broadcastMessage("User `{$nickname}` has joined channel.", $from);
    }

    protected function handleMessage(ConnectionInterface $from, $message)
    {
        if (! $this->isLoggedIn($from)) {
            return;
        }

        if (! preg_match('#^/msg (.*)#', $message, $matches)) {
            $from->send("\nERROR: Please provide a message.\n");
            return;
        }

        $nickname = $this->clients[$from->resourceId]['nickname'];
        $this->broadcastMessage("{$nickname}: {$matches[1]}", $from);
    }

    protected function handlePrivateMessage(ConnectionInterface $from, $message)
    {
        if (! $this->isLoggedIn($from)) {
            return;
        }

        if (! preg_match('#^/pm ([a-zA-z0-9_\-]+) (.*)#', $message, $matches)) {
            $from->send("\nERROR: Could not send your message.\n");
            return;
        }

        $nickname = $matches[1];
        $privateMessage = $matches[2];
        $senderNickname = $this->clients[$from->resourceId]['nickname'];

        if ($nickname === $senderNickname) {
            $from->send("\nERROR: You can not PM yourself.\n");
            return;
        }

        $sendToIndex = -1;
        foreach ($this->clients as $id => $client) {
            if ($client['nickname'] === $nickname) {
                $sendToIndex = $id;
                break;
            }
        }

        if ($sendToIndex === -1) {
            $from->send("\nERROR: Nickname `{$nickname}` does not exists.\n");
            return;
        }

        $this->clients[$sendToIndex]['conn']->send("\n>> PM from `{$senderNickname}` -> {$privateMessage}\n");
    }

    protected function handleOnlineUsers(ConnectionInterface $from, $message)
    {
        $totalUsers = count($this->clients);
        $numberOfAnonymous = 0;
        $numberOfLoggedIn = 0;
        $loggedInUsers = [];

        foreach ($this->clients as $client) {
            if ($client['logged_in']) {
                $loggedInUsers[] = $client['nickname'];
            }
        }
        $numberOfLoggedIn = count($loggedInUsers);
        $numberOfAnonymous = $totalUsers - $numberOfLoggedIn;

        $message = "Currently we have {$numberOfLoggedIn} users online and {$numberOfAnonymous} anonymous.\n";
        $message .= str_repeat('-', strlen(trim($message))) . "\n";

        if ($numberOfLoggedIn > 0) {
            $message .= "\n";
            foreach ($loggedInUsers as $user) {
                $message .= "- $user\n";
            }
        }

        $from->send("\n{$message}\n");
    }

    protected function broadcastMessage($message, ConnectionInterface $from = null)
    {
        if (is_null($from)) {
            foreach ($this->clients as $id => $client) {
                $client['conn']->send("\n>> ss {$message}\n");
            }

            return;
        }

        foreach ($this->clients as $id => $client) {
            if ((string)$id !== (string)$from->resourceId) {
                $dataJson= json_encode(['data'=>$message]);
                $client['conn']->send($dataJson);
            }
        }
    }

    protected function isLoggedIn(ConnectionInterface $conn)
    {
        if (! $this->clients[$conn->resourceId]['logged_in']) {
            $conn->send("\nERROR: You need to set your nickname first.\n");
            return false;
        }

        return true;
    }
}