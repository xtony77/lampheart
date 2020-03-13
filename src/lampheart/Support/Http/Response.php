<?php

namespace lampheart\Support\Http;

class Response
{
    public static function json($context)
    {
        return json_encode($context);
    }
}