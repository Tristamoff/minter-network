<?php

namespace classes;

use classes\Pdo as myPDO;

class Main
{
    public $pdo;

    public function __construct($db_settings)
    {
        $dbh = new myPDO($db_settings);
        $this->pdo = $dbh->connect();
    }

    //отрисовка шабона
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

    //получение личных сообщений
    public function getDirect($address) {
        $sql = '
            select `direct`.*, `pgp_keys`.`public`, `address`.`name`, `address`.`icon`
            from `direct` 
            left join `pgp_keys` on `pgp_keys`.`address` = `direct`.`address_from`
            left join `address` on `address`.`address` = `direct`.`address_from`
            where `direct`.`address_to`=:address order by `direct`.`id` desc limit 30';
        $sth = $this->pdo->prepare($sql);
        $sth->bindValue(':address', $address);
        $sth->execute();
        $res = $sth->fetchAll(\PDO::FETCH_ASSOC);
        $resp = [];
        foreach ($res as $row) {
            $name = substr($row['address_from'], 0, 8) . '...' . substr($row['address_from'], -8);
            $icon = '';
            if (!empty($row['name'])) {
                $name = (string) $row['name'];
            }
            if (!empty($row['icon'])) {
                $icon = '<img src="' . (string) $row['icon'] . '" />';
            }
            $resp[] = [
                'direct_id' => $row['direct_id'],
                'name' => $name,
                'icon' => $icon,
                'address' => $row['address_from'],
                'message' => $row['message'],
                'public_key' => (string) $row['public'],
                'created_at' => date('Y.m.d H:i', $row['created_at']),
            ];
        }
        $resp = array_reverse($resp);
        return $resp;
    }

    //создание таблиц
    public function install() {
        //инфо по адресам
        $sql = 'CREATE TABLE IF NOT EXISTS `address` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        $sth = $this->pdo->prepare($sql);
        $sth->execute();

        //таблица для ключей
        $sql = 'CREATE TABLE IF NOT EXISTS `pgp_keys` (
  `key_id` int(11) NOT NULL AUTO_INCREMENT,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_id` int(11) NOT NULL,
  `public` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`key_id`),
  UNIQUE KEY `address` (`address`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        $sth = $this->pdo->prepare($sql);
        $sth->execute();

        //таблица твитов
        $sql = 'CREATE TABLE IF NOT EXISTS `tweets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tweet_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_tweet_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_id` int(11) NOT NULL,
  `has_child` int(11) NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `likes` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        $sth = $this->pdo->prepare($sql);
        $sth->execute();

        //таблица директа
        $sql = 'CREATE TABLE IF NOT EXISTS `direct` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `block_id` int(11) NOT NULL,
  `direct_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address_from` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address_to` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `direct_id` (`direct_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        $sth = $this->pdo->prepare($sql);
        $sth->execute();

        //таблица настроек
        $sql = 'CREATE TABLE IF NOT EXISTS `settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `address_name` (`address`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        $sth = $this->pdo->prepare($sql);
        $sth->execute();

        $data = 'INSERT INTO `settings` (`address`, `name`, `value`) VALUES(0, "last_block_id", 1769545);';
        $sth = $this->pdo->prepare($data);
        $sth->execute();

        $data = 'INSERT INTO `settings` (`address`, `name`, `value`) VALUES(0, "last_tweet_id", 0);';
        $sth = $this->pdo->prepare($data);
        $sth->execute();

        //лайки
        $sql = 'CREATE TABLE IF NOT EXISTS  `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tweet_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(11) NOT NULL,
  `block_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tweet_id_address` (`tweet_id`,`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        $sth = $this->pdo->prepare($sql);
        $sth->execute();
        echo 'Установка завершена';
    }
}