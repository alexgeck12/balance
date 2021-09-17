<?php
use App\Core\Config;
use PhpAmqpLib\Connection\AMQPStreamConnection;

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

Config::getInstance()->set(__DIR__ . '/../config.ini');
$config = Config::getInstance()->get('mq');

$connection = new AMQPStreamConnection($config['host'], $config['port'], $config['user'], $config['password']);
$channel = $connection->channel();

$channel->queue_declare($config['queue'], false, true, false, false);

$callback = function ($msg) {
    echo " [x] Get action: ". $msg->body . "\n";
    $handler = new App\Handler($msg->body);
    $handler->run();
    $msg->ack();
};

$channel->basic_qos(null, 1, null);

$channel->basic_consume($config['queue'], '', false, false, false, false, $callback);

while ($channel->is_open()) {
    $channel->wait();
}

$channel->close();
$connection->close();