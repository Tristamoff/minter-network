<?php
/**
 * Сбор из блоков твитов, публичных ключей, сообщений директа
 */

include_once dirname(__FILE__) . '/config.php';

$cron = new \classes\Cron($db_settings, $nodeUrlPhp);

//смотрим последний обработанный блок
$block_id = $cron->getLastBlockId();
$cron->getBlocks(++$block_id, 50, $token);
