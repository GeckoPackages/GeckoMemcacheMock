<?php

/*
 * This file is part of the GeckoPackages.
 *
 * (c) GeckoPackages https://github.com/GeckoPackages
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace GeckoPackages\MemcacheMock;

use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @api
 *
 * @author SpacePossum
 */
class MemcachedLogger
{
    private $logger;
    private $stopwatch;

    public function __construct(LoggerInterface $logger = null, Stopwatch $stopwatch = null)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return Stopwatch|null
     */
    public function getStopwatch()
    {
        return $this->stopwatch;
    }

    /**
     * @param string     $method
     * @param array|null $params
     */
    public function startMethod($method, array $params = null)
    {
        if (null !== $this->stopwatch) {
            $this->stopwatch->start('memcached', 'memcached');
        }

        if (null !== $this->logger) {
            $this->log($method, $params);
        }
    }

    public function stopMethod()
    {
        if (null !== $this->stopwatch) {
            $this->stopwatch->stop('memcached');
        }
    }

    /**
     * @param string $message
     * @param array  $params
     */
    private function log($message, array $params = null)
    {
        $this->logger->debug($message, null === $params ? [] : $params);
    }
}
