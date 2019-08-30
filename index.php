<?php

include_once dirname(__FILE__) . '/config.php';

$main = new \classes\Main($db_settings);
$address = '';
if (!empty($_GET['address'])) {
    $js_settings['address'] = htmlspecialchars($_GET['address']);
}
$js_settings = json_encode($js_settings);
$page = $main->render('index', ['js_settings' => $js_settings]);
echo $page;