<?php

namespace classes;

use classes\Pdo as myPDO;
use classes\Minter as myMinter;


class Cron
{
    public $pdo;
    public $nodeUrl;
    public $db_settings;

    public function __construct($db_settings, $nodeUrl)
    {
        $dbh = new myPDO($db_settings);
        $this->pdo = $dbh->connect();
        $this->nodeUrl = $nodeUrl;
        $this->db_settings = $db_settings;
    }

    //Обращение к блокчейну, забор блоков
    public function getBlocks($from_block_id, $count = 15, $token = 'BIP') {
        $minter = new myMinter($this->db_settings, $this->nodeUrl, $token);
        $minter->getBlocks($from_block_id, $count);
    }

    //получение id последнего обработанного блока
    public function getLastBlockId() {
        $sql = 'select `value` from `settings` where `name`="last_block_id" and address="0"';
        $sth = $this->pdo->prepare($sql);
        $sth->execute();
        $res = $sth->fetchColumn();
        return $res;
    }

    //сохранение id последнего обработанного блока
    public function setLastBlockId($id) {
        $sql = 'update `settings` set `value` = :id where `address` = 0 and `name`="last_block_id"';
        $sth = $this->pdo->prepare($sql);
        $sth->bindParam(':id', $id, \PDO::PARAM_INT);
        $sth->execute();
    }

    //сохранение новых адресов
    public function getNewAddresses() {
        $sql = 'select `value` from `settings` where `name`="last_tweet_id" and address="0"';
        $sth = $this->pdo->prepare($sql);
        $sth->execute();
        $tweet_id = $sth->fetchColumn();

        $new_tweet_id = 0;
        $sql = 'select `id`, `address` from `tweets` where `id`>:id order by id asc limit 5';
        $sth = $this->pdo->prepare($sql);
        $sth->bindParam(':id', $tweet_id, \PDO::PARAM_INT);
        $sth->execute();
        $res = $sth->fetchAll(\PDO::FETCH_ASSOC);
        $updated_at = time();
        foreach ($res as $row) {
            $sql = 'insert ignore into `address` (address, updated_at) values (:address, :updated_at)';
            $sth = $this->pdo->prepare($sql);
            $sth->bindParam(':address', $row['address'], \PDO::PARAM_STR);
            $sth->bindParam(':updated_at', $updated_at, \PDO::PARAM_INT);
            $sth->execute();
            $new_tweet_id = $row['id'];
        }
        if ($new_tweet_id > 0) {
            $sql = 'update `settings` set `value` = :id where `address` = 0 and `name`="last_tweet_id"';
            $sth = $this->pdo->prepare($sql);
            $sth->bindParam(':id', $new_tweet_id, \PDO::PARAM_INT);
            $sth->execute();
        }

        return $res;
    }

    // получение от minterscan имен аккаунтов
    public function saveNames() {
        $ch = curl_init();

        $updated_at = time() - 3600;
        $sql = 'select `address` from `address` where `updated_at` < :updated_at order by updated_at asc limit 10';
        $sth = $this->pdo->prepare($sql);
        $sth->bindParam(':updated_at', $updated_at, \PDO::PARAM_INT);
        $sth->execute();
        $res = $sth->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($res as $row) {
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, 'https://minterscan.pro/profiles/' . $row['address']);
            curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);
            $data = curl_exec($ch);
            if (!empty($data)) {
                $data = json_decode($data, true);
                $update = [':updated_at' => time()];
                $set = ['`updated_at`=:updated_at'];
                if (!empty($data['title'])) {
                    $update[':name'] = $data['title'];
                    $set[] = '`name`=:name';
                }
                if (!empty($data['icons']['jpg'])) {
                    $update[':icon'] = $data['icons']['jpg'];
                    $set[] = '`icon`=:icon';
                }
                $set = implode(', ', $set);
                $sql_update = 'update `address` set ' . $set . ' where `address` = :address';
                $sth_update = $this->pdo->prepare($sql_update);
                foreach ($update as $key => $var) {
                    $sth_update->bindParam($key, $update[$key]);
                }
                $sth_update->bindParam(':address', $row['address']);
                $sth_update->execute();
            }
        }
        curl_close($ch);
    }
}