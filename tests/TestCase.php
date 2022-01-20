<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Tests;

use ReflectionClass;
use ReflectionProperty;

class TestCase extends \PHPUnit\Framework\TestCase
{
    public function getMock(string $class): object
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function getMockSetMethods(string $class, array $methods): object
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    public function getAbstractMock(string $class): object
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function getMethod(object $object, string $method): object
    {
        $ref = new ReflectionClass($object);
        $method = $ref->getMethod($method);
        $method->setAccessible(true);

        return $method;
    }

    public function getPropertyValue(object $object, string $property): mixed
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);

        return $ref->getValue($object);
    }

    public function setPropertyValue(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

    public function isPropertyInitialized(object $object, string $property): bool
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);

        return $ref->isInitialized($object);
    }
}
