<?php

namespace classes;

use classes\Pdo as myPDO;

class Tweet
{
    public $pdo;

    public function __construct($db_settings)
    {
        $dbh = new myPDO($db_settings);
        $this->pdo = $dbh->connect();
    }

    //получение последних 15 твитов
    public function getLastTweets($block_id = 0, $id = 0, $parent_tweet_id = '', $address = '', $current_address = '') {
        $cond = ['parent_tweet_id' => '`tweets`.`parent_tweet_id`=:parent_tweet_id'];
        if (!empty($address)) {
            $cond['address'] = '`tweets`.`address`=:address';
        }
        $where = implode(' and ' , $cond);
        $sql = '
            select `tweets`.*, `pgp_keys`.`public` as public_key, `address`.`name`, `address`.`icon` 
            from `tweets` 
            left join `pgp_keys` on `pgp_keys`.`address` = `tweets`.`address`
            left join `address` on `address`.`address` = `tweets`.`address`
            where ' . $where . '
            group by `tweets`.`tweet_id` 
            order by `block_id` asc, `id` asc 
            limit 15';
        $sth = $this->pdo->prepare($sql);
        foreach ($cond as $name => $data) {
            $sth->bindValue(':' . $name, $$name);
        }
        $sth->execute();
        $res = $sth->fetchAll(\PDO::FETCH_ASSOC);
        $tweets = [];
        $tweet_ids = [];
        foreach ($res as $row) {
            $name = substr($row['address'], 0, 8) . '...' . substr($row['address'], -8);
            $icon = '';
            if (!empty($row['name'])) {
                $name = (string) $row['name'];
            }
            if (!empty($row['icon'])) {
                $icon = '<img src="' . (string) $row['icon'] . '" />';
            }
            $tweets[$row['tweet_id']] = [
                'public_key' => (string) $row['public_key'],
                'name' => $name,
                'icon' => $icon,
                'address' => $row['address'],
                'has_child' => $row['has_child'],
                'message' => htmlspecialchars($row['message']),
                'liked' => 'false',
                'likes' => $row['likes'],
                'created_at' => date('Y.m.d H:i', $row['created_at']),
                'tweet_id' => $row['tweet_id'],
            ];
            $tweet_ids[] = '"' . $row['tweet_id'] . '"';
        }

        //проверка - лайкнут ли твит текущим пользователем
        if (!empty($current_address)) {
            $sql_likes = 'select `tweet_id` from `likes` where `tweet_id` in(' . implode(', ', $tweet_ids) . ') and `address`=:address';
            $sth_likes = $this->pdo->prepare($sql_likes);
            $sth_likes->bindValue(':address', $current_address);
            $sth_likes->execute();
            $res_likes = $sth_likes->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($res_likes as $row_likes) {
                $tweets[$row_likes['tweet_id']]['liked'] = 'true';
            }
        }
        $tweets = array_values($tweets);

        return $tweets;
    }

    //сохранение твита/ответа
    public function saveTweet($data) {
        $sql="insert ignore into `tweets` (`tweet_id`, `parent_tweet_id`, `block_id`, `address`, `message`, `meta`, `created_at`) 
                values (:tweet_id, :parent_tweet_id, :block_id, :address, :message, :meta, :created_at)";
        $sth = $this->pdo->prepare($sql);
        $sth->bindValue(':tweet_id', $data['tweet_id']);
        $sth->bindValue(':parent_tweet_id', $data['parent_tweet_id']);
        $sth->bindValue(':block_id', $data['block_id']);
        $sth->bindValue(':address', $data['address']);
        $sth->bindValue(':message', $data['message']);
        $sth->bindValue(':meta', $data['meta']);
        $sth->bindValue(':created_at', $data['created_at']);
        $sth->execute();
        $error = $sth->errorInfo();
        if($error[0] == 0){
            return false;
        }else{
            return $error[2];
        }
    }

    //сохранение публичного ключа из блокчейна
    public function savePublicKey($data)
    {
        $sql = "insert into `pgp_keys` (`address`, `block_id`, `public`, `created_at`)
                values (:address, :block_id, :public, :created_at) on duplicate key update public=:public2, block_id=:block_id2";
        $sth = $this->pdo->prepare($sql);
        $sth->bindValue(':address', $data['address']);
        $sth->bindValue(':block_id', $data['block_id']);
        $sth->bindValue(':block_id2', $data['block_id']);
        $sth->bindValue(':public', $data['public']);
        $sth->bindValue(':public2', $data['public']);
        $sth->bindValue(':created_at', $data['created_at']);
        $sth->execute();
        $error = $sth->errorInfo();
        if ($error[0] == 0) {
            return false;
        } else {
            return $error[2];
        }
    }

