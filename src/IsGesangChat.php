<?php
/**
 * Simple chat server which uses Ratchet library.
 *
 * @author Rio Astamal <rio@rioastamal.net>
 * @link https://github.com/rioastamal-examples/simple-chat-server-ratchet-php
 * @license MIT
 */
namespace IsGesang;
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Carbon\Carbon;

class IsGesangChat implements MessageComponentInterface
{
    protected $clients = [];
    protected $prefix = 'SERVER:';
    public function onOpen(ConnectionInterface $conn){
        $data = [
            'id' => $conn->resourceId,
            'nickname' => 'user_' . $conn->resourceId,
            'logged_in' => false,
            'conn' => $conn
        ];
        $this->addClient($data);
        $this->debug('Connection_ID = '.$conn->resourceId);
        $message    = $this->requestCurlOnOpen();
        $this->debug(json_encode($message));
        $conn->send(json_encode($message));
    }
    protected function requestCurl($data){
        $url="brain.gesangprasojo:91/whatsapp";
        $data=$data;
        $curl = curl_init();
           curl_setopt($curl, CURLOPT_URL, $url);
           curl_setopt($curl, CURLOPT_POST, 1);
           curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
           curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
           $response = curl_exec($curl);
           $response = json_decode($response);
           return $response;
    }
    protected function requestCurlOnOpen(){
        $url="brain.gesangprasojo:91/whatsapp/connect";
        $data=array('test'=>'test');
        $curl = curl_init();
           curl_setopt($curl, CURLOPT_URL, $url);
           curl_setopt($curl, CURLOPT_POST, 1);
           curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
           curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
           $response = curl_exec($curl);
           $response = json_decode($response);
           return $response;
    }
    // ===================================================================================================
    public function onMessage(ConnectionInterface $from, $message)
    {
        $content    = array("message"  =>  $message);
        $response   = $this->requestCurl($content);
        $this->debug(json_encode($response));
        $from->send(json_encode($response));
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
    public function onError(ConnectionInterface $conn, \Exception $e){}
    protected function debug($message, $newline = "\n"){printf("%s %s%s", $this->prefix, $message, $newline);}
    protected function addClient($clientData)
    {
        if (array_key_exists($clientData['id'], $this->clients)) {
            return;
        }
        $this->clients[$clientData['id']] = $clientData;
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
        foreach ($this->clients as $client) {
            $from->send("\n".$client['id']."\n");
        }
        $numberOfLoggedIn = count($loggedInUsers);
        $numberOfAnonymous = $totalUsers - $numberOfLoggedIn;
        $message = "Currently we have {$numberOfLoggedIn} users online and {$numberOfAnonymous} anonymous.\n";
    }
}