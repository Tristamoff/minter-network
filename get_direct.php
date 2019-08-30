<?php

/**
 * Получение личных сообщений для адреса
 */

include_once dirname(__FILE__) . '/config.php';

$direct = [];
if (!empty($_POST['address'])) {
    $main = new \classes\Main($db_settings);
    $direct = $main->getDirect($_POST['address']);
}
echo json_encode($direct);
