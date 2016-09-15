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
 * MemcachedMock behaviour tests.
 *
 * @author SpacePossum
 *
 * @internal
 */
final class MemcachedMockBehaviourTest extends PHPUnit_Framework_TestCase
{
    public function testGetSetPrefix()
    {
        $prefix = 'abc';
        $mock = new MemcachedMock();
        $this->assertSame('', $mock->getOption(-1002));
        $mock->setPrefix($prefix);
        $this->assertSame($prefix, $mock->getPrefix());
        $this->assertSame($prefix, $mock->getOption(-1002));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp #^Prefix must be a string, got "integer".$#
     */
    public function testSetInvalidPrefixType()
    {
        $mock = new MemcachedMock();
        $mock->setPrefix(123);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp #^Max. length of prefix is 128, got "132".$#
     */
    public function testSetInvalidPrefix()
    {
        $mock = new MemcachedMock();
        $mock->setPrefix('abcabcabcabcabcabcabcabcabcabcabcabcabcabcabcabcaabcabcabcabcabcabcabcabcabcabcabcabcabcabcabcabcaabcabcababcabcabcabcabcabcabcassss');
    }

    /**
     * @expectedException GeckoPackages\MemcacheMock\MemcachedMockAssertException
     * @expectedExceptionMessageRegExp /^assertConnected failed is connected.$/
     */
    public function testAssertFailToException()
    {
        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(true);
        $mock->get('a');
    }

    public function testAdd()
    {
        $testKey = 'abc456';
        $testValue = [10];

        $mock = new MemcachedMock();
        $this->assertNull($mock->getLogger());
        $this->assertFalse($mock->add($testKey, $testValue));

        $mock = $this->getMemcachedMock();
        $mock->setThrowExceptionsOnFailure(false);

        $this->assertFalse($mock->add(null, $testValue));
        $this->assertFalse($mock->add($testKey, xml_parser_create('')));
        $this->assertFalse($mock->add($testKey, $testValue, 'invalid'));

        $mock->setThrowExceptionsOnFailure(true);
        $this->assertTrue($mock->add($testKey, $testValue));

        $testExpiration = time() + 10;
        $this->assertTrue($mock->add($testKey.'1', $testValue, $testExpiration));
        $this->assertSame($testExpiration, $mock->getExpiry($testKey.'1'));

        $mock->setThrowExceptionsOnFailure(false);
        $this->assertFalse($mock->add($testKey, $testValue));

        $mock->quit();

        $logger = $mock->getLogger();
        $this->assertInstanceOf('GeckoPackages\MemcacheMock\MemcachedLogger', $logger);

        $testLogger = $logger->getLogger();
        $this->assertInstanceOf('TestLogger', $testLogger);

        /* @var TestLogger $testLogger */
        $debugLog = $testLogger->getDebugLog();
        $this->assertInternalType('array', $debugLog);
        $this->assertCount(7, $debugLog);
    }

    public function testAppend()
    {
        $testKey = 'abc000';
        $testValue = 'start';
        $testAppendValue = '_end';

        $mock = new MemcachedMock();
        $this->assertFalse($mock->append($testKey, $testValue));
        $mock->addServer('127.0.0.1', 11211);

        $this->assertFalse($mock->append(null, $testValue));
        $this->assertFalse($mock->append($testKey, '1'));

        $mock->set($testKey, $testValue);

        $this->assertTrue($mock->append($testKey, $testAppendValue));
        $this->assertSame($testValue.$testAppendValue, $mock->get($testKey));
        $this->assertFalse($mock->append($testKey, xml_parser_create('')));

        $mock->set($testKey, [10]);
        $this->assertFalse($mock->append($testKey, $testAppendValue));
    }

    public function testDelayedDelete()
    {
        $mock = new MemcachedMock();
        $this->assertFalse($mock->delete('123'));

        $testKey = 'delete789';
        $testValue = 55;
        $mock = $this->getMemcachedMock();
        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('assertHasNotInDeleteQueue');
        $method->setAccessible(true);

        $mock->setThrowExceptionsOnFailure(false);
        $this->assertFalse($mock->delete(null)); // invalid key
        $this->assertFalse($mock->delete($testKey)); // not in cache
        $this->assertTrue($mock->set($testKey, $testValue));
        $this->assertFalse($mock->delete($testKey, false)); // invalid time
        $this->assertTrue($mock->delete($testKey, 1)); // set delay delete queue EOL to 1 second
        $this->assertTrue($mock->delete($testKey, 50)); // try to set to 50, is OK but does not effect EOL of 1 second
        $this->assertFalse($mock->add($testKey, $testValue)); // not allowed while key is in delete queue
        $this->assertFalse($mock->replace($testKey, $testValue)); // not allowed while key is in delete queue
        $this->assertTrue($mock->set($testKey, $testValue)); // allowed while in key is in delete queue
        $this->assertFalse($mock->get($testKey)); // key is in delete queue
        sleep(2);
        $this->assertTrue($mock->add($testKey, $testValue));
    }

    public function testDelayedFlush()
    {
        $mock = new MemcachedMock();
        $this->assertFalse($mock->flush());
        $this->assertFalse($mock->flush(10));

        $testKey = 'flush123';
        $testValue = 4455;
        $mock = $this->getMemcachedMock();
        $mock->setThrowExceptionsOnFailure(false);
        $this->assertFalse($mock->flush('_invalid_'));

        $mock->setThrowExceptionsOnFailure(true);
        $mock->set($testKey, $testValue);
        $this->assertSame($testValue, $mock->get($testKey));

        $this->assertTrue($mock->flush(1));
        $this->assertSame($testValue, $mock->get($testKey));

        sleep(1);
        $this->assertFalse($mock->get($testKey));
    }

    /**
     * Test 'decrement' method.
     */
    public function testDecrement()
    {
        $mock = new MemcachedMock();
        $this->assertFalse($mock->decrement('a'));

        $testKey = 'abc456';
        $testValue = 10;

        $mock = $this->getMemcachedMock();
        $mock->setThrowExceptionsOnFailure(false);

        $this->assertFalse($mock->decrement(false));
        $this->assertFalse($mock->decrement('a', 1, 1, 'invalid'));
        $this->assertFalse($mock->decrement('a', 1, 'invalid'));
        $this->assertSame(2, $mock->decrement('a', 1, 2));

        $mock->setThrowExceptionsOnFailure(true);

        $this->assertTrue($mock->set($testKey, $testValue));
        $this->assertSame($testValue - 2, $mock->decrement($testKey, 2));
        $this->assertSame($testValue - 2, $mock->get($testKey));

        $testKey = 'abc789';
        $testValue = 55;
        $expiry = 20;
        $expiryReal = time() + $expiry;

        $this->assertSame($testValue, $mock->decrement($testKey, 7, $testValue, $expiry));
        $this->assertSame($testValue, $mock->get($testKey));
        $this->assertSame($expiryReal, $mock->getExpiry($testKey));

        $this->assertTrue($mock->set($testKey, 1));
        $this->assertSame(0, $mock->decrement($testKey, 2));
        $this->assertSame(0, $mock->get($testKey));

        $mock->setThrowExceptionsOnFailure(false);
        $this->assertSame(0, $mock->get($testKey));
        $this->assertFalse($mock->decrement($testKey, 'invalid', $testValue, $expiry));

        $mock->set($testKey, 'test');
        $this->assertFalse($mock->decrement($testKey, 1));
    }

    /**
     * Test 'flush' method.
     */
    public function testFlush()
    {
        $mock = $this->getMemcachedMock();

        $keys = $mock->getAllKeys();
        $this->assertCount(0, $keys);

        for ($i = 0; $i < 10; ++$i) {
            $mock->set($i.'a', $i.'b');
        }

        $keys = $mock->getAllKeys();
        $this->assertCount(10, $keys);

        $this->assertTrue($mock->flush());

        $keys = $mock->getAllKeys();
        $this->assertCount(0, $keys);

        $mock->setThrowExceptionsOnFailure(false);

        $this->assertFalse($mock->flush('abc'));

        $mock->quit();

        $this->assertFalse($mock->flush());
    }

    public function testGetReadThrough()
    {
        $testKey = 'testKey';
        $callBack = function ($memc, $key, &$value) {
            $value = 'callBackTestValue1';

            return true;
        };

        $mock = $this->getMemcachedMock();
        $this->assertSame('callBackTestValue1', $mock->get($testKey, $callBack));
        $this->assertSame('callBackTestValue1', $mock->get($testKey));

        $mock->setThrowExceptionsOnFailure(false);

        $testKey = 'testKey2';
        $callBack = function ($memc, $key, &$value) {
            return 'invalid';
        };

        $this->assertFalse($mock->get($testKey, $callBack));

        $testKey = 'testKey2';
        $callBack = function ($memc, $key, &$value) {
            $value = xml_parser_create('');

            return true;
        };

        $this->assertFalse($mock->get($testKey, $callBack));
    }

    /**
     * Test the 'get', 'getAllKeys' and 'set' methods.
     */
    public function testGetSet()
    {
        $mock = new MemcachedMock();
        $this->assertFalse($mock->getExpiry('not_in_cache'));
        $this->assertFalse($mock->getAllKeys());
        $this->assertFalse($mock->get('a'));
        $this->assertFalse($mock->set('a', 'b'));
        $this->assertFalse($mock->set('a', 'b', 1));
        $this->assertFalse($mock->getMulti(['a', 'b']));
        $this->assertFalse($mock->setMulti(['a' => 1, 'b' => 2]));
        $this->assertFalse($mock->deleteMulti(['a', 'b']));

        $testKey = 'abc123';
        $testValue = 'some value';
        $testKey1 = 'def456';
        $testValue1 = 'some other value';
        $key257 = 'abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdX';

        $mock = $this->getMemcachedMock();
        $mock->setThrowExceptionsOnFailure(false);
        $this->assertFalse($mock->get(null));
        $this->assertFalse($mock->set(null, 'b', 1));
        $this->assertFalse($mock->set('a', 'b', 'test'));
        $this->assertFalse($mock->set('a', xml_parser_create('')));
        $this->assertFalse($mock->getMulti(['a', null]));
        $this->assertFalse($mock->setMulti([$key257 => $testValue1]));
        $this->assertFalse($mock->deleteMulti([$key257]));

        $mock->setThrowExceptionsOnFailure(true);
        $keys = $mock->getAllKeys();
        $this->assertInternalType('array', $keys);
        $this->assertCount(0, $keys);

        $this->assertTrue($mock->set($testKey, $testValue));
        $this->assertSame($testValue, $mock->get($testKey));
        $this->assertTrue($mock->set($testKey1, $testValue1, time()));
        $this->assertSame($testValue1, $mock->get($testKey1));

        $keys = $mock->getAllKeys();
        $multi = $mock->getMulti([$testKey1, $testKey]);

        $this->assertInternalType('array', $keys);
        $this->assertCount(2, $keys);
        $this->assertTrue(in_array($testKey, $keys, true));
        $this->assertTrue(in_array($testKey1, $keys, true));

        $this->assertInternalType('array', $multi);
        /* @var array $multi */
        $this->assertCount(2, $multi);
        $this->assertArrayHasKey($testKey, $multi);
        $this->assertSame($testValue, $multi[$testKey]);
        $this->assertArrayHasKey($testKey1, $multi);
        $this->assertSame($testValue1, $multi[$testKey1]);

        $this->assertTrue($mock->delete($testKey));

        $keys = $mock->getAllKeys();
        $this->assertCount(1, $keys);

        $this->assertFalse($mock->get($testKey));
        $this->assertSame($testValue1, $mock->get($testKey1));

        sleep(1);
        $this->assertFalse($mock->get($testKey1));

        $keys = $mock->getAllKeys();
        $this->assertCount(0, $keys);

        $expiration = time() + 10;
        $this->assertTrue($mock->setMulti([$testKey => $testValue, $testKey1 => $testValue1], $expiration));
        $multi = $mock->getMulti([$testKey1, $testKey]);
        $this->assertSame($testValue, $multi[$testKey]);
        $this->assertSame($expiration, $mock->getExpiry($testKey));
        $this->assertSame($testValue1, $multi[$testKey1]);
        $this->assertSame($expiration, $mock->getExpiry($testKey1));

        $this->assertTrue($mock->deleteMulti([$testKey1, $testKey]));
        $this->assertFalse($mock->get($testKey1));
        $this->assertFalse($mock->get($testKey));

        $this->assertTrue($mock->deleteMulti([]));
        $this->assertTrue($mock->setMulti([]));

        $multi = $mock->getMulti(['f', 'e', 'd']);
        $this->assertInternalType('array', $multi);
        $this->assertCount(0, $multi);

        $expiration = 3600;
        $this->assertTrue($mock->set($testKey1, $testValue1, $expiration));
        $this->assertSame(time() + $expiration, $mock->getExpiry($testKey1));

        $this->assertTrue($mock->set('a1', 'b'));
        $this->assertSame(0, $mock->getExpiry('a1'));
        $this->assertTrue($mock->set('a1', 'b', 100));
        $this->assertSame(time() + 100, $mock->getExpiry('a1'));

        $this->assertTrue($mock->add('a2', 'b'));
        $this->assertSame(0, $mock->getExpiry('a2'));

        $this->assertSame(1, $mock->decrement('a3', 1, 1));
        $this->assertSame(0, $mock->getExpiry('a3'));
        $this->assertSame(1, $mock->get('a3'));
    }

    /**
     * Test 'increment' method.
     */
    public function testIncrement()
    {
        $testKey = 'abc123';
        $testValue = 1;

        $mock = new MemcachedMock();
        $this->assertFalse($mock->increment('a'));

        $mock = $this->getMemcachedMock();
        $mock->setThrowExceptionsOnFailure(false);
        $this->assertFalse($mock->increment(null));
        $this->assertFalse($mock->increment('string', 1, 1, 'abc'));

        $mock->setThrowExceptionsOnFailure(true);

        $this->assertTrue($mock->set($testKey, $testValue));
        $this->assertSame($testValue + 1, $mock->increment($testKey, 1));
        $this->assertSame($testValue + 1, $mock->get($testKey));

        $testKey = 'abc124';
        $testValue = 44;
        $expiry = 8;
        $expiryReal = time() + $expiry;

        $this->assertSame($testValue, $mock->increment($testKey, 5, $testValue, $expiry));
        $this->assertSame($testValue, $mock->get($testKey));
        $this->assertSame($expiryReal, $mock->getExpiry($testKey));

        $mock->setThrowExceptionsOnFailure(false);
        $this->assertFalse($mock->increment($testKey, 'invalid', $testValue, $expiry));

        $mock->set($testKey, 'test');
        $this->assertFalse($mock->increment($testKey, 1));
    }

    public function testNormalizeDelayToAbsoluteTime()
    {
        $mock = new MemcachedMock();
        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('normalizeDelayToAbsoluteTime');
        $method->setAccessible(true);
        $this->assertSame(time(), $method->invokeArgs($mock, [null]));
        $this->assertSame(time(), $method->invokeArgs($mock, [0]));
        $this->assertSame(time() + 100, $method->invokeArgs($mock, [100]));
        $this->assertSame(time() - 50, $method->invokeArgs($mock, [-50]));
    }

    public function testNormalizeExpirationTime()
    {
        $mock = new MemcachedMock();
        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('normalizeExpirationTime');
        $method->setAccessible(true);
        $this->assertSame(0, $method->invokeArgs($mock, [null]));
        $this->assertSame(0, $method->invokeArgs($mock, [0]));
        $this->assertSame(time() + 100, $method->invokeArgs($mock, [100]));
        $this->assertSame(time() - 50, $method->invokeArgs($mock, [-50]));
        $this->assertSame(time(), $method->invokeArgs($mock, [time()]));
        $this->assertSame(time() + 90, $method->invokeArgs($mock, [time() + 90]));
        $this->assertSame(time() - 85, $method->invokeArgs($mock, [time() - 85]));
    }

    /**
     * Test 'getOption' and 'setOption' method.
     */
    public function testOptionMethods()
    {
        $mock = new MemcachedMock();
        $mock->setLogger(new MemcachedLogger(new TestLogger()));
        $this->assertSame('', $mock->getOption(-1002));

        $this->assertFalse($mock->getOption(null));
        $this->assertFalse($mock->getOption(32));
        $this->assertFalse($mock->getOption(123));

        $this->assertFalse($mock->setOption(null, null));
        $this->assertFalse($mock->setOption(19, null));
        $this->assertFalse($mock->setOption(20, ''));
        $this->assertFalse($mock->setOption(32, xml_parser_create('')));
        $this->assertFalse($mock->setOption(-1002, null));
        $this->assertFalse($mock->setOptions([-1002 => null]));

        $this->assertTrue($mock->setOption(19, 1));
        $this->assertTrue($mock->setOption(20, 20));

        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(true);

        $this->assertTrue($mock->setOption(-1002, 'some_prefix'));
        $this->assertSame('some_prefix', $mock->getOption(-1002));

        $options = [0 => 'a1', 1 => 'a2', 2 => 'a3', 5 => 'a4', 6 => 'a5', 8 => 'a6'];

        $this->assertTrue($mock->setOptions($options));

        foreach ($options as $option => $value) {
            $this->assertSame($value, $mock->getOption($option));
        }

        $this->assertTrue($mock->setOptions([]));
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessageRegExp /^assertOption failed option is known, got "667".$/
     */
    public function testOptionException()
    {
        $mock = $this->getMemcachedMock();
        $mock->setOptions([667 => 123]);
    }

    /**
     * @param mixed  $option
     * @param mixed  $value
     * @param string $message
     *
     * @dataProvider provideInvalidOptionValues
     */
    public function testSetInvalidOptionValue($option, $value, $message)
    {
        $this->setExpectedException(
            '\GeckoPackages\MemcacheMock\MemcachedMockAssertException',
            $message
        );

        $mock = $this->getMemcachedMock();
        $mock->setOption($option, $value);
    }

    public function provideInvalidOptionValues()
    {
        return [
            [19, 'a', 'assertIntValue failed value is an integer, got "string". Invalid value for option "19".'],
            [20, null, 'assertIntValue failed value is an integer, got "NULL". Invalid value for option "20".'],
        ];
    }

    public function testPrepend()
    {
        $testKey = 'abc000';
        $testValue = 'start';
        $testAppendValue = '_end';

        $mock = new MemcachedMock();
        $this->assertFalse($mock->prepend($testKey, $testValue));
        $mock->addServer('127.0.0.1', 11211);

        $this->assertFalse($mock->prepend(null, $testValue));
        $this->assertFalse($mock->prepend($testKey, '1'));

        $mock->set($testKey, $testValue);

        $this->assertTrue($mock->prepend($testKey, $testAppendValue));
        $this->assertSame($testAppendValue.$testValue, $mock->get($testKey));
        $this->assertFalse($mock->prepend($testKey, xml_parser_create('')));

        $mock->set($testKey, [10]);
        $this->assertFalse($mock->prepend($testKey, $testAppendValue));
    }

    public function testReplace()
    {
        $testKey = 'abc000';
        $testValue = 'start';
        $testReplaceValue = '_end';

        $mock = new MemcachedMock();
        $this->assertFalse($mock->replace($testKey, $testValue));
        $mock->addServer('127.0.0.1', 11211);

        $this->assertFalse($mock->replace(null, $testValue));
        $this->assertFalse($mock->replace($testKey, '1'));

        $mock->set($testKey, $testValue);

        $this->assertTrue($mock->replace($testKey, $testReplaceValue));
        $this->assertSame($testReplaceValue, $mock->get($testKey));
        $this->assertFalse($mock->replace($testKey, xml_parser_create('')));
        $this->assertFalse($mock->replace($testKey, $testReplaceValue, 'invalid'));

        $this->assertTrue($mock->replace($testKey, $testReplaceValue, 10));
        $this->assertSame(time() + 10, $mock->getExpiry($testKey));
    }

    /**
     * Test 'addServer', 'addServers', 'getServerList', 'getStats', 'getVersion', 'isPersistent' and 'isPristine' methods.
     */
    public function testServerMethods()
    {
        $mock = new MemcachedMock();
        $mock->setLogger(new MemcachedLogger(new TestLogger()));

        $servers = $mock->getServerList();
        $this->assertInternalType('array', $servers);
        $this->assertCount(0, $servers);

        $this->assertFalse($mock->getStats());
        $this->assertFalse($mock->getVersion());
        $this->assertFalse($mock->isPersistent());
        $this->assertFalse($mock->isPristine());
        $this->assertFalse($mock->addServers([1]));
        $this->assertFalse($mock->addServers([['a', 'b']]));
        $this->assertFalse($mock->addServers([['127.0.0.1', 123, 'a']]));
        $this->assertFalse($mock->addServers([['127.0.0.1', 123], ['127.0.0.1', 'a']]));

        $mock->setThrowExceptionsOnFailure(true);

        $this->assertTrue($mock->resetServerList());

        $servers = $mock->getServerList();
        $this->assertInternalType('array', $servers);
        $this->assertCount(0, $servers);

        $this->assertTrue($mock->addServer('127.0.0.1', 11211));
        $servers = $mock->getServerList();
        $this->assertCount(1, $servers);
        $this->assertSame('127.0.0.1', $servers[0]['host']);
        $this->assertSame(11211, $servers[0]['port']);

        $stats = $mock->getStats();
        $this->assertInternalType('array', $stats);
        /* @var array $stats */
        $this->assertCount(1, $stats);
        $this->assertArrayHasKey('127.0.0.1:11211', $stats);
        $this->assertArrayHasKey('version', $stats['127.0.0.1:11211']);

        $this->assertTrue(
            $mock->addServers(
                [
                    ['127.0.0.2', 11212],
                    ['127.0.0.3', 11213, 3],
                ]
            )
        );

        $stats = $mock->getStats();
        $this->assertCount(3, $stats);

        $this->assertArrayHasKey('127.0.0.1:11211', $stats);
        $this->assertArrayHasKey('version', $stats['127.0.0.1:11211']);
        $this->assertInternalType('string', $stats['127.0.0.1:11211']['version']);

        $this->assertArrayHasKey('127.0.0.2:11212', $stats);
        $this->assertArrayHasKey('version', $stats['127.0.0.2:11212']);
        $this->assertInternalType('string', $stats['127.0.0.2:11212']['version']);

        $this->assertArrayHasKey('127.0.0.3:11213', $stats);
        $this->assertArrayHasKey('version', $stats['127.0.0.3:11213']);
        $this->assertInternalType('string', $stats['127.0.0.3:11213']['version']);

        $serverList = $mock->getServerList();
        $this->assertInternalType('array', $serverList);
        $this->assertCount(3, $serverList);

        $this->assertInternalType('array', $serverList[0]);
        $this->assertArrayHasKey('host', $serverList[0]);
        $this->assertSame('127.0.0.1', $serverList[0]['host']);
        $this->assertArrayHasKey('port', $serverList[0]);
        $this->assertSame(11211, $serverList[0]['port']);

        $this->assertInternalType('array', $serverList[1]);
        $this->assertArrayHasKey('host', $serverList[1]);
        $this->assertSame('127.0.0.2', $serverList[1]['host']);
        $this->assertArrayHasKey('port', $serverList[1]);
        $this->assertSame(11212, $serverList[1]['port']);

        $this->assertInternalType('array', $serverList[2]);
        $this->assertArrayHasKey('host', $serverList[2]);
        $this->assertSame('127.0.0.3', $serverList[2]['host']);
        $this->assertArrayHasKey('port', $serverList[2]);
        $this->assertSame(11213, $serverList[2]['port']);

        $version = $mock->getVersion();
        $this->assertInternalType('array', $version);

        /* @var array $version */
        $this->assertArrayHasKey('127.0.0.1:11211', $version);
        $this->assertSame('x.x.mock', $version['127.0.0.1:11211']);

        $this->assertArrayHasKey('127.0.0.2:11212', $version);
        $this->assertSame('x.x.mock', $version['127.0.0.2:11212']);

        $this->assertArrayHasKey('127.0.0.3:11213', $version);
        $this->assertSame('x.x.mock', $version['127.0.0.3:11213']);

        $this->assertTrue($mock->quit());

        $serverList = $mock->getServerList();
        $this->assertInternalType('array', $serverList);
        $this->assertCount(0, $serverList);

        $this->assertTrue($mock->addServer('127.0.0.1', 11211));
        $servers = $mock->getServerList();
        $this->assertCount(1, $servers);

        $this->assertTrue($mock->resetServerList());
        $this->assertInternalType('array', $serverList);
        $servers = $mock->getServerList();
        $this->assertCount(0, $servers);

        $mock->setThrowExceptionsOnFailure(false);
        $this->assertFalse($mock->getStats());
        $this->assertFalse($mock->addServer(null, null));

        $this->assertTrue($mock->addServers([]));
    }

    /**
     * Test 'touch' method.
     */
    public function testTouch()
    {
        $mock = new MemcachedMock();
        $this->assertFalse($mock->touch('a', 765));
        $this->assertFalse($mock->touch(null, 765));

        $testKey = 'abc123';
        $testValue = 'some value';

        $mock = $this->getMemcachedMock();
        $expiryStart = time() + 10;

        $this->assertTrue($mock->set($testKey, $testValue, $expiryStart));
        $this->assertSame($expiryStart, $mock->getExpiry($testKey));
        $this->assertSame($testValue, $mock->get($testKey));

        $expiry1 = time() + 10;
        $this->assertTrue($mock->touch($testKey, $expiry1));
        $this->assertSame($expiry1, $mock->getExpiry($testKey));

        $expiry2 = 5;
        $this->assertTrue($mock->touch($testKey, $expiry2));
        $this->assertSame(time() + $expiry2, $mock->getExpiry($testKey));

        $mock->setThrowExceptionsOnFailure(false);
        $this->assertFalse($mock->touch('does_not_exists', 765));
        $this->assertFalse($mock->touch($testKey, 'invalid_expiry'));
        $this->assertFalse($mock->touch(null, 1));
    }

    /**
     * @param int         $code
     * @param string      $resultMessage
     * @param string|null $message
     *
     * @dataProvider provideErrorMap
     */
    public function testResultErrorMessages($code, $resultMessage, $message = null)
    {
        $mock = new MemcachedMock();
        $mock->setLogger(new MemcachedLogger(new TestLogger()));
        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('setResultFailed');
        $method->setAccessible(true);
        $method->invokeArgs($mock, [$code, $message]);
        $this->assertSame($code, $mock->getResultCode());
        $this->assertSame($resultMessage, $mock->getResultMessage());
    }

    public function provideErrorMap()
    {
        return [
            [2, 'getaddrinfo() or getnameinfo() HOSTNAME LOOKUP FAILURE'],
            [5, 'WRITE FAILURE'],
            [12, 'CONNECTION DATA EXISTS'],
            [14, 'NOT STORED'],
            [16, 'NOT FOUND'],
            [667, 'custom message', 'custom message'],
        ];
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessageRegExp /^Unknown result failed code "555", supply an error message.$/
     */
    public function testMissingResultErrorMessage()
    {
        $mock = new MemcachedMock();
        $mockReflection = new \ReflectionClass($mock);
        $method = $mockReflection->getMethod('setResultFailed');
        $method->setAccessible(true);
        $method->invokeArgs($mock, [555]);
    }

    public function testPrefixUsage()
    {
        $testPrefix = '_prefix_test_';
        $testKey = 'prefixKey';
        $testValue = 12345;

        $mock = $this->getMemcachedMock();
        $mock->setOption(-1002, $testPrefix);

        $testExpiration = time() + 10;
        $this->assertTrue($mock->set($testKey, $testValue, $testExpiration));
        $this->assertSame($testValue, $mock->get($testKey));
        $this->assertSame($testExpiration, $mock->getExpiry($testKey));

        $this->assertTrue($mock->add($testKey.'1', 4));
        $this->assertSame(7, $mock->increment($testKey.'1', 3));
        $this->assertSame(7, $mock->get($testKey.'1'));
        $this->assertSame(5, $mock->decrement($testKey.'1', 2));
        $this->assertSame(5, $mock->get($testKey.'1'));
        $this->assertTrue($mock->append($testKey.'1', 'abc'));
        $this->assertSame('5abc', $mock->get($testKey.'1'));
        $this->assertTrue($mock->replace($testKey.'1', 'efg'));
        $this->assertSame('efg', $mock->get($testKey.'1'));
        $this->assertTrue($mock->prepend($testKey.'1', 'abc1'));
        $this->assertSame('abc1efg', $mock->get($testKey.'1'));

        $keys = $mock->getAllKeys();
        $this->assertInternalType('array', $keys);
        $this->assertCount(2, $keys);
        $this->assertTrue(in_array($testPrefix.$testKey, $keys, true));
        $this->assertTrue(in_array($testPrefix.$testKey.'1', $keys, true));

        $multi = $mock->getMulti([$testKey, $testKey.'1']);
        $this->assertInternalType('array', $multi);
        $this->assertCount(2, $multi);
        $this->assertArrayHasKey($testKey, $multi);
        $this->assertArrayHasKey($testKey.'1', $multi);

        $this->assertTrue($mock->delete($testKey.'1'));
        $this->assertFalse($mock->get($testKey.'1'));

        $keys = $mock->getAllKeys();
        $this->assertInternalType('array', $keys);
        $this->assertCount(1, $keys);

        $this->assertTrue($mock->delete($testKey));
        $this->assertFalse($mock->get($testKey));

        $keys = $mock->getAllKeys();
        $this->assertInternalType('array', $keys);
        $this->assertCount(0, $keys);

        $this->assertTrue($mock->set($testKey, $testValue, $testExpiration));
        $this->assertSame($testValue, $mock->get($testKey));

        $mock->setOption(-1002, '');

        $this->assertTrue($mock->set($testKey, $testValue.'abc', $testExpiration));
        $this->assertSame($testValue.'abc', $mock->get($testKey));

        $keys = $mock->getAllKeys();
        $this->assertInternalType('array', $keys);
        $this->assertCount(2, $keys);
        $this->assertTrue(in_array($testKey, $keys, true));
        $this->assertTrue(in_array($testPrefix.$testKey, $keys, true));
    }

    private function getMemcachedMock()
    {
        $mock = new MemcachedMock();
        $mock->setThrowExceptionsOnFailure(true);
        $mock->addServer('127.0.0.1', 11211);
        $mock->setLogger(new MemcachedLogger(new TestLogger()));

        return $mock;
    }
}
