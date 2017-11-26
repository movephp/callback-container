<?php

namespace Movephp\CallbackContainer\Tests\Fixtures;

class NormalClass
{
    public function method($arg): array
    {
        return [__CLASS__, __METHOD__, $arg];
    }

    public static function staticMethod($arg): array
    {
        return [__CLASS__, __METHOD__, $arg];
    }
}

class NonInstantiableClass
{
    private function __construct()
    {
    }

    public function method($arg): array
    {
    }

    public static function staticMethod($arg): array
    {
        return [__CLASS__, __METHOD__, $arg];
    }
}

function simpleTestFunction($arg)
{
    return [__FUNCTION__, $arg];
}