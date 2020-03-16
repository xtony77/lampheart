<?php

namespace lampheart\Support\Http;

use lampheart\Support\Http\Response;
use lampheart\Support\Http\Security;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Validation\Factory;
use Illuminate\Translation\Translator;

trait Request
{
    use Security;

    private $postData;

    public function __construct()
    {
        $this->postData = array();
    }

    public function requestAll()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'DELETE')
        {
            return $this->get();
        }
        else
        {
            return $this->post();
        }
    }

    public function requestInput($index)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'DELETE')
        {
            return $this->get($index);
        }
        else
        {
            return $this->post($index);
        }
    }

    /**
     * Fetch from array
     *
     * This is a helper function to retrieve values from global arrays
     *
     * @param $array
     * @param string $index
     * @return array|bool|string|string[]|null
     */
    private function _fetch_from_array(&$array, $index = '')
    {
        if (!isset($array[$index]))
        {
            return array();
        }

        $str = $this->xss_clean($array[$index]);
        if (is_numeric($str)) {
            return $str + 0;
        }
        return $str;
    }

    /**
     * Fetch an item from the GET array
     *
     * @param null $index
     * @return array|bool|string|string[]|null
     */
    private function get($index = NULL)
    {
        // Check if a field has been provided
        if ($index === NULL AND ! empty($_GET))
        {
            $get = array();

            // loop through the full _GET array
            foreach (array_keys($_GET) as $key)
            {
                $get[$key] = $this->_fetch_from_array($_GET, $key);
            }
            return $get;
        }

        return $this->_fetch_from_array($_GET, $index);
    }

    /**
     * Fetch an item from the POST array
     *
     * @param null $index
     * @return array|bool|string|string[]|null
     */
    private function post($index = NULL)
    {
        if (empty($this->postData))
        {
            $this->postData = file_get_contents('php://input');
            $this->postData = strpos($_SERVER["CONTENT_TYPE"], 'json') ? json_decode($this->postData, true) : $this->postData;
        }

        // Check if a field has been provided
        if ($index === NULL AND ! empty($this->postData))
        {
            $post = array();

            // Loop through the full _POST array and return it
            foreach (array_keys($this->postData) as $key)
            {
                $post[$key] = $this->_fetch_from_array($this->postData, $key);
            }
            return $post;
        }

        return $this->_fetch_from_array($this->postData, $index);
    }

    /**
     * Validate requests
     *
     * @param array $requests
     * @param array $rules
     * @return array
     * @throws \Exception
     */
    public function requestValidate(array $requests, array $rules)
    {
        $langPath = dirname(dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))))).'/resources/lang';

        if (!file_exists($langPath)) {
            throw new \Exception('Validate localization not exist: '.$langPath);
        }

        $loader = new FileLoader(new Filesystem(), $langPath);
        $loader->addNamespace('lang', $langPath);
        $loader->load('en', 'validation', 'lang');
        $validator = new Factory(new Translator($loader, 'en'));

        $validatorResult = $validator->make($requests, $rules);

        if ($validatorResult->fails())
        {
            header("HTTP/1.0 422 Unprocessable Entity");
            echo Response::json($validatorResult->errors());
            exit();
        }

        return $requests;
    }

    /**
     * Request Headers
     *
     * In Apache, you can simply call apache_request_headers(), however for
     * people running other webservers the function is undefined.
     *
     * @return array
     */
    public function request_headers()
    {
        $headers = [];

        foreach ($_SERVER as $key => $val)
        {
            if (strncmp($key, 'HTTP_', 5) === 0)
            {
                $headers[substr($key, 5)] = $this->_fetch_from_array($_SERVER, $key);
            }
        }

        return $headers;
    }
}