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
        if (self::$client->exists($key)) {
            return self::$client->get($key);
        } else {
            return false;
        }
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
        if (empty(env('REDIS_HOST')) || empty(env('REDIS_PORT'))) {
            throw new \Exception('Empty redis host and port');
        }

        self::$client = new Client([
            'scheme' => 'tcp',
            'host'   => env('REDIS_HOST'),
            'port'   => env('REDIS_PORT'),
        ]);
    }
}