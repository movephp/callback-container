<?php

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