<?php

namespace classes;

use Minter\MinterAPI;
use classes\Tweet;
use classes\Cron;

class Minter
{
    public $api;
    public $db_settings;
    public $nodeUrl;
    public $token;

    public function __construct($db_settings, $nodeUrl = 'http://api.minter.one/', $token = 'BIP')
    {
        $this->api = new MinterAPI($nodeUrl);
        $this->db_settings = $db_settings;
        $this->nodeUrl = $nodeUrl;
        $this->token = $token;
    }

    //получение блоков
    public function getBlocks($from_block_id, $count = 15) {
        $end_block_id = $from_block_id + $count;
        $tweet = new Tweet($this->db_settings);
        $cron = new Cron($this->db_settings, $this->nodeUrl);
        while ($from_block_id < $end_block_id) {
            try {
                $block = $this->api->getBlock($from_block_id);
                $block_id = $block->result->height;
                if ($block) {
                    echo 'Check block ' . $from_block_id ."\n";
                } else {
                    echo 'Block ' . $from_block_id . ' not found' . "\n";
                }
                if (!empty($block->result->transactions)) {
                    //обход транзакций блока
                    preg_match('/([^.]+)/',$block->result->time,$match);
                    $block_time = strtotime(str_replace('T', ' ', $match[0]));

                    foreach ($block->result->transactions as $transaction) {
                        if ($transaction->type != 1) continue;
                        if (!empty($transaction->payload)) {
                            $payload = base64_decode($transaction->payload);
                            try {
                                $payload = json_decode($payload, true);
                                if (!empty($payload['message']) && !empty($payload['tweet_id'])) {
                                    //твит или ответ
                                    $tweet_data = [];
                                    $tweet_data['message'] = $payload['message'];
                                    $tweet_data['tweet_id'] = $payload['tweet_id'];
                                    $tweet_data['parent_tweet_id'] = '';
                                    if (!empty($payload['reply_to'])) {
                                        $tweet_data['parent_tweet_id'] = $payload['reply_to'];
                                    }
                                    $tweet_data['address'] = $transaction->from;
                                    $tweet_data['block_id'] = $block_id;
                                    $tweet_data['meta'] = '';//тут будут og-метатэги ссылок, картинки и т.п.
                                    $tweet_data['created_at'] = $block_time;
                                    $saved = $tweet->saveTweet($tweet_data);
                                }
                                elseif (!empty($payload['message']) && !empty($payload['direct_id'])) {
                                    //директ
                                    $tweet_data = [];
                                    $tweet_data['block_id'] = $block_id;
                                    $tweet_data['direct_id'] = $payload['direct_id'];
                                    $tweet_data['address_from'] = $transaction->from;
                                    $tweet_data['address_to'] = $transaction->data->to;
                                    $tweet_data['message'] = $payload['message'];
                                    $tweet_data['created_at'] = $block_time;
                                    $saved = $tweet->saveDirect($tweet_data);
                                }
                                elseif (!empty($payload['public'])) {
                                    //отправка публичного ключа
                                    $tweet_data = [];
                                    $tweet_data['block_id'] = $block_id;
                                    $tweet_data['address'] = $transaction->from;
                                    $tweet_data['public'] = $payload['public'];
                                    $tweet_data['created_at'] = $block_time;
                                    $saved = $tweet->savePublicKey($tweet_data);
                                }
                                elseif (!empty($payload['like'])) {
                                    //лайк
                                    $tweet_data = [];
                                    $tweet_data['block_id'] = $block_id;
                                    $tweet_data['address'] = $transaction->from;
                                    $tweet_data['tweet_id'] = $payload['like'];
                                    $tweet_data['created_at'] = $block_time;
                                    $saved = $tweet->saveLike($tweet_data);
                                }
                                elseif (!empty($payload['unlike'])) {
                                    //снятие лайка
                                    $tweet_data = [];
                                    $tweet_data['block_id'] = $block_id;
                                    $tweet_data['address'] = $transaction->from;
                                    $tweet_data['tweet_id'] = $payload['unlike'];
                                    $tweet_data['created_at'] = $block_time;
                                    $saved = $tweet->saveUnLike($tweet_data);
                                }
                            } catch (\Exception $e) {

                            }
                        }
                    }
                }

                //сохранение номера последнего обработанного блока
                $cron->setLastBlockId($block_id);
            } catch (\Exception $e) {
                echo 'Block ' . $from_block_id . ' not found' . "\n";
            }

            $from_block_id++;
        }
    }

    //получение баланса
    public function getBalance($address) {
        try {
            $balance = $this->api->getBalance($address);
            if (!empty($balance->result->balance)) {
                $all_tokens = (array) $balance->result->balance;
                if (!empty($all_tokens[$this->token])) {
                    return round($all_tokens[$this->token] / 1000000000000000000, 3);
                }
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}