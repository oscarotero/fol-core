<?php
namespace Fol\Container;

use Interop\Container\ContainerInterface;

/**
 * Fol\Container
 *
 * Universal dependency injection container
 */
class Container implements ContainerInterface
{
	private $containers = [];
    private $register = [];
    private $services = [];

    /**
     * Register new services
     *
     * @param integer|string $id       The service id
     * @param \Closure       $resolver A function that returns a service instance
     * @param boolean        $single   Whether the same instance should be return each time
     */
    public function register($id, \Closure $resolver = null, $single = true)
    {
    	$this->register[$id] = [$resolver, $single];
    }

    /**
     * Add new containers
     *
     * @param ContainerInterface $container
     */
    public function add(ContainerInterface $container)
    {
    	$this->containers[] = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
    	if (isset($this->register[$id]) || isset($this->services[$id])) {
    		return true;
    	}

    	foreach ($this->containers as $container) {
    		if ($container->has($id)) {
    			return true;
    		}
    	}

    	return false;
    }

    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        if (isset($this->register[$id])) {
            try {
                $service = call_user_func($this->register[$id][0]);

                if ($this->register[$id][1]) {
                    $this->services[$id] = $service;
                }

                return $service;

            } catch (\Exception $exception) {
                throw new ContainerException("Error on retrieve {$id}");
            }
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return $container->get($id);
            }
        }

        throw new NotFoundException("{$id} has not found");
    }

    /**
     * Set manually new services
     * 
     * @param integer|string $id
     * @param mixed          $service
     */
    public function set($id, $service)
    {
        $this->services[$id] = $services;
    }
}