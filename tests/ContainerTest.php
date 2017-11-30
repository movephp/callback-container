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

namespace Movephp\CallbackContainer\Tests;

include_once(__DIR__ . '/fixtures.php');

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainer;
use Movephp\CallbackContainer\{
    Container, Parameter, Exception
};

/**
 * Class AutoloadTest
 * @package Movephp\CallbackContainer\Tests
 */
class ContainerTest extends TestCase
{
    /**
     *
     */
    private const ARG_VALUE = ' value ';

    /**
     * @return array
     */
    public function makeProvider(): array
    {
        $anonymousObject = new class
        {
            public function method($arg): array
            {
                return [__CLASS__, __METHOD__, $arg];
            }

            public static function staticMethod($arg): array
            {
                return [__CLASS__, __METHOD__, $arg];
            }
        };
        return [
            'invalid callback'                        => [
                ['asd', 'asd', 'asd'],
                false,
                null,
                \InvalidArgumentException::class
            ],
            'Closure'                                 => [
                function ($a) {
                    return 1;
                },
                false,
                1
            ],
            'string function'                         => [
                Fixtures\simpleTestFunction::class,
                true,
                Fixtures\simpleTestFunction(self::ARG_VALUE)
            ],
            '[object, method]'                        => [
                [new Fixtures\NormalClass(), 'method'],
                true,
                (new Fixtures\NormalClass())->method(self::ARG_VALUE)
            ],
            '[class, method]'                         => [
                [Fixtures\NormalClass::class, 'method'],
                true,
                (new Fixtures\NormalClass())->method(self::ARG_VALUE)
            ],
            '[class, static_method]'                  => [
                [Fixtures\NormalClass::class, 'staticMethod'],
                true,
                Fixtures\NormalClass::staticMethod(self::ARG_VALUE)
            ],
            'string "class::method"'                  => [
                'Movephp\CallbackContainer\Tests\Fixtures\NormalClass::method()',
                true,
                (new Fixtures\NormalClass())->method(self::ARG_VALUE)
            ],
            'string "class::static_method"'           => [
                'Movephp\CallbackContainer\Tests\Fixtures\NormalClass::staticMethod',
                true,
                Fixtures\NormalClass::staticMethod(self::ARG_VALUE)
            ],
            '[anonymous_object, method]'              => [
                [$anonymousObject, 'method'],
                false,
                $anonymousObject->method(self::ARG_VALUE)
            ],
            '[anonymous_class, method],'              => [
                [get_class($anonymousObject), 'method'],
                false,
                null,
                Exception\UnacceptableCallableException::class
            ],
            '[anonymous_class, static_method]'        => [
                [get_class($anonymousObject), 'staticMethod'],
                false,
                $anonymousObject->staticMethod(self::ARG_VALUE)
            ],
            '[non_instantiable_class, method]'        => [
                [Fixtures\NonInstantiableClass::class, 'method'],
                false,
                null,
                Exception\CantBeInvokedException::class
            ],
            '[non_instantiable_class, static_method]' => [
                [Fixtures\NonInstantiableClass::class, 'staticMethod'],
                true,
                Fixtures\NonInstantiableClass::staticMethod(self::ARG_VALUE)
            ],
            '[non_class, method]'                     => [
                ['PsrContainerKey', 'method'],
                false,
                null,
                Exception\PsrContainerRequiredException::class
            ]
        ];
    }

    /**
     * @param $callback
     * @param bool $expectedIsSerializable
     * @param $expectedClosureReturn
     * @param string $expectException
     * @dataProvider makeProvider
     */
    public function testMakeAndGetClosure(
        $callback,
        bool $expectedIsSerializable,
        $expectedClosureReturn,
        string $expectException = ''
    ): void {
        if ($expectException) {
            $this->expectException($expectException);
        }
        $factory = new Container();
        $container = $factory->make($callback);
        if (!$expectException) {
            $this->assertEquals(
                $expectedIsSerializable,
                $container->isSerializable()
            );
            $this->assertEquals(
                $expectedClosureReturn,
                call_user_func($container->closure(), self::ARG_VALUE)
            );
        }
    }

    /**
     *
     */
    public function testMakeWithPsrContainer(): void
    {
        $psrContainerMock = $this->getMockForAbstractClass(PsrContainer::class);
        $factory = new Container($psrContainerMock);
        $container = $factory->make(['PsrContainerKey', 'method']);
        $this->assertTrue($container->isSerializable());
    }

    /**
     *
     */
    public function testSetPsrContainer(): void
    {
        $psrContainerMock = $this->getMockForAbstractClass(PsrContainer::class);
        $factory = new Container();
        $factory->setPsrContainer($psrContainerMock);
        $container = $factory->make(['PsrContainerKey', 'method']);
        $this->assertTrue($container->isSerializable());
    }

