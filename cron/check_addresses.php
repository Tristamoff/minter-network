<?php
/**
 * Поиск в твитах новых адресов
 */

include_once dirname(__FILE__) . '/../config.php';

$cron = new \classes\Cron($db_settings, $nodeUrlPhp);
$cron->getNewAddresses();

