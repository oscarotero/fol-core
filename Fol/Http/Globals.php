<?php
/**
 * Fol\Http\Globals
 *
 * Class to detect and return http data from global $_SERVER/$_POST/$_GET/$_FILES arrays
 */
namespace Fol\Http;

class Globals
{
    /**
     * Gets a value from $_SERVER variable
     *
     * @param string $name The parameter name
     * 
     * @return mixed The parameter value or null
     */
    public static function get($name)
    {
        return isset($_SERVER[$name]) ? $_SERVER[$name] : null;
    }


    /**
     * Checks a value from $_SERVER
     *
     * @param string $name The parameter name
     * 
     * @return boolean
     */
    public static function has($name)
    {
        return !empty($_SERVER[$name]);
    }


    /**
     * Gets the global request scheme
     * 
     * @return string
     */
    public static function getScheme()
    {
        return self::get('HTTPS') === 'on' ? 'https' : 'http';
    }


    /**
     * Gets the global request port
     * 
     * @return integer
     */
    public static function getPort()
    {
        return self::get('X_FORWARDED_PORT') ?: self::get('SERVER_PORT') ?: 80;
    }


    /**
     * Gets the global request url
     * 
     * @return string
     */
    public static function getUrl()
    {
        return self::getScheme().'://'.self::get('SERVER_NAME').':'.self::getPort().self::get('REQUEST_URI');
    }


    /**
     * Gets the global request method
     * 
     * @return string
     */
    public static function getMethod()
    {
        $method = self::get('REQUEST_METHOD');

        if ($method === 'POST' && self::has('X_HTTP_METHOD_OVERRIDE')) {
            return self::get('X_HTTP_METHOD_OVERRIDE');
        }

        return $method ?: 'GET';
    }


    /**
     * Gets the all global request ips
     * 
     * @return string|null
     */
    public static function getIps()
    {
        $ips = [];

        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!self::has($key)) {
                continue;
            }

            foreach (explode(',', self::get($key)) as $ip) {
                if (!empty($ip) && $ip !== 'unknown') {
                    $ips[] = $ip;
                }
            }
        }

        return $ips;
    }


    /**
     * Gets the global headers
     * 
     * @return array
     */
    public static function getHeaders()
    {
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headers[str_replace('_', '-', substr($name, 5))] = $value;
                continue;
            }

            if (in_array($name, array('CONTENT_LENGTH', 'CONTENT_MD5', 'CONTENT_TYPE'))) {
                $headers[str_replace('_', '-', $name)] = $value;
            }
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
            $headers['AUTHORIZATION'] = 'Basic '.base64_encode($_SERVER['PHP_AUTH_USER'].':'.$pass);
        }

        return $headers;
    }


    /**
     * Gets the global $_GET values
     * 
     * @return array
     */
    public static function getGet()
    {
        return (array) filter_input_array(INPUT_GET);
    }


    /**
     * Gets the global $_POST values
     * 
     * @return array
     */
    public static function getPost()
    {
        return (array) filter_input_array(INPUT_POST);
    }


    /**
     * Gets the global $_COOKIES values
     * 
     * @return array
     */
    public static function getCookies()
    {
        return (array) filter_input_array(INPUT_COOKIE);
    }


    /**
     * Gets the global $_FILES values (and normalizes its structure)
     * 
     * @return array
     */
    public static function getFiles()
    {
        if (empty($_FILES)) {
            return [];
        }

        return self::fixArray($_FILES);
    }


    /**
     * Fix the $files order by converting from default wierd schema
     * [first][name][second][0], [first][error][second][0]...
     * to a more straightforward one.
     * [first][second][0][name], [first][second][0][error]...
     *
     * @param array $files An array with all files values
     *
     * @return array The files values fixed
     */
    private static function fixArray($files)
    {
        if (isset($files['name'], $files['tmp_name'], $files['size'], $files['type'], $files['error'])) {
            return self::moveToRight($files);
        }

        foreach ($files as &$file) {
            $file = self::fixArray($file);
        }

        return $files;
    }


    /**
     * Private function used by fixArray
     *
     * @param array $files An array with all files values
     *
     * @return array The files values fixed
     */
    private static function moveToRight($files)
    {
        if (!is_array($files['name'])) {
            return $files;
        }

        $results = array();

        foreach ($files['name'] as $index => $name) {
            $reordered = array(
                'name' => $files['name'][$index],
                'tmp_name' => $files['tmp_name'][$index],
                'size' => $files['size'][$index],
                'type' => $files['type'][$index],
                'error' => $files['error'][$index]
            );

            if (is_array($name)) {
                $reordered = self::moveToRight($reordered);
            }

            $results[$index] = $reordered;
        }

        return $results;
    }


    /**
     * Gets the global request payload
     * 
     * @return null|array
     */
    public static function getPayload()
    {
        if (!in_array(self::getMethod(), ['POST', 'PUT', 'DELETE'])) {
            return null;
        }

        $contentType = self::get('CONTENT_TYPE');

        if (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
            parse_str(file_get_contents('php://input'), $data);
            return $data;
        }

        if (strpos($contentType, 'application/json') === 0) {
            return json_decode(file_get_contents('php://input'), true);
        }

        return null;
    }
}