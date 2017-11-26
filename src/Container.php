<?php
/**
 * Copyright 2017 Sinkevich Alexey
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Movephp\CallbackContainer;

use Movephp\CallbackContainer\Exception;
use Psr\Container\ContainerInterface as PsrContainer;

/**
 * Class Container
 * @package Movephp\CallbackContainer
 */
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
     * @var null|array|callable
     */
    private $callback = null;

    /**
     * @var null|Parameter[]
     */
    private $parameters = null;

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
     *
     */
    public function __clone()
    {
        if (self::$psrContainerGlobal && !$this->psrContainer) {
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
     * @param array|callable $callback
     * @return ContainerInterface
     */
    public function make($callback): ContainerInterface
    {
        $container = clone($this);
        $container->setCallback($callback);
        return $container;
    }

    /**
     * @return \Closure
     * @throws Exception\CallbackRequired
     */
    public function closure(): \Closure
    {
        if (is_null($this->closure)) {
            if (is_null($this->callback)) {
                throw new Exception\CallbackRequired('Can\'t build Closure: callback is not set');
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
     * @throws Exception\NonSerializableException
     */
    public function serialize(): string
    {
        if (!$this->isSerializable()) {
            throw new Exception\NonSerializableException(sprintf(
                'Can\'t serialize given callable: %s',
                print_r($this->original, true)
            ));
        }
        return serialize([
            'callback'   => $this->callback,
            'parameters' => $this->parameters
        ]);
    }

    /**
     * @param string $serialized
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function unserialize($serialized): void
    {
        $value = unserialize($serialized);
        if (!is_array($value)) {
            throw new \BadMethodCallException(sprintf(
                'Serialized string must represents an array: %s',
                $serialized
            ));
        }
        foreach (['callback', 'parameters'] as $field) {
            if (!array_key_exists($field, $value)) {
                throw new \BadMethodCallException(sprintf(
                    'Can\'t find field "%s" in given serialized string: %s',
                    $field, $serialized
                ));
            }
        }

        $callback = $value['callback'];
        if (!$this->checkCallback($callback)) {
            throw new \InvalidArgumentException(sprintf(
                '$callback must be an correct callable or an array like ["class_name_or_DI_container_key", "method_name"], given: %s',
                print_r($callback, true)
            ));
        }
        $this->callback = $callback;

        $parameters = $value['parameters'];
        if (!is_null($parameters)) {
            if (!is_array($parameters)) {
                throw new \InvalidArgumentException(sprintf(
                    '$parameters must be an array of "%s", given: %s',
                    Parameter::class, print_r($parameters, true)
                ));
            }
            foreach ($parameters as $parameter) {
                if (!$parameter instanceof Parameter) {
                    throw new \InvalidArgumentException(sprintf(
                        '$parameters must be an array of "%s", given: %s',
                        Parameter::class, print_r($parameters, true)
                    ));
                }
            }
            $this->parameters = $parameters;
        }

        if (self::$psrContainerGlobal) {
            $this->setPsrContainer(self::$psrContainerGlobal);
        }
    }

    /**
     * @return Parameter[]
     */
    public function parameters(): array
    {
        if (is_null($this->parameters)) {
            if (!is_null($this->closure)) {
                $reflectionMethod = new \ReflectionFunction($this->closure());
            } elseif (is_string($this->callback)) {
                $reflectionMethod = new \ReflectionFunction($this->callback);
            } else {
                $class = $this->callback[0];
                $method = $this->callback[1];
                if (class_exists($class)) {
                    $reflectionMethod = new \ReflectionMethod($class, $method);
                } else {
                    $reflectionMethod = new \ReflectionFunction($this->closure());
                }
            }
            foreach ($reflectionMethod->getParameters() as $parameter) {
                $this->parameters[] = new Parameter($parameter);
            }
        }
        return $this->parameters;
    }

    /**
     * @param $callback
     * @throws Exception\CantBeInvokedException
     * @throws Exception\PsrContainerRequired
     * @throws Exception\UnacceptableCallableException
     * @throws \InvalidArgumentException
     */
    private function setCallback($callback): void
    {
        if (!$this->checkCallback($callback)) {
            throw new \InvalidArgumentException(sprintf(
                '$callback must be an correct callable or an array like ["class_name_or_DI_container_key", "method_name"], given: %s',
                print_r($callback, true)
            ));
        }

        $this->original = $callback;
        $this->closure = null;
        $this->callback = null;

        if (is_string($callback) && strpos($callback, '::') !== false) {
            // String "class::method" converts to array ["class", "method"]
            $callback = explode('::', $callback, 2);
            if (isset($callback[1])) {
                $callback[1] = trim($callback[1], '()');
            }
        }

        if ($callback instanceof \Closure) {
            // \Closure - not callback
            $this->closure = $callback;
            return;
        }

        if (is_string($callback)) {
            // "function_name" - callback
            $this->callback = $callback;
            return;
        }

        /**
         * $callback is not \Closure, not string, so it is array
         * @see checkCallback()
         */
        $classOrObject = $callback[0];
        $method = $callback[1];

        if (is_object($classOrObject)) {
            $reflectionClass = new \ReflectionClass($classOrObject);
            if (!$reflectionClass->isAnonymous()) {
                // [object, "method"] - callback
                $this->callback = [get_class($classOrObject), $method];
                return;
            } else {
                // [anonymous_object, "method"] - not callback
                $this->closure = \Closure::fromCallable($callback);
                return;
            }
        }

        /**
         * $classOrObject is not object, so it is string
         */

        if (class_exists($classOrObject)) {
            $reflectionClass = new \ReflectionClass($classOrObject);
            $reflectionMethod = $reflectionClass->getMethod($method);
            if (!$reflectionMethod->isStatic() && !$reflectionClass->isInstantiable()) {
                throw new Exception\CantBeInvokedException(sprintf(
                    'Given callable can\'t be invoked: class "%s" is not instantiable and method "%s" is not static. Callable: %s',
                    $classOrObject,
                    $method,
                    print_r($this->original, true)
                ));
            }
            if ($reflectionClass->isAnonymous()) {
                if ($reflectionMethod->isStatic()) {
                    // ["anonymous_class", "static_method"] - not callback
                    $this->closure = \Closure::fromCallable($callback);
                    return;
                } else {
                    // ["anonymous_class", "non_static_method"] - UNACCEPTABLE
                    throw new Exception\UnacceptableCallableException(sprintf(
                        'Unacceptable callable - name of anonymous class and non-static method: %s',
                        print_r($this->original, true)
                    ));
                }
            }

            // ["class", "method"] - callback
            $this->callback = $callback;
            return;

        } else {
            // Let assume that $classOrObject is DI-container key
            if (is_null($this->psrContainer)) {
                throw new Exception\PsrContainerRequired(sprintf(
                    'Its required to put a PSR-container before creating %s with non-existent class: %s',
                    __CLASS__, print_r($callback, true)
                ));
            }
            $this->callback = $callback;
            return;
        }
    }

    /**
     * @param callable|array $callback
     * @return bool
     */
    private function checkCallback($callback): bool
    {
        if (is_callable($callback)) {
            return true;
        }
        if (is_string($callback) && strpos($callback, '::') !== false) {
            // String "class::method" converts to array ["class", "method"]
            $callback = explode('::', $callback, 2);
        }
        if (
            is_array($callback) && count($callback) === 2 &&
            isset($callback[0]) && is_string($callback[0]) &&
            isset($callback[1]) && is_string($callback[1])
        ) {
            return true;
        }
        return false;
    }

    /**
     * @param array|callable $callback
     * @return \Closure
     */
    private function makeClosure($callback): \Closure
    {
        if (is_string($callback)) {
            return \Closure::fromCallable($callback);
        }
        $class = $callback[0];
        $method = $callback[1];
        if (class_exists($class) && (new \ReflectionMethod($class, $method))->isStatic()) {
            return \Closure::fromCallable($callback);
        } else {
            $object = $this->makeInstance($class);
            return \Closure::fromCallable([$object, $method]);
        }
    }

    /**
     * @param string $class
     * @return mixed
     * @throws Exception\ClassNotFound
     * @throws Exception\PsrContainerRequired
     */
    private function makeInstance(string $class)
    {
        if (!is_null($this->psrContainer) && $this->psrContainer->has($class)) {
            return $this->psrContainer->get($class);
        } else {
            // Trying to make object without PSR-container
            if (class_exists($class)) {
                $reflectionClass = new \ReflectionClass($class);
                $constructor = $reflectionClass->getConstructor();
                if ($reflectionClass->isInstantiable() && (!$constructor || empty($constructor->getParameters()))) {
                    return new $class();
                }
            }
            if (is_null($this->psrContainer)) {
                throw new Exception\PsrContainerRequired(sprintf(
                    'Can\'t make instance of class "%s" without PSR-container',
                    $class
                ));
            } else {
                throw new Exception\ClassNotFound(sprintf(
                    'Class "%s" is not found in PSR-container',
                    $class
                ));
            }
        }
    }
}
