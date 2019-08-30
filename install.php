<?php
/**
 * Создание таблиц в БД
 */

include_once dirname(__FILE__) . '/config.php';

$main = new \classes\Main($db_settings);
$main->install();
