<?php

//получение баланса кошелька

include_once dirname(__FILE__) . '/config.php';

$balance[$token] = 0;
if (!empty($_GET['address'])) {
    $address = $_GET['address'];
    $minter = new \classes\Minter($db_settings, $nodeUrlPhp, $token);
    $balanceToken = $minter->getBalance($address);
    $balance[$token] = $balanceToken;
}

echo  json_encode($balance);
