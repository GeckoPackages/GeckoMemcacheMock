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
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author SpacePossum
 *
 * @internal
 */
final class MemcachedLoggerTest extends PHPUnit_Framework_TestCase
{
    public function testLogger()
    {
        $stopWatch = new Stopwatch();
        $logger = new MemcachedLogger(null, $stopWatch);
        $logger->startMethod('testLogger');

        $event = $stopWatch->getEvent('memcached');
        $this->assertTrue($event->isStarted());

        $logger->stopMethod();

        $this->assertSame($stopWatch, $logger->getStopwatch());

        $event = $stopWatch->getEvent('memcached');
        $this->assertCount(1, $event->getPeriods());

        if (method_exists($stopWatch, 'getSections')) {
            $sections = $stopWatch->getSections();
            $this->assertCount(1, $sections);
        } else {
            $this->markTestSkipped('Requires symfony/stopwatch 2.6 or higher.');
        }
    }
}
