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

/**
 * Represent a value stored in the MemcachedMock.
 *
 * @author SpacePossum
 *
 * @internal
 */
class MemcacheObject
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var int
     */
    private $expireTime;

    /**
     * @param string   $key
     * @param mixed    $value
     * @param int|null $expireTime
     */
    public function __construct($key, $value, $expireTime)
    {
        $this->key = $key;
        $this->value = $value;
        $this->expireTime = $expireTime;
    }

    /**
     * @return int|null
     */
    public function getExpireTime()
    {
        return $this->expireTime;
    }

    /**
     * @param int|null $expireTime
     */
    public function setExpireTime($expireTime)
    {
        $this->expireTime = $expireTime;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
