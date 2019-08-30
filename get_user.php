<?php

/**
 * получение баланса, имени и аватара кошелька
 */

include_once dirname(__FILE__) . '/config.php';

$userData = [];
if (!empty($_GET['address'])) {
    $address = $_GET['address'];

    $minter = new \classes\Minter($db_settings, $nodeUrlPhp, $token);

    $tweet = new \classes\Tweet($db_settings);
    $userData = $tweet->getUserData($address);
    $userData['balance'] = $minter->getBalance($address);
}

echo  json_encode($userData);
