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

use PHPUnit\Framework\TestCase;
use Movephp\CallbackContainer\Parameter;

/**
 * Class ParameterTest
 * @package Movephp\CallbackContainer\Tests
 */
class ParameterTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\ReflectionParameter
     */
    private $reflectionMock;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->reflectionMock = $this->getMockBuilder(\ReflectionParameter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getName',
                'isVariadic',
                'hasType',
                'getType',
                'isOptional',
                'isDefaultValueAvailable',
                'getDefaultValue'
            ])
            ->getMock();
    }

    /**
     *
     */
    protected function tearDown(): void
    {
        $this->reflectionMock = null;
    }

    /**
     *
     */
    public function testName(): void
    {
        $this->reflectionMock->expects($this->once())
            ->method('getName')
            ->willReturn('someName');

        $parameter = new Parameter($this->reflectionMock);
        $this->assertEquals(
            'someName',
            $parameter->name()
        );
    }

    /**
     * @return array
     */
    public function boolDataProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false]
        ];
    }

    /**
     * @param bool $value
     * @dataProvider boolDataProvider
     */
    public function testType(bool $value): void
    {
        $this->reflectionMock->expects($this->once())
            ->method('hasType')
            ->willReturn($value);
        $this->reflectionMock->expects($this->any())
            ->method('getType')
            ->willReturn('someType');

        $parameter = new Parameter($this->reflectionMock);
        $this->assertEquals($value, $parameter->hasType());
        $this->assertEquals($value ? 'someType' : '', $parameter->type());
    }

    /**
     * @param bool $value
     * @dataProvider boolDataProvider
     */
    public function testIsVariadic(bool $value): void
    {
        $this->reflectionMock->expects($this->once())
            ->method('isVariadic')
            ->willReturn($value);

        $parameter = new Parameter($this->reflectionMock);
        $this->assertEquals($value, $parameter->isVariadic());
    }

    /**
     * @param bool $value
     * @dataProvider boolDataProvider
     */
    public function testIsOptional(bool $value): void
    {
        $this->reflectionMock->expects($this->once())
            ->method('isOptional')
            ->willReturn($value);
        $this->reflectionMock->expects($this->any())
            ->method('isDefaultValueAvailable')
            ->willReturn(true);

        $parameter = new Parameter($this->reflectionMock);
        $this->assertEquals($value, $parameter->isOptional());
    }

    /**
     * @param bool $value
     * @dataProvider boolDataProvider
     */
    public function testIsOptionalIfDefaultValueUnavailable(bool $value): void
    {
        $this->reflectionMock->expects($this->once())
            ->method('isOptional')
            ->willReturn($value);
        $this->reflectionMock->expects($this->any())
            ->method('isDefaultValueAvailable')
            ->willReturn(false);

        $parameter = new Parameter($this->reflectionMock);
        $this->assertFalse($parameter->isOptional());
    }

    /**
     * @param bool $isOptional
     * @dataProvider boolDataProvider
     */
    public function testGetDefault(bool $isOptional): void
    {
        $this->reflectionMock->expects($this->once())
            ->method('isOptional')
            ->willReturn($isOptional);
        $this->reflectionMock->expects($this->any())
            ->method('isDefaultValueAvailable')
            ->willReturn($isOptional);
        $this->reflectionMock->expects($this->any())
            ->method('getDefaultValue')
            ->willReturn('someValue');

        $parameter = new Parameter($this->reflectionMock);
        $this->assertEquals(
            $isOptional ? 'someValue' : '',
            $parameter->getDefault()
        );
    }
}