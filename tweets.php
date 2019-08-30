<?php
/**
 * Получение твитов из БД
 */

include_once dirname(__FILE__) . '/config.php';

$tweet = new \classes\Tweet($db_settings);

$block_id = '';
$id = '';
$parent_tweet_id = '';
$address = '';
$current_address = '';
if (!empty($_GET['parent_tweet_id'])) {
    $parent_tweet_id = $_GET['parent_tweet_id'];
}
if (!empty($_GET['address'])) {
    $address = $_GET['address'];
}
if (!empty($_GET['current_address'])) {
    $current_address = $_GET['current_address'];
}
$tweets = $tweet->getLastTweets($block_id, $id, $parent_tweet_id, $address, $current_address);
echo json_encode($tweets);
