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

namespace Movephp\CallbackContainer;

use Psr\Container\ContainerInterface as PsrContainer;

/**
 * Interface ContainerInterface
 * @package Movephp\CallbackContainer
 */
interface ContainerInterface extends \Serializable
{
    /**
     * @param PsrContainer $psrContainer
     */
    public function setPsrContainer(PsrContainer $psrContainer): void;

    /**
     * @param PsrContainer $psrContainer
     */
    public static function setPsrContainerGlobal(PsrContainer $psrContainer): void;

    /**
     * @param callable|array $callback
     * @return ContainerInterface
     * @throws \InvalidArgumentException        If $callback is not callable and is not like ["class_name_or_DI_container_key", "method_name"
     * @throws Exception\CantBeInvokedException If $callback is like ["non_instantiable_class_name", "non_static_method"]
     * @throws Exception\UnacceptableCallableException If $callback is like ["anonymous_class_name", "non_static_method"]
     * @throws Exception\PsrContainerRequired   If $callback is like ["psr_container_key", "method"] and PSR-container is not set
     */
    public function make($callback): self;

    /**
     * @return \Closure
     * @throws Exception\CallbackRequired     If callback is not set
     * @throws Exception\PsrContainerRequired If $callback is like ["psr_container_key", "method"] and PSR-container is not set
     * @throws Exception\ClassNotFound        If $callback is like ["psr_container_key", "method"] and item with that key is not found in PSR-container
     */
    public function closure(): \Closure;

    /**
     * @return bool
     */
    public function isSerializable(): bool;

    /**
     * @return string
     * @throws Exception\NonSerializableException If given callback is not serializable
     */
    public function serialize(): string;

    /**
     * @param string $serialized
     * @throws \BadMethodCallException   If unserialize($serialized) does not match the expected structure
     * @throws \InvalidArgumentException If unserialized "callback" is invalid callback OR
     *                                   if unserialized "parameters" is invalid array of Parameters
     */
    public function unserialize($serialized): void;

    /**
     * @return Parameter[]
     */
    public function parameters(): array;
}