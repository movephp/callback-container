<?php

declare(strict_types=1);

namespace Movephp\CallbackContainer\Tests;

use PHPUnit\Framework\TestCase;
use Movephp\CallbackContainer\Parameter;

/**
 * Class AutoloadTest
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