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
interface ContainerInterface extends \Serializable {
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
     */
    public function make($callback): self;

    /**
     * @return \Closure
     */
    public function closure(): \Closure;

    /**
     * @return bool
     */
    public function isSerializable(): bool;

    /**
     * @return Parameter[]
     */
    public function parameters(): array;
}