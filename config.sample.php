<?php
/**
 * Настройки и подключение vendor
 */

$db_settings = [
    'host' => 'localhost',
    'name' => 'root',
    'pass' => 'root',
    'db_name' => 'minter'
];

$nodeUrlPhp = 'http://api.minter.one/';
$token = 'BIP';

$js_settings = [
    'token' => $token,
    'nodeUrl' => 'https://api.minter.stakeholder.space/'
];

include_once dirname(__FILE__) . '/vendor/autoload.php';

include_once dirname(__FILE__) . '/classes/Pdo.php';
include_once dirname(__FILE__) . '/classes/Main.php';
include_once dirname(__FILE__) . '/classes/Minter.php';
include_once dirname(__FILE__) . '/classes/Cron.php';
include_once dirname(__FILE__) . '/classes/Tweet.php';