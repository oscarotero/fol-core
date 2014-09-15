<?php
/**
 * Fol\Http\Url
 *
 * Class to represent and manipulate an url
 */
namespace Fol\Http;

class Url
{
    protected static $defaultPorts = [
        'http' => 80,
        'https' => 433
    ];

    protected $scheme;
    protected $host;
    protected $port;
    protected $user;
    protected $password;
    protected $path;
    protected $filename;
    protected $extension;
    protected $fragment;

    public $query;


    /**
     * Generates an url using its parts
     *
     * @param string      $scheme
     * @param string      $host
     * @param integer     $port
     * @param string|null $user
     * @param string|null $password
     * @param string      $path
     * @param array       $query
     * @param string      $fragment
     *
     * @return string
     */
    public static function build($scheme, $host, $port, $user, $password, $path, array $query = null, $fragment = null)
    {
        if (isset(self::$defaultPorts[$scheme]) && (self::$defaultPorts[$scheme] == $port)) {
            $port = null;
        }

        return sprintf('%s%s%s%s%s%s%s',
            $scheme ? sprintf('%s:', $scheme) : '',
            $user ? sprintf('%s%s@', $user, $password ? sprintf(':%s', $password) : '') : '',
            $host ? sprintf('//%s', $host) : '',
            $port ? sprintf(':%d', $port) : '',
            $path,
            $query ? '?'.http_build_query($query) : '',
            $fragment ? '#'.$fragment : ''
        );
    }


    /**
     * Constructor
     *
     * @param string $url
     */
    public function __construct($url)
    {
        $this->query = new RequestParameters();
        $this->setUrl($url);
    }


    /**
     * Set a new url
     *
     * @param string $url The new url
     */
    public function setUrl($url)
    {
        $url = parse_url($url) + [
            'scheme' => null,
            'host' => null,
            'port' => null,
            'user' => null,
            'pass' => null,
            'path' => '',
            'fragment' => ''
        ];

        $this->setScheme($url['scheme']);
        $this->setHost($url['host']);
        $this->setPort($url['port']);
        $this->setUser($url['user']);
        $this->setPassword($url['pass']);
        $this->setPath($url['path']);
        $this->setFragment($url['fragment']);

        if (isset($url['query'])) {
            parse_str(html_entity_decode($url['query']), $query);

            $this->query->set($query);
        }
    }


    /**
     * Gets the url
     * 
     * @param boolean $query True to add the query to the url (false by default)
     * 
     * @return string
     */
    public function getUrl($query = false) {
        return self::build($this->getScheme(), $this->getHost(), $this->getPort(), $this->getUser(), $this->getPassword(), $this->getPath(), ($query ? $this->query->get() : []), $this->getFragment());
    }


    /**
     * Gets the url path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path.$this->filename.($this->extension ? ".{$this->extension}" : '');
    }


    /**
     * Sets the url path
     * 
     * @param string $path
     */
    public function setPath($path)
    {
        $parts = pathinfo(urldecode($path)) + ['dirname' => '/', 'basename' => ''];

        $path = str_replace('\\', '/', $parts['dirname']);

        if ($path === '.') {
            $path = '/';
        } else {
            if ($path[0] !== '/') {
                $path = "/{$path}";
            }

            if (substr($path, -1) !== '/') {
                $path = "{$path}/";
            }
        }

        $this->path = $path;
        $this->setFilename($parts['basename']);
    }


    /**
     * Gets the url scheme (for example: http)
     *
     * @return string
     */
    public function getScheme()
    {
        return $this->scheme;
    }


    /**
     * Sets the url scheme
     *
     * @param string $scheme
     */
    public function setScheme($scheme)
    {
        $this->scheme = strtolower($scheme);
    }


    /**
     * Gets the url host
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Sets the url host
     *
     * @param string $host
     */
    public function setHost($host)
    {
        $this->host = strtolower($host);
    }


    /**
     * Gets the url port
     *
     * @return int|null
     */
    public function getPort()
    {
        return $this->port ?: (isset(self::$defaultPorts[$this->scheme]) ? self::$defaultPorts[$this->scheme] : null);
    }

    /**
     * Sets the url port
     *
     * @param int|null $port
     */
    public function setPort($port)
    {
        $this->port = ($port === null) ? $port : intval($port);
    }


    /**
     * Gets the url user
     *
     * @return string|null
     */
    public function getUser()
    {
        return $this->user;
    }


    /**
     * Sets the url user
     *
     * @param string|null $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }


    /**
     * Gets the url password
     *
     * @return string|null
     */
    public function getPassword()
    {
        return $this->password;
    }


    /**
     * Sets the url password
     *
     * @param string|null $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Gets the url fragment
     *
     * @return string
     */
    public function getFragment()
    {
        return $this->fragment;
    }


    /**
     * Sets the url fragment
     * 
     * @param string $fragment
     */
    public function setFragment($fragment)
    {
        $this->fragment = $fragment;
    }


    /**
     * Gets the url extension
     *
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }


    /**
     * Sets the url extension
     * 
     * @param string $extension
     */
    public function setExtension($extension)
    {
        $this->extension = strtolower($extension);
    }


    /**
     * Gets the url filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }


    /**
     * Sets the url filename
     * 
     * @param string $file
     */
    public function setFilename($file)
    {
        $parts = pathinfo($file) + ['filename' => '', 'extension' => ''];
        $this->filename = $parts['filename'];
        $this->setExtension($parts['extension']);
    }
}