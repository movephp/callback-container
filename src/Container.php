<?php

namespace Movephp\CallbackContainer;

use Psr\Container\ContainerInterface as PsrContainer;

class Container implements ContainerInterface
{
    /**
     * @var null|PsrContainer
     */
    private $psrContainer = null;

    /**
     * @var null|PsrContainer
     */
    private static $psrContainerGlobal = null;

    /**
     * @var callable
     */
    private $original;

    /**
     * @var null|\Closure
     */
    private $closure = null;

    /**
     * @var null|callable
     */
    private $callback = null;

    /**
     * Container constructor.
     * @param PsrContainer|null $psrContainer
     */
    public function __construct(PsrContainer $psrContainer = null)
    {
        if ($psrContainer) {
            $this->setPsrContainer($psrContainer);
        } elseif (self::$psrContainerGlobal) {
            $this->setPsrContainer(self::$psrContainerGlobal);
        }
    }

    /**
     * @param PsrContainer $psrContainer
     */
    public function setPsrContainer(PsrContainer $psrContainer): void
    {
        $this->psrContainer = $psrContainer;
    }

    /**
     * @param PsrContainer $psrContainer
     */
    public static function setPsrContainerGlobal(PsrContainer $psrContainer): void
    {
        self::$psrContainerGlobal = $psrContainer;
    }

    /**
     * @param callable $callback
     * @return ContainerInterface
     */
    public function make(callable $callback): ContainerInterface
    {
        $container = clone($this);
        $container->setCallback($callback);
        return $container;
    }

    /**
     * @return \Closure
     */
    public function closure(): \Closure
    {
        if (is_null($this->closure)) {
            if (is_null($this->callback)) {
                throw new \BadMethodCallException('Can\'t build Closure: callable is not set');
            }
            $this->closure = $this->makeClosure($this->callback);
        }
        return $this->closure;
    }

    /**
     * @return bool
     */
    public function isSerializable(): bool
    {
        return !is_null($this->callback);
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        if (!$this->isSerializable()) {
            throw new \BadMethodCallException(sprintf(
                'Can\'t serialize given callable: %s',
                print_r($this->original, true)
            ));
        }
        return serialize($this->callback);
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized): void
    {
        $value = unserialize($serialized);
        if (!is_callable($this->callback)) {
            throw new \BadMethodCallException(sprintf(
                'Unserialized value is not callable: ',
                print_r($value, true)
            ));
        }
        $this->callback = $value;
        if (self::$psrContainerGlobal) {
            $this->setPsrContainer(self::$psrContainerGlobal);
        }
    }

    /**
     * @param callable $callback
     */
    private function setCallback(callable $callback): void
    {
        $this->original = $callback;
        $this->closure = null;
        $this->callback = null;

        if (is_string($callback) && strpos($callback, '::') !== false) {
            // String "class::method" converts to array ["class", "method"]
            $callback = explode('::', $callback, 2);
        }

        if ($callback instanceof \Closure) {
            // \Closure - not callback
            $this->closure = $callback;

        } elseif (is_string($callback)) {
            // "function_name" - callback
            $this->callback = $callback;

        } elseif (is_array($callback)) {
            $classOrObject = $callback[0];
            $method = $callback[1];

            $reflectionClass = new \ReflectionClass($classOrObject);
            $reflectionMethod = $reflectionClass->getMethod($method);

            if (is_object($classOrObject)) {
                if (!$reflectionClass->isAnonymous()) {
                    // [object, "method"] - callback
                    $this->callback = [get_class($classOrObject), $method];
                } else {
                    // [anonymous_object, "method"] - not callback
                    $this->closure = \Closure::fromCallable($callback);
                }
            } elseif (is_string($classOrObject)) {
                if (!$reflectionMethod->isStatic() && !$reflectionClass->isInstantiable()) {
                    throw new \BadMethodCallException(sprintf(
                        'Given callable can\'t be invoked: class "%s" is not instantiable and method "%s" is not static. Callable: %s',
                        $classOrObject,
                        $method,
                        print_r($this->original, true)
                    ));
                }
                if (!$reflectionClass->isAnonymous()) {
                    // ["class", "method"] - callback
                    $this->callback = $callback;
                } else {
                    if ($reflectionMethod->isStatic()) {
                        // ["anonymous_class", "static_method"] - not callback
                        $this->closure = \Closure::fromCallable($callback);
                    } else {
                        // ["anonymous_class", "nonstatic_method"] - UNACCEPTABLE
                        throw new \BadMethodCallException(sprintf(
                            'Unacceptable callable - name of anonymous class and nonstatic method: %s',
                            print_r($this->original, true)
                        ));
                    }
                }
            }
        }
    }

    /**
     * @param callable $callable
     * @return \Closure
     */
    private function makeClosure(callable $callable): \Closure
    {
        if ($callable instanceof \Closure) {
            return $callable;
        }
        if (is_string($callable)) {
            return \Closure::fromCallable($callable);
        }
        $class = $callable[0];
        $method = $callable[1];
        if ((new \ReflectionMethod($class, $method))->isStatic()) {
            return \Closure::fromCallable($callable);
        } else {
            $object = $this->makeInstance($class);
            return \Closure::fromCallable([$object, $method]);
        }
    }

    /**
     * @param $class
     * @return object
     */
    private function makeInstance($class)
    {
        if (!is_null($this->psrContainer) && $this->psrContainer->has($class)) {
            return $this->psrContainer->get($class);
        } else {
            // Trying to make object without PSR-container
            if (class_exists($class)) {
                $reflectionClass = new \ReflectionClass($class);
                if ($reflectionClass->isInstantiable() && empty($reflectionClass->getConstructor()->getParameters())) {
                    return new $class();
                }
            }
            if (is_null($this->psrContainer)) {
                throw new \BadMethodCallException(sprintf(
                    'Can\'t make instance of class "%s" without PSR-container',
                    $class
                ));
            } else {
                throw new \BadMethodCallException(sprintf(
                    'Class "%s" is not found in PSR-container',
                    $class
                ));
            }
        }
    }
}