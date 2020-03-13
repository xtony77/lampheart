<?php

namespace lampheart\Support;

use lampheart\Support\App;

class Log
{
    public static function error(string $message, array $context)
    {
        self::fireLogEvent(__FUNCTION__, $message, $context);
    }

    public static function warning(string $message, array $context)
    {
        self::fireLogEvent(__FUNCTION__, $message, $context);
    }

    public static function notice(string $message, array $context)
    {
        self::fireLogEvent(__FUNCTION__, $message, $context);
    }

    public static function info(string $message, array $context)
    {
        self::fireLogEvent(__FUNCTION__, $message, $context);
    }

    public static function debug(string $message, array $context)
    {
        self::fireLogEvent(__FUNCTION__, $message, $context);
    }

    private static function fireLogEvent(string $level, string $message, array $context)
    {
        foreach (App::get('log') as $log) {
            $log->{$level}($message, $context);
        }
    }
}