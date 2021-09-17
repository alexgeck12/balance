<?php
use App\Core\Config;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

Config::getInstance()->set(__DIR__ . '/../config.ini');
$config = Config::getInstance()->get('mq');

$connection = new AMQPStreamConnection($config['host'], $config['port'], $config['user'], $config['password']);
$channel = $connection->channel();

$channel->queue_declare($config['queue'], false, true, false, false);

$action = json_encode([
    'type' => 'transfer',
    'data' => [
        'from_user_id' => '1',
        'to_user_id' => '2',
        'balance' => '250.25',
        'timestamp' => date("Y-m-d H:i:s")
    ]
]);

$msg = new AMQPMessage($action, ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
$channel->basic_publish($msg, '', $config['queue']);

echo " [x] Sent action: ". $action . "\n";

$channel->close();
$connection->close();