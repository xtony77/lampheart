<?php

namespace lampheart\Support\Http;

class Response
{
    public static function json($context, $code = null)
    {
        if (!empty($code) && is_numeric($code)) {
            http_response_code($code);
        }

        return json_encode($context);
    }
}