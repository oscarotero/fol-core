<?php
/**
 * Fol\Http\Sessions\Session
 *
 * Class to manage the session
 */
namespace Fol\Http\Sessions;

use Fol\ContainerTrait;
use Fol\Http\Request;
use Fol\Http\Response;

class Session implements \ArrayAccess, SessionInterface
{
    use ContainerTrait;

    protected $id;
    protected $name;
    protected $request;
    protected $userResolver;
    protected $user;


    /**
     * Construct and loads the session data
     *
     * @param string $id
     * @param string $name
     */
    public function __construct($id = null, $name = null)
    {
        $this->id = $id;
        $this->name = $name;
    }


    /**
     * Defines how the user will be called
     * 
     * @param callable $resolver
     */
    public function defineUser(\Closure $resolver)
    {
        $this->userResolver = $resolver;
    }


    /**
     * Resolves the user resolver and returns thee result
     * 
     * @return mixed
     */
    public function getUser()
    {
        if (!isset($this->user)) {
            if (!$this->userResolver) {
                throw new \Exception("You must specify a function to get the user");
            }

            $resolver = $this->userResolver;
            $this->user = $resolver($this, $this->request);
        }

        return $this->user;
    }


    /**
     * {@inheritDoc}
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }


    /**
     * {@inheritDoc}
     */
    public function prepare(Response $response) {}


    /**
     * Close the session and save the data.
     */
    public function save() {}


    /**
     * Destroy the current session deleting the data
     */
    public function destroy()
    {
        $this->delete();
    }


    /**
     * Get the current session id
     *
     * @return string The id
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * Get the session name
     *
     * @return string The name
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Regenerate the id for the current session
     * 
     * @param boolean $destroy  Set true to destroy the current data
     * @param integer $lifetime The new session duration
     */
    public function regenerate($destroy = false, $lifetime = null) {}


    /**
     * Get a flash value (read only once)
     *
     * @param string $name    The value name. If it is not defined, returns all stored variables
     * @param string $default A default value in case the variable is not defined
     *
     * @return string The value of the variable or the default value.
     * @return array  All stored variables in case no name is defined.
     */
    public function getFlash($name = null, $default = null)
    {
        if ($name === null) {
            return isset($this->items['_flash']) ? $this->items['_flash'] : [];
        }

        if (isset($this->items['_flash'][$name])) {
            $default = $this->items['_flash'][$name];
            unset($this->items['_flash'][$name]);
        }

        return $default;
    }


    /**
     * Set a new flash value
     *
     * @param string|array $name  The variable name or an array of variables
     * @param string       $value The value of the variable
     */
    public function setFlash($name, $value = null)
    {
        if (!isset($this->items['_flash'])) {
            $this->items['_flash'] = [];
        }

        if (is_array($name)) {
            $this->items['_flash'] = array_replace($this->items['_flash'], $name);
        } else {
            $this->items['_flash'][$name] = $value;
        }
    }


    /**
     * Check if a flash variable is defined or not (but does not remove it)
     *
     * @param string $name The variable name.
     *
     * @return boolean True if it's defined, false if not
     */
    public function hasFlash($name)
    {
        return (isset($this->items['_flash']) && array_key_exists($name, $this->items['_flash']));
    }
}
