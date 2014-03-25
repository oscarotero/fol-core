<?php
/**
 * Fol\Http\Router\Route
 *
 * Class to manage a http route
 */
namespace Fol\Http\Router;

use Fol\ContainerTrait;

use Fol\Http\Request;
use Fol\Http\Response;
use Fol\Http\HttpException;

class Route implements \ArrayAccess
{
    use ContainerTrait;

    protected $name;
    protected $target;

    protected $ip;
    protected $method;
    protected $scheme;
    protected $host;
    protected $port;
    protected $path;
    protected $format;
    protected $language;


    /**
     * Constructor
     *
     * @param string $name   The route name
     * @param array  $config The available options
     */
    public function __construct ($name, array $config = array())
    {
        $this->name = $name;
        $this->target = $config['target'];

        $this->ip = isset($config['ip']) ? $config['ip'] : null;
        $this->method = isset($config['method']) ? $config['method'] : null;
        $this->scheme = isset($config['scheme']) ? $config['scheme'] : null;
        $this->host = isset($config['host']) ? $config['host'] : null;
        $this->port = isset($config['port']) ? $config['port'] : null;
        $this->path = isset($config['path']) ? $config['path'] : null;
        $this->format = isset($config['format']) ? $config['format'] : null;
    }


    /**
     * Check the request
     *
     * @param Fol\Http\Request $request The request to check
     * @param array $params The params to check
     *
     * @return bool
     */
    public function checkRequest(Request $request, array $params)
    {
        foreach ($params as $param) {
            if (($value = $this->$param) === null) {
                continue;
            }

            $method = "get{$param}";

            if (is_array($value)) {
                if (!in_array($request->$method(), $value, true)) {
                    return false;
                }
            } else if ($request->$method() !== $value) {
                return false;
            }
        }

        return true;
    }


    /**
     * Check if the route match with the request
     *
     * @param Fol\Http\Request The request to check
     *
     * @return bool
     */
    public function match(Request $request)
    {
        return $this->checkRequest($request, ['ip', 'method', 'scheme', 'host', 'port', 'path', 'format', 'language']);
    }


    /**
     * Reverse the route
     *
     * @param array $defaults   Defaults values for scheme, host, port, path and format
     * @param array $parameters Optional array of parameters to use in URL
     *
     * @return string The url to the route
     */
    public function generate (array $defaults, array $parameters = array())
    {
        $scheme = $host = $port = $path = $format = '';

        foreach (['scheme', 'host', 'port', 'path', 'format'] as $name) {
            if ($this->$name) {
                $$name = is_array($this->$name) ? $this->$name[0] : $this->$name;
            } else if (isset($defaults[$name])) {
                $$name = $defaults[$name];
            }
        }

        $url = "{$scheme}://{$host}";

        if ($port && $port != 80) {
            $url .= ":{$port}";
        }

        if ($format && $path) {
            $path .= ".{$format}";
        }

        $url .= $path;

        if ($parameters) {
            $url .= '?'.http_build_query($parameters);
        }

        return $url;
    }


    /**
     * Reverse the route, returning a Request object
     *
     * @param array $defaults   Defaults values for scheme, host, port, path and format
     * @param array $parameters Optional array of parameters to use in URL
     *
     * @return Fol\Http\Request The request instance
     */
    public function generateRequest (array $defaults, array $parameters = array())
    {
        $request = new Request($this->generate($defaults, $parameters));

        if ($this->method) {
            $request->setMethod(is_array($this->method) ? $this->method[0] : $this->method);
        }

        if ($this->ip) {
            $request->setIp(is_array($this->ip) ? $this->ip[0] : $this->ip);
        }

        if ($this->language) {
            $request->setLanguage(is_array($this->language) ? $this->language[0] : $this->language);
        }

        return $request;
    }


    /**
     * Execute the route and returns the response object
     *
     * @param Fol\Http\Request $request The request to send to controller
     * @param array $arguments The arguments passed to the controller (after $request and $response instances)
     *
     * @return Fol\Http\Response
     */
    public function execute(Request $request, array $arguments = array())
    {
        ob_start();

        $return = '';
        $response = new Response;

        array_unshift($arguments, $request, $response);
        $request->route = $this;

        try {
            if (!is_array($this->target) || is_object($this->target[0])) {
                $return = call_user_func_array($this->target, $arguments);
            } else {
                list($class, $method) = $this->target;

                $class = new \ReflectionClass($class);

                $controller = $class->hasMethod('__construct') ? $class->newInstanceArgs($arguments) : $class->newInstance();

                $return = $class->getMethod($method)->invokeArgs($controller, $arguments);

                unset($controller);
            }
        } catch (\Exception $exception) {
            ob_clean();

            if (!($exception instanceof HttpException)) {
                $exception = new HttpException('Error processing request', 500, $exception);
            }

            throw $exception;
        }

        if ($return instanceof Response) {
            $return->appendContent(ob_get_clean());

            $return->prepare($request);

            return $return;
        }

        $response->appendContent(ob_get_clean().$return);

        $response->prepare($request);

        return $response;
    }
}