    //лайк твита
    public function saveLike($data) {
        //проверяем был ли уже лайк
        $sql_ver = 'select `id` from `likes` where address=:address and tweet_id=:tweet_id limit 1';
        $sth_ver = $this->pdo->prepare($sql_ver);
        $sth_ver->bindValue(':address', $data['address']);
        $sth_ver->bindValue(':tweet_id', $data['tweet_id']);
        $sth_ver->execute();
        $res_ver_id = $sth_ver->fetchColumn();
        if (!$res_ver_id) {
            //ещё нет лайка за этот твит, добавляем
            $sql_add = 'insert into `likes` (tweet_id, address, created_at, block_id) values (:tweet_id, :address, :created_at, :block_id) ';
            $sth_add = $this->pdo->prepare($sql_add);
            $sth_add->bindValue(':address', $data['address']);
            $sth_add->bindValue(':tweet_id', $data['tweet_id']);
            $sth_add->bindValue(':created_at', $data['created_at']);
            $sth_add->bindValue(':block_id', $data['block_id']);
            $sth_add->execute();

            $sql_up = 'update `tweets` set `likes` = likes + 1 where `tweet_id`=:tweet_id';
            $sth_up = $this->pdo->prepare($sql_up);
            $sth_up->bindValue(':tweet_id', $data['tweet_id']);
            $sth_up->execute();
        }
    }

    //снятие лайк с твита
    public function saveUnLike($data) {
        //проверяем был ли уже лайк
        $sql_ver = 'select `id` from `likes` where `address`=:address and `tweet_id`=:tweet_id limit 1';
        $sth_ver = $this->pdo->prepare($sql_ver);
        $sth_ver->bindValue(':address', $data['address']);
        $sth_ver->bindValue(':tweet_id', $data['tweet_id']);
        $sth_ver->execute();
        $res_ver_id = $sth_ver->fetchColumn();
        if ($res_ver_id) {
            //был лайк за этот твит, удаляем
            $sql_del = 'delete from `likes` where `tweet_id`=:tweet_id and `address`=:address';
            $sth_del = $this->pdo->prepare($sql_del);
            $sth_del->bindValue(':address', $data['address']);
            $sth_del->bindValue(':tweet_id', $data['tweet_id']);
            $sth_del->execute();

            $sql_up = 'update `tweets` set likes = likes - 1 where tweet_id=:tweet_id';
            $sth_up = $this->pdo->prepare($sql_up);
            $sth_up->bindValue(':tweet_id', $data['tweet_id']);
            $sth_up->execute();
        }
    }

    //сохранение директа
    public function saveDirect($data) {
        $sql="insert into `direct` (`block_id`, `direct_id`, `address_from`, `address_to`, `message`, `created_at`) 
                values (:block_id, :direct_id, :address_from, :address_to, :message, :created_at)";
        $sth = $this->pdo->prepare($sql);
        $sth->bindValue(':block_id', $data['block_id']);
        $sth->bindValue(':direct_id', $data['direct_id']);
        $sth->bindValue(':address_from', $data['address_from']);
        $sth->bindValue(':address_to', $data['address_to']);
        $sth->bindValue(':message', $data['message']);
        $sth->bindValue(':created_at', $data['created_at']);
        $sth->execute();
        $error = $sth->errorInfo();
        if($error[0] == 0){
            return false;
        }else{
            return $error[2];
        }
    }


    //получение баланса
    public function getUserData($address) {
        $sql = 'select * from `address` where `address`=:address';
        $sth = $this->pdo->prepare($sql);
        $sth->bindValue(':address', $address);
        $sth->execute();
        $res = $sth->fetchAll(\PDO::FETCH_ASSOC);

        $name = substr($address, 0, 8) . '...' . substr($address, -8);
        $icon = '';
        if (!empty($res[0])) {
            if (!empty($res[0]['name'])) {
                $name = (string) $res[0]['name'];
            }
            if (!empty($res[0]['icon'])) {
                $icon = '<img src="' . (string) $res[0]['icon'] . '" />';
            }
        }

        $data = [
            'address' => $address,
            'name' => $name,
            'icon' => $icon
        ];
        return $data;
    }

    //отрисовка шаблона
    public function render($tpl, $variables = []) {
        $tpl_dir = dirname(__FILE__) . '/../view/' . $tpl . '.html';
        if (!is_file($tpl_dir)) {
            return 'View "'. $tpl_dir . '" not exists';
        }
        extract($variables);
        ob_start();
        include($tpl_dir);
        return ob_get_clean();
    }
}