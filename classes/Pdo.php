<?php

namespace classes;

class Pdo
{
    protected $host;
    protected $name;
    protected $pass;
    protected $db_name;

    public function __construct($db_settings)
    {
        $this->host = $db_settings['host'];
        $this->name = $db_settings['name'];
        $this->pass = $db_settings['pass'];
        $this->db_name = $db_settings['db_name'];
    }

    public function connect()
    {
        $dbh = new \PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->name, $this->pass);
        return $dbh;
    }
}