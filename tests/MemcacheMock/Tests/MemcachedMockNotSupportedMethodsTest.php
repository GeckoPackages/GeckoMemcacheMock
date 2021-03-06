<?php

/*
 * This file is part of the GeckoPackages.
 *
 * (c) GeckoPackages https://github.com/GeckoPackages
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use GeckoPackages\MemcacheMock\MemcachedMock;

/**
 * Test all not supported methods of the MemcachedMock.
 *
 * @author SpacePossum
 *
 * @internal
 */
final class MemcachedMockNotSupportedMethodsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param string $method
     * @param mixed  $arg1
     * @param mixed  $arg2
     * @param mixed  $arg3
     * @param mixed  $arg4
     *
     * @dataProvider provideNotSupportedMethods
     *
     * @expectedException \BadMethodCallException
     */
    public function testNotSupportedMethods($method, $arg1 = null, $arg2 = null, $arg3 = null, $arg4 = null)
    {
        $mock = new MemcachedMock();
        $mock->$method($arg1, $arg2, $arg3, $arg4);
    }

    public function provideNotSupportedMethods()
    {
        return [
            ['addByKey', null, null],
            ['appendByKey'],
            ['cas'],
            ['casByKey'],
            ['decrementByKey'],
            ['deleteByKey'],
            ['deleteMultiByKey', null, []],
            ['fetch'],
            ['fetchAll'],
            ['getByKey'],
            ['getDelayed', []],
            ['getDelayedByKey', null, []],
            ['getMultiByKey', null, []],
            ['getServerByKey'],
            ['incrementByKey'],
            ['prependByKey'],
            ['replaceByKey'],
            ['setByKey'],
            ['setMultiByKey', null, []],
            ['touchByKey'],
        ];
    }
}
