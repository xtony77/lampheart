<?php

namespace lampheart\Support;

class App
{
    public static function get(string $key, $default = null)
    {
        global $app;

        if ($key !== '' && isset($app[$key]))
        {
            return $app[$key];
        }
        else if ($key !== '' && !isset($app[$key]) && !empty($default))
        {
            return $default;
        }

        return false;
    }

    public static function set(array $data)
    {
        global $app;
        foreach ($data as $key => $value) {
            $app[$key] = $value;
        }
    }
}