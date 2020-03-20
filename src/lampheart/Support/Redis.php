<?php

namespace lampheart\Support;

use Predis\Autoloader;
use Predis\Client;

class Redis
{
    private static $client;

    public static function set(string $key, string $value, int $expiration_in_seconds = null)
    {
        self::make();

        if (!empty($expiration_in_seconds)) {
            self::$client->setex($key, $expiration_in_seconds, $value);
        } else {
            self::$client->set($key, $value);
        }
    }

    public static function get(string $key)
    {
        self::make();

        return self::$client->get($key);
    }

    public static function del(string $key)
    {
        self::make();

        if (self::$client->exists($key)) {
            self::$client->del($key);
        }
    }

    private static function make()
    {
        $path = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))).'/config/redis.php';

        if (!is_file($path)) {
            throw new \Exception('Redis config not exist: '.$path);
        }

        $config = require $path;

        if ($config['replication'] === true)
        {
            $parameters = $config['replications'];
            $options    = ['replication' => true];
            self::$client = new Client($parameters, $options);
            return;
        }

        self::$client = new Client($config['default']);
    }
}