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
    public function requestHeaders()
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

    /**
     * Fetch the IP Address
     *
     * @return	string
     */
    public function requestIpArray()
    {
        $ip_address = [];

        if (!empty($_SERVER["HTTP_CLIENT_IP"]) && $this->validIP($_SERVER["HTTP_CLIENT_IP"]))
        {
            $ip_address['SERVER']['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CLIENT_IP"];
        }

        if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]) && $this->validIP($_SERVER["HTTP_X_FORWARDED_FOR"]))
        {
            $ip_address['SERVER']['HTTP_X_FORWARDED_FOR'] = $_SERVER["HTTP_X_FORWARDED_FOR"];
        }

        if (!empty($_SERVER['REMOTE_ADDR']) && $this->validIP($_SERVER["REMOTE_ADDR"]))
        {
            $ip_address['SERVER']['REMOTE_ADDR'] = $_SERVER["REMOTE_ADDR"];
        }

        if (empty($ip_address))
        {
            $ip_address['SERVER']['EMPTY_HEADER'] = '0.0.0.0';
        }

        $ip_address[0] = reset($ip_address['SERVER']);

        return $ip_address;
    }

    /**
     * Validate IP Address
     *
     * @access	public
     * @param	string
     * @param	string	ipv4 or ipv6
     * @return	bool
     */
    public function validIP($ip, $which = '')
    {
        $which = strtolower($which);

        // First check if filter_var is available
        if (is_callable('filter_var'))
        {
            switch ($which) {
                case 'IPv4':
                    $flag = FILTER_FLAG_IPV4;
                    break;
                case 'IPv6':
                    $flag = FILTER_FLAG_IPV6;
                    break;
                default:
                    $flag = '';
                    break;
            }

            return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flag);
        }

        if ($which !== 'IPv6' && $which !== 'IPv4')
        {
            if (strpos($ip, ':') !== FALSE)
            {
                $which = 'IPv6';
            }
            elseif (strpos($ip, '.') !== FALSE)
            {
                $which = 'IPv4';
            }
            else
            {
                return FALSE;
            }
        }

        $func = '_valid'.$which;
        return $this->$func($ip);
    }

    // --------------------------------------------------------------------

    /**
     * Validate IPv4 Address
     *
     * Updated version suggested by Geert De Deckere
     *
     * @access	protected
     * @param	string
     * @return	bool
     */
    protected function _validIPv4($ip)
    {
        $ip_segments = explode('.', $ip);

        // Always 4 segments needed
        if (count($ip_segments) !== 4)
        {
            return FALSE;
        }
        // IP can not start with 0
        if ($ip_segments[0][0] == '0')
        {
            return FALSE;
        }

        // Check each segment
        foreach ($ip_segments as $segment)
        {
            // IP segments must be digits and can not be
            // longer than 3 digits or greater then 255
            if ($segment == '' OR preg_match("/[^0-9]/", $segment) OR $segment > 255 OR strlen($segment) > 3)
            {
                return FALSE;
            }
        }

        return TRUE;
    }

    // --------------------------------------------------------------------

    /**
     * Validate IPv6 Address
     *
     * @access	protected
     * @param	string
     * @return	bool
     */
    protected function _validIPv6($str)
    {
        // 8 groups, separated by :
        // 0-ffff per group
        // one set of consecutive 0 groups can be collapsed to ::

        $groups = 8;
        $collapsed = FALSE;

        $chunks = array_filter(
            preg_split('/(:{1,2})/', $str, NULL, PREG_SPLIT_DELIM_CAPTURE)
        );

        // Rule out easy nonsense
        if (current($chunks) == ':' OR end($chunks) == ':')
        {
            return FALSE;
        }

        // PHP supports IPv4-mapped IPv6 addresses, so we'll expect those as well
        if (strpos(end($chunks), '.') !== FALSE)
        {
            $ipv4 = array_pop($chunks);

            if ( ! $this->_validIPv4($ipv4))
            {
                return FALSE;
            }

            $groups--;
        }

        while ($seg = array_pop($chunks))
        {
            if ($seg[0] == ':')
            {
                if (--$groups == 0)
                {
                    return FALSE;	// too many groups
                }

                if (strlen($seg) > 2)
                {
                    return FALSE;	// long separator
                }

                if ($seg == '::')
                {
                    if ($collapsed)
                    {
                        return FALSE;	// multiple collapsed
                    }

                    $collapsed = TRUE;
                }
            }
            elseif (preg_match("/[^0-9a-f]/i", $seg) OR strlen($seg) > 4)
            {
                return FALSE; // invalid segment
            }
        }

        return $collapsed OR $groups == 1;
    }
}