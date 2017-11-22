<?php

namespace Movephp\CallbackContainer;

use Psr\Container\ContainerInterface as PsrContainer;

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
     * @param callable $callback
     * @return ContainerInterface
     */
    public function make(callable $callback): self;

    /**
     * @return \Closure
     */
    public function closure(): \Closure;

    /**
     * @return bool
     */
    public function isSerializable(): bool;
}