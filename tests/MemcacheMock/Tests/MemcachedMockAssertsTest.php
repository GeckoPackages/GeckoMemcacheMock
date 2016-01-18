<?php

/*
 * This file is part of the GeckoPackages.
 *
 * (c) GeckoPackages https://github.com/GeckoPackages
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use GeckoPackages\MemcacheMock\MemcachedLogger;
use GeckoPackages\MemcacheMock\MemcachedMock;

/**
 * Lower level test for all asserts of the MemcachedMock.
 *
 * @author SpacePossum
 *
 * @internal
 */
final class MemcachedMockAssertsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param string      $methodName
     * @param array       $args
     * @param bool        $expected
     * @param string|null $message
     *
     * @dataProvider provideAssertTestCases
     */
    public function testAsserts($methodName, array $args, $expected, $message = null)
    {
        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(false);
        $logger = new TestLogger();
        $mock->setLogger(new MemcachedLogger($logger));

        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod($methodName);
        $this->assertInstanceOf('\ReflectionMethod', $method, sprintf('Failed to find method "%s".', $methodName));
        $this->assertSame('GeckoPackages\MemcacheMock\MemcachedMock', $method->getDeclaringClass()->getName());
        $this->assertTrue($method->isPrivate(), sprintf('Expected method "%s" to be private.', $methodName));
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invokeArgs($mock, $args), sprintf('Expected return value mismatch for "%s".', $methodName));
        if (null !== $message) {
            $failures = $logger->getErrorLog();
            $this->assertInternalType('array', $failures);
            $this->assertCount(1, $failures, 'Expected 1 assert failure.');
            $this->assertSame($message, $failures[0][0], 'Assert message not as expected.');
        }
    }

    /**
     * Test for each assert on the MemcachedMock there is at least one test case.
     */
    public function testCompletenessOfAssertTestCases()
    {
        $cases = $this->provideAssertTestCases();
        $mock = new MemcachedMock();
        $mockReflection = new \ReflectionClass($mock);
        $allMethods = $mockReflection->getMethods();
        $missing = array();
        foreach ($allMethods as $method) {
            $methodName = $method->getName();
            if (0 !== strpos($methodName, 'assert')) {
                continue;
            }

            foreach ($cases as $case) {
                if (in_array($methodName, $case, true)) {
                    continue 2;
                }
            }

            $missing[] = $methodName;
        }

        $this->assertEmpty($missing, sprintf("Missing test for the asserts:\n- %s", implode("\n- ", $missing)));
    }

    public function provideAssertTestCases()
    {
        $key256 = 'abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcd';
        $key257 = $key256.'e';

        return array(
            array('assertCallBackResult', array(true), true),
            array('assertCallBackResult', array(false), true),
            array('assertCallBackResult', array('test'), false, 'assertCallBackResult failed callback returned is bool, got "string".'),
            array('assertCallBackResult', array(null, 'Custom message.'), false, 'assertCallBackResult failed callback returned is bool, got "NULL". Custom message.'),
            array('assertConnected', array(false), false, 'assertConnected failed is connected.'),
            array('assertDelay', array(0), true),
            array('assertDelay', array(1), true),
            array('assertDelay', array(time()), true),
            array('assertDelay', array(-1), false, 'assertDelay failed delay is greater than or equals 0, got "-1".'),
            array('assertDelay', array(null), false, 'assertDelay failed delay is an integer, got "NULL".'),
            array('assertExpiration', array(1), true),
            array('assertExpiration', array(null), true),
            array('assertExpiration', array(new \stdClass()), false, 'assertExpiration failed expiration is an integer, got "stdClass".'),
            array('assertIntValue', array(-1), true),
            array('assertIntValue', array(0), true),
            array('assertIntValue', array(1), true),
            array('assertIntValue', array(100), true),
            array('assertIntValue', array(null), false, 'assertIntValue failed value is an integer, got "NULL".'),
            array('assertHasInCache', array('abc'), false, 'assertHasInCache failed key "abc" is in cache.'),
            array('assertHasNotInCache', array('abc'), true),
            array('assertHasNotInDeleteQueue', array('test'), true),
            array('assertKey', array('key'), true),
            array('assertKey', array(null), false, 'assertKey failed key is a string, got "NULL".'),
            array('assertKey', array($key256), true),
            array('assertKey', array($key257), false, sprintf('assertKey failed key (+ prefix) is less than 256 characters, got "%s" (257).', $key257)),
            array('assertOffset', array(-1), true),
            array('assertOffset', array(0), true),
            array('assertOffset', array(1), true),
            array('assertOffset', array(100), true),
            array('assertOffset', array(null), false, 'assertOffset failed offset is an integer, got "NULL".'),
            array('assertOption', array(0), true),
            array('assertOption', array(-1002), true),
            array('assertOption', array(null), false, 'assertOption failed option is an integer, got "NULL".'),
            array('assertOption', array(''), false, 'assertOption failed option is an integer, got "string".'),
            array('assertOption', array(true), false, 'assertOption failed option is an integer, got "boolean".'),
            array('assertOption', array(new \stdClass()), false, 'assertOption failed option is an integer, got "stdClass".'),
            array('assertOption', array(777), false, 'assertOption failed option is known, got "777".'),
            array('assertOptionValue', array(1), true),
            array('assertOptionValue', array(true), true),
            array('assertOptionValue', array(false), true),
            array('assertOptionValue', array(new \stdClass()), true),
            array('assertOptionValue', array('test'), true),
            array('assertOptionValue', array(array()), true),
            array('assertOptionValue', array(array('1')), true),
            array('assertOptionValue', array(xml_parser_create('')), false, 'assertOptionValue failed value is not a resource, got "xml".'),
            array('assertPrefix', array('key'), true),
            array('assertPrefix', array(null), false, 'assertPrefix failed prefix is a string, got "NULL".'),
            array('assertPrefix', array($key256), false, sprintf('assertPrefix failed prefix is less than 128 characters, got "%s" (256).', $key256)),
            array('assertScalarValue', array(1), true),
            array('assertScalarValue', array(''), true),
            array('assertScalarValue', array(null), false, 'assertScalarValue failed value is a scalar, got "NULL".'),
            array('assertScalarValue', array(new \stdClass()), false, 'assertScalarValue failed value is a scalar, got "stdClass".'),
            array('assertScalarValue', array(xml_parser_create('')), false, 'assertScalarValue failed value is a scalar, got "resource".'),
            array('assertScalarValue', array(array()), false, 'assertScalarValue failed value is a scalar, got "array".'),
            array('assertServer', array('127.0.0.1', 11211), true),
            array('assertServer', array(null, 11211), false, 'assertServer failed host is a string, got "NULL".'),
            array('assertServer', array('127.0.0.1', new \stdClass()), false, 'assertServer failed port is an integer, got "stdClass".'),
            array('assertServer', array('127.0.0.1', -1), false, 'assertServer failed port is greater than 0, got "-1".'),
            array('assertServer', array('127.0.0.1', 11211, 'abc'), false, 'assertServer failed weight is an integer, got "string".'),
            array('assertServer', array('127.0.0.1', 11211, -1), false, 'assertServer failed weight greater than or equals 0, got "-1".'),
            array('assertValue', array(1), true),
            array('assertValue', array(true), true),
            array('assertValue', array(false), true),
            array('assertValue', array(new \stdClass()), true),
            array('assertValue', array('test'), true),
            array('assertValue', array(array()), true),
            array('assertValue', array(array('1')), true),
            array('assertValue', array(xml_parser_create('')), false, 'assertValue failed value is not a resource, got "xml".'),
        );
    }

    /**
     * @expectedException GeckoPackages\MemcacheMock\MemcachedMockAssertException
     * @expectedExceptionMessage assertHasNotInCache failed key "a" is not in cache.
     */
    public function testAssertHasNotInCache()
    {
        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(true);
        $mock->addServer('127.0.0.1', 11211);
        $mock->set('a', 'b');

        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('assertHasNotInCache');
        $method->setAccessible(true);
        $method->invokeArgs($mock, array('a'));
    }

    public function testAssertHasInCache()
    {
        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(true);
        $mock->addServer('127.0.0.1', 11211);
        $mock->set('a', 'b');

        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('assertHasInCache');
        $method->setAccessible(true);
        $this->assertTrue($method->invokeArgs($mock, array('a')));
    }

    public function testAssertHasNotInDeleteQueue()
    {
        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(true);
        $mock->addServer('127.0.0.1', 11211);
        $mock->set('a', 'b');
        $mock->delete('a', 100);

        $mock->setThrowExceptionsOnFailure(false);
        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('assertHasNotInDeleteQueue');
        $method->setAccessible(true);
        $this->assertFalse($method->invokeArgs($mock, array('a')));
    }

    public function testMultipleAssertFailures()
    {
        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(false);
        $logger = new TestLogger();
        $mock->setLogger(new MemcachedLogger($logger));

        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('assertConnected');
        $method->setAccessible(true);
        $this->assertFalse($method->invokeArgs($mock, array()));

        $method = $mockReflection->getMethod('assertScalarValue');
        $method->setAccessible(true);
        $this->assertFalse($method->invokeArgs($mock, array(array())));

        $mock->setThrowExceptionsOnFailure(true);

        $errorLog = $mock->getLogger()->getLogger()->getErrorLog();
        $this->assertInternalType('array', $errorLog);
        $this->assertCount(2, $errorLog);
    }
}
