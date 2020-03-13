<?php

/**
 * global variable $app
 */
if (function_exists('app') === false) {
    function app() {
        return new \lampheart\Support\App;
    }
}

/**
 * .env
 */
if (function_exists('env') === false) {
    function env($key, $default = null) {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (($valueLength = strlen($value)) > 1 && $value[0] === '"' && $value[$valueLength - 1] === '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

/**
 * localization
 */
if (function_exists('lang') === false) {
    function lang(string $translation)
    {
        $retrieve = array_filter(explode('.', $translation));

        $totalStr = count($retrieve);
        if ($totalStr <= 1) {
            return '';
        }

        $locale = app()::get('locale', 'en');

        $langFilePath = dirname(dirname(dirname(__DIR__))).'/resources/lang/'.$locale;

        if (!is_file($langFilePath)) {
            throw new \Exception('localization not exist: '.$langFilePath);
        }

        for ($i = 0; $i < ($totalStr - 1); $i++) {
            $langFilePath .= '/' . $retrieve[$i];
        }
        $langFilePath .= '.php';

        if (!is_file($langFilePath)) {
            return '';
        }

        $lang = require $langFilePath;

        $langKey = $retrieve[$totalStr - 1];
        return isset($lang[$langKey]) ? $lang[$langKey] : '';
    }
}

/**
 * uniqid, sha1 length 40 char
 */
if (function_exists('unique') === false) {
    function unique()
    {
        return hash('sha1', uniqid(mt_rand(), TRUE));
    }
}

/**
 * password hash and verify
 */
if (function_exists('password_encode') === false) {
    function password_encode(string $pwd)
    {
        return password_hash($pwd, PASSWORD_DEFAULT);
    }
}
if (function_exists('password_check') === false) {
    function password_check(string $pwd, string $hash)
    {
        return password_verify($pwd, $hash);
    }
}

/**
 * encrypt and decrypt
 *
 * AES-128-CBC
 * AES-256-CBC
 */
if (function_exists('generateKey') === false) {
    function generateKey(string $cipher = 'AES-128-CBC')
    {
        return bin2hex(openssl_random_pseudo_bytes($cipher === 'AES-128-CBC' ? 16 : 32));
    }
}
if (function_exists('encrypt') === false) {
    function encrypt(string $str, string $cipher = 'AES-128-CBC')
    {
        $key = hex2bin(env('APP_KEY'));
        $length = mb_strlen($key, '8bit');
        $lengthCheck = (($cipher === 'AES-128-CBC' && $length === 16) || ($cipher === 'AES-256-CBC' && $length === 32));
        if (!$lengthCheck) {
            throw new Exception('The only supported ciphers are AES-128-CBC and AES-256-CBC with the correct key lengths.');
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        $value = \openssl_encrypt(serialize($str), $cipher, $key, 0, $iv);

        if ($value === false) {
            throw new Exception('Could not encrypt the data.');
        }

        $iv = base64_encode($iv);
        $mac = hash_hmac('sha256', $iv.$value, $key);

        $json = json_encode(compact('iv', 'value', 'mac'));

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Could not encrypt the data.');
        }

        return base64_encode($json);
    }
}
if (function_exists('decrypt') === false) {
    function decrypt(string $payload, string $cipher = 'AES-128-CBC')
    {
        $bytes = openssl_random_pseudo_bytes($cipher === 'AES-128-CBC' ? 16 : 32);
        $key = hex2bin(env('APP_KEY'));
        $payload = json_decode(base64_decode($payload), true);

        $validPayload = is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']) &&
        strlen(base64_decode($payload['iv'], true)) === openssl_cipher_iv_length($cipher);
        if (!$validPayload) {
            throw new Exception('The payload is invalid.');
        }

        $calculated = hash_hmac('sha256', hash_hmac('sha256', $payload['iv'].$payload['value'], $key), $bytes, true);
        $validMac = hash_equals(hash_hmac('sha256', $payload['mac'], $bytes, true), $calculated);
        if (!$validMac) {
            throw new Exception('The MAC is invalid.');
        }

        $iv = base64_decode($payload['iv']);

        $decrypted = \openssl_decrypt(
            $payload['value'], $cipher, $key, 0, $iv
        );

        if ($decrypted === false) {
            throw new Exception('Could not decrypt the data.');
        }

        return unserialize($decrypted);
    }
}

/**
 * check is CLI
 */
if (function_exists('is_cli_request') === false) {
    function is_cli_request()
    {
        return (php_sapi_name() === 'cli' OR defined('STDIN'));
    }
}