    /**
     *
     */
    public function testClosureWithoutCallback(): void
    {
        $this->expectException(Exception\CallbackRequiredException::class);
        $container = new Container();
        $container->closure();
    }

    /**
     *
     */
    public function testClosureWithPsrContainer(): void
    {
        $object = new Fixtures\NormalClass();

        $psrContainerMock = $this->getMockForAbstractClass(PsrContainer::class);
        $psrContainerMock->expects($this->atLeastOnce())
            ->method('has')
            ->with($this->equalTo('PsrContainerKey'))
            ->willReturn(true);
        $psrContainerMock->expects($this->once())
            ->method('get')
            ->with($this->equalTo('PsrContainerKey'))
            ->willReturn($object);

        $factory = new Container($psrContainerMock);
        $container = $factory->make(['PsrContainerKey', 'method']);
        $this->assertEquals(
            $object->method(self::ARG_VALUE),
            call_user_func($container->closure(), self::ARG_VALUE)
        );
    }

    /**
     *
     */
    public function testClosureWithPsrContainerAndInvalidKey(): void
    {
        $this->expectException(Exception\ClassNotFoundException::class);

        $psrContainerMock = $this->getMockForAbstractClass(PsrContainer::class);
        $psrContainerMock->expects($this->atLeastOnce())
            ->method('has')
            ->with($this->equalTo('PsrContainerKey'))
            ->willReturn(false);

        $factory = new Container($psrContainerMock);
        $container = $factory->make(['PsrContainerKey', 'method']);
        $container->closure();
    }

    /**
     * @return array
     */
    public function serializeProvider(): array
    {
        return [
            'string function'                         => [
                'trim',
                serialize('trim')
            ],
            '[object, method]'                        => [
                [new Fixtures\NormalClass(), 'method'],
                serialize([Fixtures\NormalClass::class, 'method'])
            ],
            '[class, method]'                         => [
                [Fixtures\NormalClass::class, 'method'],
                serialize([Fixtures\NormalClass::class, 'method'])
            ],
            '[class, static_method]'                  => [
                [Fixtures\NormalClass::class, 'staticMethod'],
                serialize([Fixtures\NormalClass::class, 'staticMethod'])
            ],
            'string "class::method"'                  => [
                'Movephp\CallbackContainer\Tests\Fixtures\NormalClass::method()',
                serialize([Fixtures\NormalClass::class, 'method'])
            ],
            'string "class::static_method"'           => [
                'Movephp\CallbackContainer\Tests\Fixtures\NormalClass::staticMethod',
                serialize([Fixtures\NormalClass::class, 'staticMethod'])
            ],
            '[non_instantiable_class, static_method]' => [
                [Fixtures\NonInstantiableClass::class, 'staticMethod'],
                serialize([Fixtures\NonInstantiableClass::class, 'staticMethod'])
            ]
        ];
    }

    /**
     * @param $callback
     * @param string $expectedSerialized
     * @dataProvider serializeProvider
     */
    public function testSerialize($callback, string $expectedSerialized): void
    {
        $factory = new Container();
        $container = $factory->make($callback);
        $this->assertContains(
            $expectedSerialized,
            serialize($container)
        );
        $this->assertEquals(
            serialize($container),
            serialize(unserialize(serialize($container)))
        );
    }

    /**
     * @return array
     */
    public function nonSerializableProvider(): array
    {
        $anonymousObject = new class
        {
            public function method($arg): array
            {
                return [__CLASS__, __METHOD__, $arg];
            }

            public static function staticMethod($arg): array
            {
                return [__CLASS__, __METHOD__, $arg];
            }
        };
        return [
            'Closure'                          => [
                function ($a) {
                    return 1;
                }
            ],
            '[anonymous_object, method]'       => [
                [$anonymousObject, 'method']
            ],
            '[anonymous_class, static_method]' => [
                [get_class($anonymousObject), 'staticMethod']
            ]
        ];
    }

    /**
     * @param $callback
     * @dataProvider nonSerializableProvider
     */
    public function testSerializeNonSerializable($callback): void
    {
        $this->expectException(Exception\NonSerializableException::class);
        $factory = new Container();
        $container = $factory->make($callback);
        serialize($container);
    }

