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

/**
 * Class Parameter
 * @package Movephp\CallbackContainer
 */
class Parameter
{
    /**
     * @var string
     */
    private $name = '';

    /**
     * @var string
     */
    private $type = '';

    /**
     * @var bool
     */
    private $variadic = false;

    /**
     * @var bool
     */
    private $optional = false;

    /**
     * @var null|mixed
     */
    private $default = null;

    /**
     * Parameter constructor.
     * @param \ReflectionParameter $parameter
     */
    public function __construct(\ReflectionParameter $parameter)
    {
        $this->name = $parameter->getName();
        $this->variadic = $parameter->isVariadic();

        if ($parameter->hasType()) {
            $this->type = (string)($parameter->getType());
        }
        if ($parameter->isOptional() && $parameter->isDefaultValueAvailable()) {
            $this->optional = true;
            $this->default = $parameter->getDefaultValue();
        }
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function hasType(): bool
    {
        return $this->type !== '';
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isVariadic(): bool
    {
        return $this->variadic;
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * @return mixed|null
     */
    public function getDefault()
    {
        return $this->default;
    }
}