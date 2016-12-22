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
use GeckoPackages\MemcacheMock\MemcachedMockAssertException;

/**
 * Lower level test for all asserts of the MemcachedMock.
 *
 * @author SpacePossum
 *
 * @internal
 */
final class MemcachedMockAssertsTest extends \PHPUnit_Framework_TestCase
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
        $missing = [];
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

        return [
            ['assertArrayValue', [true], false, 'assertArrayValue failed value is an array, got "boolean".'],
            ['assertArrayValue', [[]], true],
            ['assertCallBackResult', [true], true],
            ['assertCallBackResult', [false], true],
            ['assertCallBackResult', ['test'], false, 'assertCallBackResult failed callback returned is bool, got "string".'],
            ['assertCallBackResult', [null, 'Custom message.'], false, 'assertCallBackResult failed callback returned is bool, got "NULL". Custom message.'],
            ['assertConnected', [false], false, 'assertConnected failed is connected.'],
            ['assertDelay', [0], true],
            ['assertDelay', [1], true],
            ['assertDelay', [time()], true],
            ['assertDelay', [-1], false, 'assertDelay failed delay is greater than or equals 0, got "-1".'],
            ['assertDelay', [null], false, 'assertDelay failed delay is an integer, got "NULL".'],
            ['assertExpiration', [1], true],
            ['assertExpiration', [null], true],
            ['assertExpiration', [new \stdClass()], false, 'assertExpiration failed expiration is an integer >= 0 or null, got "stdClass".'],
            ['assertExpiration', [-1], false, 'assertExpiration failed expiration is an integer >= 0 or null, got "-1".'],
            ['assertIntValue', [-1], true],
            ['assertIntValue', [0], true],
            ['assertIntValue', [1], true],
            ['assertIntValue', [100], true],
            ['assertIntValue', [null], false, 'assertIntValue failed value is an integer, got "NULL".'],
            ['assertHasInCache', ['abc'], false, 'assertHasInCache failed key "abc" is in cache.'],
            ['assertHasNotInCache', ['abc'], true],
            ['assertHasNotInDeleteQueue', ['test'], true],
            ['assertKey', ['key'], true],
            ['assertKey', [null], false, 'assertKey failed key is a string, got "NULL".'],
            ['assertKey', [$key256], true],
            ['assertKey', [$key257], false, sprintf('assertKey failed key is less than 256 characters, got "%s" (257).', $key257)],
            ['assertOffset', [-1], true],
            ['assertOffset', [0], true],
            ['assertOffset', [1], true],
            ['assertOffset', [100], true],
            ['assertOffset', [null], false, 'assertOffset failed offset is an integer, got "NULL".'],
            ['assertOption', [0], true],
            ['assertOption', [-1002], true],
            ['assertOption', [null], false, 'assertOption failed option is an integer, got "NULL".'],
            ['assertOption', [''], false, 'assertOption failed option is an integer, got "string".'],
            ['assertOption', [true], false, 'assertOption failed option is an integer, got "boolean".'],
            ['assertOption', [new \stdClass()], false, 'assertOption failed option is an integer, got "stdClass".'],
            ['assertOption', [777], false, 'assertOption failed option is known, got "777".'],
            ['assertOptionValue', [1], true],
            ['assertOptionValue', [true], true],
            ['assertOptionValue', [false], true],
            ['assertOptionValue', [new \stdClass()], true],
            ['assertOptionValue', ['test'], true],
            ['assertOptionValue', [[]], true],
            ['assertOptionValue', [['1']], true],
            ['assertOptionValue', [xml_parser_create('')], false, 'assertOptionValue failed value is not a resource, got "xml".'],
            ['assertPrefix', ['key'], true],
            ['assertPrefix', [null], false, 'assertPrefix failed prefix is a string, got "NULL".'],
            ['assertPrefix', [$key256], false, sprintf('assertPrefix failed prefix is less than 128 characters, got "%s" (256).', $key256)],
            ['assertScalarValue', [1], true],
            ['assertScalarValue', [''], true],
            ['assertScalarValue', [null], false, 'assertScalarValue failed value is a scalar, got "NULL".'],
            ['assertScalarValue', [new \stdClass()], false, 'assertScalarValue failed value is a scalar, got "stdClass".'],
            ['assertScalarValue', [xml_parser_create('')], false, 'assertScalarValue failed value is a scalar, got "resource".'],
            ['assertScalarValue', [[]], false, 'assertScalarValue failed value is a scalar, got "array".'],
            ['assertServer', ['127.0.0.1', 11211, 0], true],
            ['assertServer', [null, 11211, 0], false, 'assertServer failed host is a string, got "NULL".'],
            ['assertServer', ['127.0.0.1', new \stdClass(), 0], false, 'assertServer failed port is an integer, got "stdClass".'],
            ['assertServer', ['127.0.0.1', -1, 0], false, 'assertServer failed port is greater than 0, got "-1".'],
            ['assertServer', ['127.0.0.1', 11211, 'abc'], false, 'assertServer failed weight is an integer, got "string".'],
            ['assertServer', ['127.0.0.1', 11211, -1], false, 'assertServer failed weight greater than or equals 0, got "-1".'],
            ['assertValue', [1], true],
            ['assertValue', [true], true],
            ['assertValue', [false], true],
            ['assertValue', [new \stdClass()], true],
            ['assertValue', ['test'], true],
            ['assertValue', [[]], true],
            ['assertValue', [['1']], true],
            ['assertValue', [xml_parser_create('')], false, 'assertValue failed value is not a resource, got "xml".'],
        ];
    }

    /**
     * @requires PHPUnit 5.2
     */
    public function testAssertHasNotInCache()
    {
        $this->expectException('\GeckoPackages\MemcacheMock\MemcachedMockAssertException');
        $this->expectExceptionMessageRegExp('#^assertHasNotInCache failed key "a" is not in cache.$#');

        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(true);
        $mock->addServer('127.0.0.1', 11211);
        $mock->set('a', 'b');

        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('assertHasNotInCache');
        $method->setAccessible(true);
        $method->invokeArgs($mock, ['a']);
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
        $this->assertTrue($method->invokeArgs($mock, ['a']));
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
        $this->assertFalse($method->invokeArgs($mock, ['a']));
    }

    /**
     * @expectedException \GeckoPackages\MemcacheMock\MemcachedMockAssertException
     * @expectedExceptionMessageRegExp /^assertKey failed key with prefix is less than 256 characters, got "_prefix_test_prefix_test_prefix_test_prefix_test_prefix_test_aaabbbcccdddeeefffggghhhhiiijjjjkkklllmmmnnnooopppqqqrrrssstttuuuvvvwwwxxxyyyzzzaaabbbcccdddeeefffggghhhhiiijjjjkkklllmmmnnnooopppqqqrrrssstttuuuvvvwwwxxxyyyzzzaaabbbcccdddeeefffggghhhhiiijjjjkkklllmmmnnnooopppqqqrrrssstttuuuvvvwwwxxxyyyzzz" \(301\).$/
     */
    public function testAssertKeyWithPrefixSet()
    {
        $prefix = '_prefix_test_prefix_test_prefix_test_prefix_test_prefix_test_';
        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(true);
        $mock->setOption(-1002, $prefix);

        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('getPrefix');
        $method->setAccessible(true);
        $this->assertSame($prefix, $method->invokeArgs($mock, []));

        $method = $mockReflection->getMethod('assertKey');
        $method->setAccessible(true);
        $method->invokeArgs($mock, ['aaabbbcccdddeeefffggghhhhiiijjjjkkklllmmmnnnooopppqqqrrrssstttuuuvvvwwwxxxyyyzzzaaabbbcccdddeeefffggghhhhiiijjjjkkklllmmmnnnooopppqqqrrrssstttuuuvvvwwwxxxyyyzzzaaabbbcccdddeeefffggghhhhiiijjjjkkklllmmmnnnooopppqqqrrrssstttuuuvvvwwwxxxyyyzzz']);
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
        $this->assertFalse($method->invokeArgs($mock, []));

        $method = $mockReflection->getMethod('assertScalarValue');
        $method->setAccessible(true);
        $this->assertFalse($method->invokeArgs($mock, [[]]));

        $mock->setThrowExceptionsOnFailure(true);

        $errorLog = $mock->getLogger()->getLogger()->getErrorLog();
        $this->assertInternalType('array', $errorLog);
        $this->assertCount(2, $errorLog);
    }
}