    public function unserializeCorruptedProvider(): array
    {
        return [
            'non array'                                 => [
                serialize('asd'),
                \BadMethodCallException::class
            ],
            'no "callback" field'                       => [
                serialize(['asd' => '', 'parameters' => '']),
                \BadMethodCallException::class
            ],
            'no "parameters" field'                     => [
                serialize(['callback' => '', 'asd' => '']),
                \BadMethodCallException::class
            ],
            '"callback" is non existent function'       => [
                serialize([
                    'callback'   => 'some_non_existent_function',
                    'parameters' => null
                ]),
                \InvalidArgumentException::class
            ],
            '"callback" is non callable array'          => [
                serialize([
                    'callback'   => ['non', 'callable', 'array'],
                    'parameters' => null
                ]),
                \InvalidArgumentException::class
            ],
            '"callback" is not array and is not string' => [
                serialize([
                    'callback'   => new Fixtures\NormalClass(),
                    'parameters' => null
                ]),
                \InvalidArgumentException::class
            ],
            '"parameters" is not array'                 => [
                serialize([
                    'callback'   => Fixtures\simpleTestFunction::class,
                    'parameters' => 'asd'
                ]),
                \InvalidArgumentException::class
            ],
            '"parameters" contains invalid items'       => [
                serialize([
                    'callback'   => Fixtures\simpleTestFunction::class,
                    'parameters' => [new \DateTime()]
                ]),
                \InvalidArgumentException::class
            ]
        ];
    }

    /**
     * @param string $value
     * @param string $expectedException
     * @dataProvider unserializeCorruptedProvider
     */
    public function testUnserializeCorrupted(string $value, string $expectedException): void
    {
        $this->expectException($expectedException);
        $value = 'C:' . strlen(Container::class) . ':"' . Container::class . '":' . strlen($value) . ':{' . $value . '}';
        unserialize($value);
    }

    /**
     *
     */
    public function testUnserializePsrContainerKeyWithoutPsrContainer(): void
    {
        $this->expectException(Exception\PsrContainerRequiredException::class);

        $psrContainerMock = $this->getMockForAbstractClass(PsrContainer::class);
        $factory = new Container($psrContainerMock);
        $container = $factory->make(['PsrContainerKey', 'method']);

        $container = unserialize(serialize($container));
        $container->closure();
    }

    /**
     * @return array
     */
    public function parametersProvider(): array
    {
        return [
            'Closure'             => [
                function ($a, $b, $c) {
                },
                ['a' => 'a', 'b' => 'b', 'c' => 'c']
            ],
            'function name'       => [
                Fixtures\simpleTestFunction::class,
                ['arg' => 'arg']
            ],
            '[class, method]'     => [
                [Fixtures\NormalClass::class, 'method'],
                ['arg' => 'arg']
            ],
            '[non_class, method]' => [
                ['PsrContainerKey', 'method'],
                ['arg' => 'arg']
            ]
        ];
    }

    /**
     * @param $callback
     * @param array $expectedArgs
     * @dataProvider parametersProvider
     */
    public function testParameters($callback, array $expectedArgs): void
    {
        $object = new Fixtures\NormalClass();
        $psrContainerMock = $this->getMockForAbstractClass(PsrContainer::class);
        $psrContainerMock->expects($this->any())
            ->method('has')
            ->with($this->equalTo('PsrContainerKey'))
            ->willReturn(true);
        $psrContainerMock->expects($this->any())
            ->method('get')
            ->with($this->equalTo('PsrContainerKey'))
            ->willReturn($object);

        $factory = new Container($psrContainerMock);
        $container = $factory->make($callback);
        $parameters = $container->parameters();

        $this->assertContainsOnlyInstancesOf(Parameter::class, $parameters);
        $this->assertEquals(
            $expectedArgs,
            array_map(
                function (Parameter $parameter): string {
                    return $parameter->name();
                },
                $parameters
            )
        );
    }

    /**
     *
     */
    public function testParametersSerialization(): void
    {
        $factory = new Container();
        $container = $factory->make([Fixtures\NormalClass::class, 'method']);
        $parameters1 = $container->parameters();

        $container = unserialize(serialize($container));
        $parameters2 = $container->parameters();

        $this->assertEquals($parameters1, $parameters2);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetPsrContainerGlobalBeforeConstructor(): void
    {
        $psrContainerMock = $this->getMockForAbstractClass(PsrContainer::class);
        Container::setPsrContainerGlobal($psrContainerMock);
        $factory = new Container();
        $container = $factory->make(['PsrContainerKey', 'method']);
        $this->assertTrue($container->isSerializable());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetPsrContainerGlobalAfterConstructor(): void
    {
        $psrContainerMock = $this->getMockForAbstractClass(PsrContainer::class);
        $factory = new Container();
        Container::setPsrContainerGlobal($psrContainerMock);
        $container = $factory->make(['PsrContainerKey', 'method']);
        $this->assertTrue($container->isSerializable());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSetPsrContainerGlobalBeforeUnserialize(): void
    {
        $psrContainerMock = $this->getMockForAbstractClass(PsrContainer::class);
        $factory = new Container($psrContainerMock);
        $container = $factory->make(['PsrContainerKey', 'method']);
        $serialized = serialize($container);

        Container::setPsrContainerGlobal($psrContainerMock);
        $container = unserialize($serialized);
        $this->assertTrue($container->isSerializable());
    }
}