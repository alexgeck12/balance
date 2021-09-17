<?php
use App\Core\Config;

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

Config::getInstance()->set(__DIR__ . '/../config.ini');

$message = json_encode([
    'type' => 'enrollment',
    'data' => [
        'user_id' => '1',
        'balance' => '50.20',
        'timestamp' => date("Y-m-d H:i:s")
    ]
]);

$message2 = json_encode([
    'type' => 'enrollment',
    'data' => [
        'user_id' => '10',
        'balance' => '0.00',
        'timestamp' => date("Y-m-d H:i:s")
    ]
]);

$message3 = json_encode([
    'type' => 'writeOff',
    'data' => [
        'user_id' => '1',
        'balance' => '75.24',
        'timestamp' => date("Y-m-d H:i:s")
    ]
]);

$message4 = json_encode([
    'type' => 'transfer',
    'data' => [
        'from_user_id' => '1',
        'to_user_id' => '2',
        'balance' => '50.20',
        'timestamp' => date("Y-m-d H:i:s")
    ]
]);

$handler = new App\Handler($message3);
var_dump( $handler->run() );