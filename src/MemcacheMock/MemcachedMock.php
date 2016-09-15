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
 * Memcached mock for testing and analysis.
 *
 * Mock to mimic the Memcached package (http://php.net/manual/en/book.memcached.php) as much as possible.
 * Its use is for test purposes of software that support interaction with Memcache (http://memcached.org/)
 * using the Memcached package.
 * It provides a way to perform tests without having an instance Memcache running or even having the
 * Memcached package available. For the latter this class explicitly does not extends the Memcached class.
 *
 * @api
 *
 * @author SpacePossum
 */
class MemcachedMock
{
    /**
     * @var MemcacheObject[]
     */
    private $cache = [];

    /**
     * @var array
     */
    private $options;
    private $connections = [];
    private $isPersistent = false;
    private $isPristine = false;
    private $resultCode = 0;
    private $resultMessage = 'SUCCESS';

    // mock internals
    private $delayedFlush = -1; // UNIX timestamp
    private $deleteQueue = [];
    private $throwExceptionOnFailure = false;

    /**
     * @var MemcachedLogger|null
     */
    private $logger;

    public function __construct($persistent_id = null, $callback = null)
    {
        $this->isPersistent = null !== $persistent_id;
        $this->options = [
            -1003 => 1,    // \Memcached::OPT_SERIALIZER           / 1 => \Memcached::SERIALIZER_PHP
            -1002 => '',   // \Memcached::OPT_PREFIX_KEY
            -1001 => true, // \Memcached::OPT_COMPRESSION
                0 => 0,    // \Memcached::OPT_NO_BLOCK             / The docs on the default are wrong
                1 => 0,    // \Memcached::OPT_TCP_NODELAY          / The docs on the default are wrong
                2 => 0,    // \Memcached::OPT_HASH                 / 0 => \Memcached::HASH_DEFAULT
               16 => 0,    // \Memcached::OPT_LIBKETAMA_COMPATIBLE / The docs on the default are wrong
               18 => 0,    // \Memcached::OPT_BINARY_PROTOCOL      / The docs on the default are wrong
               19 => 0,    // \Memcached::OPT_SEND_TIMEOUT
               20 => 0,    // \Memcached::OPT_RECV_TIMEOUT
        ];
    }

    /**
     * @note Not available on Memcached class.
     *
     * @return MemcachedLogger|null
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @note Not available on Memcached class.
     *
     * @param MemcachedLogger $logger
     */
    public function setLogger(MemcachedLogger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @note Not available on Memcached class.
     *
     * @param string $key
     *
     * @return int|false Unix timestamp or false if not in cache
     */
    public function getExpiry($key)
    {
        $key = $this->getPrefix().$key;
        if (!$this->isInCache($key)) {
            return false;
        }

        return $this->fetchExpirationFromCache($key);
    }

    /**
     * @note Not available on Memcached class.
     *
     * @param bool $flag
     */
    public function setThrowExceptionsOnFailure($flag)
    {
        $this->throwExceptionOnFailure = $flag;
    }

    //--------------------------------------------------------
    // Connection management
    //--------------------------------------------------------

    public function addServer($host, $port, $weight = 0)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('addServer', ['host' => $host, 'port' => $port, 'weight' => $weight]);
        }

        if (!$this->assertServer($host, $port, $weight)) {
            $this->stopMethod();

            return false;
        }

        $this->connections[] = ['host' => $host, 'port' => $port, $weight];
        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    public function addServers(array $servers)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('addServers', ['servers' => $servers]);
        }

        /** @var array $server */
        foreach ($servers as $server) {
            $this->assertArrayValue($server);
            $count = count($server);
            $weight = 0;
            if (3 === $count) {
                $weight = $server[2];
            } elseif (2 !== $count) {
                if (!$this->failedAssert(sprintf('server array must contain 2 or 3 elements, got "%d".', $count))) {
                    $this->stopMethod();

                    return false;
                }
            }

            list($host, $port) = $server;
            if (!$this->assertServer($host, $port, $weight)) {
                $this->stopMethod();

                return false;
            }

            $this->connections[] = ['host' => $host, 'port' => $port, $weight];
        }

        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    public function getServerList()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('getServerList');
        }

        $servers = [];
        foreach ($this->connections as $connection) {
            // do _not_ return weight, @see https://github.com/php-memcached-dev/php-memcached/pull/56
            $servers[] = ['host' => $connection['host'], 'port' => $connection['port']];
        }

        $this->setResultOK();
        $this->stopMethod();

        return $servers;
    }

    public function isPersistent()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('isPersistent');
        }

        $this->setResultOK();
        $this->stopMethod();

        return $this->isPersistent;
    }

    public function isPristine()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('isPristine');
        }

        $this->setResultOK();
        $this->stopMethod();

        return $this->isPristine;
    }

    public function quit()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('quit');
        }

        $this->connections = [];
        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    public function resetServerList()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('resetServerList');
        }

        $this->connections = [];
        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    //--------------------------------------------------------
    // Options
    //--------------------------------------------------------

    /**
     * @param int $option
     *
     * @return mixed false on error
     */
    public function getOption($option)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('getOption', ['option' => $option]);
        }

        if (!$this->assertOption($option)) {
            $this->stopMethod();

            return false;
        }

        $this->setResultOK();
        $this->stopMethod();

        return array_key_exists($option, $this->options) ? $this->options[$option] : false;
    }

    /**
     * @param int   $option
     * @param mixed $value
     *
     * @return bool
     */
    public function setOption($option, $value)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('setOption', ['option' => $option, 'value' => $value]);
        }

        if (!$this->setOptionImpl($option, $value)) {
            $this->stopMethod();

            return false;
        }

        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    /**
     * @param array $options array<int, mixed>
     *
     * @return bool
     */
    public function setOptions(array $options)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('setOptions', ['options' => $options]);
        }

        $result = 1;
        foreach ($options as $option => $value) {
            $result &= $this->setOptionImpl($option, $value);
        }

        if (1 !== $result) {
            $this->stopMethod();

            return false;
        }

        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    private function setOptionImpl($option, $value)
    {
        if (!$this->assertOption($option)) {
            return false;
        }

        if (!$this->assertOptionValue($value)) {
            //if not int, memcached triggers error and returns null and not false as documented
            return false;
        }

        switch ($option) {
            case -1002: // \Memcached::OPT_PREFIX_KEY
                if (!$this->assertPrefix($value)) {
                    return false;
                }

                break;
            case 19:    // \Memcached::OPT_SEND_TIMEOUT
            case 20:    // \Memcached::OPT_RECV_TIMEOUT
                if (!$this->assertIntValue($value, sprintf('Invalid value for option "%d".', $option))) {
                    return false;
                }

                break;
        }

        $this->options[$option] = $value;

        return true;
    }

    //--------------------------------------------------------
    // misc
    //--------------------------------------------------------

    /**
     * @return false|string[]
     */
    public function getAllKeys()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('getAllKeys');
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        $this->setResultOK();
        $this->stopMethod();

        return $this->getKeysFromCache();
    }

    public function getStats()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('getStats');
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        $stats = [];
        foreach ($this->connections as $connection) {
            $stats[sprintf('%s:%d', $connection['host'], $connection['port'])] = ['version' => '1.x.dev'];
        }

        $this->setResultOK();
        $this->stopMethod();

        return $stats;
    }

    public function getVersion()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('getVersion');
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        $versions = [];
        foreach ($this->connections as $connection) {
            $versions[sprintf('%s:%d', $connection['host'], $connection['port'])] = 'x.x.mock';
        }

        $this->setResultOK();
        $this->stopMethod();

        return $versions;
    }

    //--------------------------------------------------------
    // result fetching
    //--------------------------------------------------------

    public function getResultCode()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('getResultCode');
            $this->stopMethod();
        }

        return $this->resultCode;
    }

    public function getResultMessage()
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('getResultMessage');
            $this->stopMethod();
        }

        return $this->resultMessage;
    }

    //--------------------------------------------------------
    // Getting, setting and deleting data
    //--------------------------------------------------------

    public function add($key, $value, $expiration = null)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('add', ['key' => $key, 'value' => $value, 'expiration' => $expiration]);
        }

        if (!$this->assertConnected()) {
            $this->setResultFailed(5); // \Memcached::RES_WRITE_FAILURE
            $this->stopMethod();

            return false;
        }

        if (!$this->assertKey($key)) {
            $this->stopMethod();

            return false;
        }

        $key = $this->getPrefix().$key;
        if (!$this->assertHasNotInDeleteQueue($key)) {
            $this->setResultFailed(12); // \Memcached::RES_DATA_EXISTS doc states \Memcached::RES_NOTSTORED :(
            $this->stopMethod();

            return false;
        }

        if (!$this->assertHasNotInCache($key)) {
            $this->setResultFailed(12); // \Memcached::RES_DATA_EXISTS doc states \Memcached::RES_NOTSTORED :(
            $this->stopMethod();

            return false;
        }

        if (!$this->assertValue($value)) {
            $this->stopMethod();

            return false;
        }

        if (!$this->assertExpiration($expiration)) {
            $this->stopMethod();

            return false;
        }

        $this->storeValueInCache($key, $value, $expiration);
        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    public function append($key, $value)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('append', ['key' => $key, 'value' => $value]);
        }

        // Assert compression is off

        if (!$this->assertConnected()) {
            $this->setResultFailed(5); // \Memcached::RES_WRITE_FAILURE
            $this->stopMethod();

            return false;
        }

        if (!$this->assertKey($key)) {
            $this->stopMethod();

            return false;
        }

        $key = $this->getPrefix().$key;

        if (!$this->assertHasInCache($key)) {
            $this->setResultFailed(14); // \Memcached::RES_NOTSTORED
            $this->stopMethod();

            return false;
        }

        if (!$this->assertScalarValue($value, 'Can only append a scalar value.')) {
            $this->stopMethod();

            return false;
        }

        $currentValue = $this->fetchValueFromCache($key);
        // check if can append to current value
        if (!$this->assertScalarValue($currentValue, 'Append can only done on a scalar.')) {
            $this->stopMethod();

            return false;
        }

        $this->storeValueInCache($key, $currentValue.$value, $this->fetchExpirationFromCache($key));
        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    public function decrement($key, $offset = 1, $initial_value = 0, $expiry = 0)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('decrement', ['key' => $key, 'offset' => $offset, 'initial_value' => $initial_value, 'expiry' => $expiry]);
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        if (!$this->assertKey($key)) {
            $this->stopMethod();

            return false;
        }

        $key = $this->getPrefix().$key;

        if ($this->isInCache($key)) {
            if (!$this->assertOffset($offset)) {
                $this->stopMethod();

                return false;
            }

            $value = $this->fetchValueFromCache($key);
            if (!$this->assertIntValue($value, 'Decrement can only done on integer value.')) {
                $this->stopMethod();

                return false;
            }

            $value -= $offset;
            if ($value < 0) {
                $value = 0;
            }

            $expiry = $this->fetchExpirationFromCache($key);
        } else {
            if (!$this->assertExpiration($expiry)) {
                $this->stopMethod();

                return false;
            }

            if (!$this->assertIntValue($initial_value)) {
                $this->stopMethod();

                return false;
            }

            $value = $initial_value;
        }

        $this->storeValueInCache($key, $value, $expiry);
        $this->setResultOK();
        $this->stopMethod();

        return $value;
    }

    public function delete($key, $time = 0)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('delete', ['key' => $key, 'time' => $time]);
        }

        $result = $this->deleteImpl($key, $time);
        $this->stopMethod();

        return $result;
    }

    public function deleteMulti(array $keys, $time = 0)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('deleteMulti', ['keys' => $keys, 'time' => $time]);
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        $result = 1;
        foreach ($keys as $key) {
            $result &= $this->deleteImpl($key, $time);
        }

        if (1 !== $result) {
            $this->setResultFailed(16);
            $this->stopMethod();

            return false;
        }

        $this->stopMethod();

        return true;
    }

    private function deleteImpl($key, $time = 0)
    {
        if (!$this->assertConnected()) {
            return false;
        }

        if (!$this->assertExpiration($time)) {
            return false;
        }

        if (!$this->assertKey($key)) {
            return false;
        }

        $key = $this->getPrefix().$key;

        if (!$this->isInCache($key) && !$this->isInDeleteQueue($key)) {
            $this->setResultFailed(16);

            return false;
        }

        $this->deleteFromCache($key, $time);
        $this->setResultOK();

        return true;
    }

    public function flush($delay = 0)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('flush', ['delay' => $delay]);
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        if (!$this->assertDelay($delay)) {
            $this->stopMethod();

            return false;
        }

        $this->flushCache($delay);
        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    public function get($key, callable $cache_cb = null, &$cas_token = null)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('get', ['key' => $key, 'cache_cb' => null !== $cache_cb, 'cas_token' => null !== $cas_token]);
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        if (!$this->assertKey($key)) {
            $this->stopMethod();

            return false;
        }

        $key = $this->getPrefix().$key;

        if ($this->isInCache($key)) {
            $this->setResultOK();
            $this->stopMethod();

            return $this->fetchValueFromCache($key);
        }

        // Read-through simulation, @see http://php.net/manual/en/memcached.callbacks.read-through.php
        if (null === $cache_cb) {
            $this->stopMethod();

            return false;
        }

        $value = null;
        $result = $cache_cb($this, $key, $value);
        if ($result === false || !$this->assertCallBackResult($result)) {
            $this->stopMethod();

            return false;
        }

        if (!$this->assertValue($value)) {
            $this->stopMethod();

            return false;
        }

        $this->storeValueInCache($key, $value, 0); // 0 is default expire time
        $this->setResultOK();
        $this->stopMethod();

        return $value;
    }

    public function getMulti(array $keys, array &$cas_tokens = null, $flags = null)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('getMulti', ['keys' => $keys, 'cas_tokens' => null !== $cas_tokens, 'flags' => $flags]);
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        $result = [];
        for ($i = 0, $count = count($keys); $i < $count; ++$i) {
            if (!$this->assertKey($keys[$i])) {
                $this->stopMethod();

                return false;
            }

            $key = $this->getPrefix().$keys[$i];
            if ($this->isInCache($key)) {
                $result[$keys[$i]] = $this->fetchValueFromCache($key);
            }
        }

        $this->setResultOK();
        $this->stopMethod();

        return $result;
    }

    public function increment($key, $offset = 1, $initial_value = 0, $expiry = 0)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('increment', ['key' => $key, 'offset' => $offset, 'initial_value' => $initial_value, 'expiry' => $expiry]);
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        if (!$this->assertKey($key)) {
            $this->stopMethod();

            return false;
        }

        $key = $this->getPrefix().$key;

        if ($this->isInCache($key)) {
            if (!$this->assertOffset($offset)) {
                $this->stopMethod();

                return false;
            }

            $value = $this->fetchValueFromCache($key);
            if (!$this->assertIntValue($value, 'Increment can only be done on integer value.')) {
                $this->stopMethod();

                return false;
            }

            $value += $offset;
            $expiry = $this->fetchExpirationFromCache($key);
        } else {
            if (!$this->assertExpiration($expiry)) {
                $this->stopMethod();

                return false;
            }

            $value = $initial_value;
        }

        $this->storeValueInCache($key, $value, $expiry);
        $this->setResultOK();
        $this->stopMethod();

        return $value;
    }

    public function prepend($key, $value)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('prepend', ['key' => $key, 'value' => $value]);
        }

        if (!$this->assertConnected()) {
            $this->setResultFailed(5); // \Memcached::RES_WRITE_FAILURE
            $this->stopMethod();

            return false;
        }

        if (!$this->assertKey($key)) {
            $this->stopMethod();

            return false;
        }

        $key = $this->getPrefix().$key;

        if (!$this->assertHasInCache($key)) {
            $this->setResultFailed(14); // \Memcached::RES_NOTSTORED
            $this->stopMethod();

            return false;
        }

        if (!$this->assertScalarValue($value)) {
            $this->stopMethod();

            return false;
        }

        $currentValue = $this->fetchValueFromCache($key);
        // check if can prepend to current value
        if (!$this->assertScalarValue($currentValue)) {
            $this->stopMethod();

            return false;
        }

        $this->storeValueInCache($key, $value.$currentValue, $this->fetchExpirationFromCache($key));
        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    public function replace($key, $value, $expiration = null)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('replace', ['key' => $key, 'value' => $value, 'expiration' => $expiration]);
        }

        if (!$this->assertConnected()) {
            $this->setResultFailed(5); // \Memcached::RES_WRITE_FAILURE
            $this->stopMethod();

            return false;
        }

        if (!$this->assertKey($key)) {
            $this->stopMethod();

            return false;
        }

        $key = $this->getPrefix().$key;
        if (!$this->assertHasNotInDeleteQueue($key)) {
            $this->setResultFailed(12); // \Memcached::RES_DATA_EXISTS doc states \Memcached::RES_NOTSTORED :(
            $this->stopMethod();

            return false;
        }

        if (!$this->assertHasInCache($key)) {
            $this->setResultFailed(16); // \Memcached::RES_NOTFOUND docs state RES_NOTSTORED :(
            $this->stopMethod();

            return false;
        }

        if (!$this->assertValue($value)) {
            $this->stopMethod();

            return false;
        }

        if (!$this->assertExpiration($expiration)) {
            $this->stopMethod();

            return false;
        }

        $this->storeValueInCache($key, $value, $expiration);
        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    public function set($key, $value, $expiration = null)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('set', ['key' => $key, 'value' => $value, 'expiration' => $expiration]);
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        $result = $this->setImpl($key, $value, $expiration);
        $this->stopMethod();

        return $result;
    }

    public function setMulti(array $items, $expiration = null)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('setMulti', ['items' => $items, 'expiration' => $expiration]);
        }

        if (!$this->assertConnected()) {
            $this->stopMethod();

            return false;
        }

        $result = 1;
        foreach ($items as $key => $value) {
            $result &= $this->setImpl($key, $value, $expiration);
        }

        $this->stopMethod();

        return 1 === $result;
    }

    private function setImpl($key, $value, $expiration = null)
    {
        if (!$this->assertKey($key)) {
            return false;
        }

        $key = $this->getPrefix().$key;

        if (!$this->assertValue($value)) {
            return false;
        }

        if (!$this->assertExpiration($expiration)) {
            return false;
        }

        $this->storeValueInCache($key, $value, $expiration);
        $this->setResultOK();

        return true;
    }

    public function touch($key, $expiration)
    {
        if (null !== $this->logger) {
            $this->logger->startMethod('touch', ['key' => $key, 'expiration' => $expiration]);
        }

        // Note: only available for 'binary protocol'
        //if (!$this->assertOptionIsSet(18)) { // \Memcached::OPT_BINARY_PROTOCOL
        //    return false;
        //}

        if (!$this->assertConnected()) {
            $this->setResultFailed(5); // \Memcached::RES_WRITE_FAILURE
            $this->stopMethod();

            return false;
        }

        if (!$this->assertKey($key)) {
            $this->stopMethod();

            return false;
        }

        $key = $this->getPrefix().$key;

        if (!$this->assertHasInCache($key)) {
            $this->setResultFailed(16); // \Memcached::RES_NOTFOUND
            $this->stopMethod();

            return false;
        }

        if (!$this->assertExpiration($expiration)) {
            $this->stopMethod();

            return false;
        }

        $this->cache[$key]->setExpireTime($this->normalizeTime($expiration));
        $this->setResultOK();
        $this->stopMethod();

        return true;
    }

    //--------------------------------------------------------
    // Not supported methods of the Memcached v.2.1.0 API
    //--------------------------------------------------------

    public function addByKey($server_key, $key, $value, $expiration = null)
    {
        throw new \BadMethodCallException('"addByKey" is not implemented.');
    }

    public function appendByKey($server_key, $key, $value)
    {
        throw new \BadMethodCallException('"appendByKey" is not implemented.');
    }

    public function cas($cas_token, $key, $value, $expiration = null)
    {
        throw new \BadMethodCallException('"cas" is not implemented.');
    }

    public function casByKey($cas_token, $server_key, $key, $value, $expiration = null)
    {
        throw new \BadMethodCallException('"casByKey" is not implemented.');
    }

    public function decrementByKey($server_key, $key, $offset = 1, $initial_value = 0, $expiry = 0)
    {
        throw new \BadMethodCallException('"decrementByKey" not implemented.');
    }

    public function deleteByKey($server_key, $key, $time = 0)
    {
        throw new \BadMethodCallException('"deleteByKey" is not implemented.');
    }

    public function deleteMultiByKey($server_key, array $keys, $time = 0)
    {
        throw new \BadMethodCallException('"deleteMultiByKey" is not implemented.');
    }

    public function fetch()
    {
        throw new \BadMethodCallException('"fetch" is not implemented.');
    }

    public function fetchAll()
    {
        throw new \BadMethodCallException('"fetchAll" is not implemented.');
    }

    public function getByKey($server_key, $key, callable $cache_cb = null, &$cas_token = null)
    {
        throw new \BadMethodCallException('"getByKey" not implemented.');
    }

    public function getDelayed(array $keys, $with_cas = null, callable $value_cb = null)
    {
        throw new \BadMethodCallException('"getDelayed" is not implemented.');
    }

    public function getDelayedByKey($server_key, array $keys, $with_cas = null, callable $value_cb = null)
    {
        throw new \BadMethodCallException('"getDelayedByKey" is not implemented.');
    }

    public function getMultiByKey($server_key, array $keys, &$cas_tokens = null, $flags = null)
    {
        throw new \BadMethodCallException('"getMultiByKey" is not implemented.');
    }

    public function getServerByKey($server_key)
    {
        throw new \BadMethodCallException('"getServerByKey" is not implemented.');
    }

    public function incrementByKey($server_key, $key, $offset = 1, $initial_value = 0, $expiry = 0)
    {
        throw new \BadMethodCallException('"incrementByKey" is not implemented.');
    }

    public function prependByKey($server_key, $key, $value)
    {
        throw new \BadMethodCallException('"prependByKey" is not implemented.');
    }

    public function replaceByKey($server_key, $key, $value, $expiration = null)
    {
        throw new \BadMethodCallException('"replaceByKey" is not implemented.');
    }

    public function setByKey($server_key, $key, $value, $expiration = null)
    {
        throw new \BadMethodCallException('"setByKey" is not implemented.');
    }

    public function setMultiByKey($server_key, array $items, $expiration = null)
    {
        throw new \BadMethodCallException('"setMultiByKey" is not implemented.');
    }

    public function touchByKey($server_key, $key, $expiration)
    {
        throw new \BadMethodCallException('"touchByKey" is not implemented.');
    }

    //--------------------------------------------------------
    // internals
    //--------------------------------------------------------

    private function checkForDelayedFlush()
    {
        if ($this->delayedFlush > 0 && $this->delayedFlush <= time()) {
            $this->cache = [];
            $this->delayedFlush = 0;
        }
    }

    private function deleteFromCache($key, $delay)
    {
        if (0 === $delay) {
            unset($this->cache[$key]);

            return;
        }

        $delay = $this->normalizeTime($delay);
        // `add` and `replace` are effected by the delay
        if (array_key_exists($key, $this->deleteQueue) && $this->deleteQueue[$key] < $delay) {
            // re-queued for deleting, keep current first time of deletion FIXME make sure this is memcached behaviour.
            return;
        }

        $this->deleteQueue[$key] = $delay;
    }

    private function flushCache($delay)
    {
        if ($delay < 1) {
            $this->cache = [];
        } else {
            $this->delayedFlush = $this->normalizeTime($delay);
        }
    }

    private function getKeysFromCache()
    {
        $this->checkForDelayedFlush();
        $keys = array_keys($this->cache);
        // not use array_filter with $this in closure to prevent having to require 5.4
        $filteredKeys = [];
        foreach ($keys as $key) {
            if (!$this->isInDeleteQueue($key)) {
                $filteredKeys[] = $key;
            }
        }

        return $filteredKeys;
    }

    private function getPrefix()
    {
        return array_key_exists(-1002, $this->options) ? $this->options[-1002] : false; // \Memcached::OPT_PREFIX_KEY
    }

    private function isInCache($key)
    {
        $this->checkForDelayedFlush();
        if (!array_key_exists($key, $this->cache)) {
            return false;
        }

        if ($this->cache[$key]->getExpireTime() < time()) {
            unset($this->cache[$key]);

            return false;
        }

        return !$this->isInDeleteQueue($key);
    }

    private function isInDeleteQueue($key)
    {
        if (!array_key_exists($key, $this->deleteQueue)) {
            return false;
        }

        if ($this->deleteQueue[$key] < time()) {
            unset($this->deleteQueue[$key]);

            return false;
        }

        return true;
    }

    private function normalizeTime($time)
    {
        if (null === $time || 0 === $time) {
            return time();
        }

        if ($time < 2592000) { // 60 * 60 * 24 * 30 @see http://php.net/manual/en/memcached.expiration.php
            return time() + $time;
        }

        return $time;
    }

    /**
     * Dumb setter, no checks by design.
     *
     * @param string $key
     *
     * @return int|null
     */
    private function fetchExpirationFromCache($key)
    {
        return $this->cache[$key]->getExpireTime();
    }

    /**
     * Dumb setter, no checks by design.
     *
     * @param string $key
     *
     * @return mixed
     */
    private function fetchValueFromCache($key)
    {
        return unserialize($this->cache[$key]->getValue());
    }

    /**
     * Dumb setter, no checks by design.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $expiration
     */
    private function storeValueInCache($key, $value, $expiration = null)
    {
        $this->cache[$key] = new MemcacheObject($key, serialize($value), $this->normalizeTime($expiration));
    }

    //--------------------------------------------------------
    // asserts
    //--------------------------------------------------------

    private function assertCallBackResult($result, $message = null)
    {
        if (!is_bool($result)) {
            return $this->failedAssert(sprintf('callback returned is bool, got "%s".', is_object($result) ? get_class($result) : gettype($result)), $message);
        }

        return true;
    }

    private function assertConnected()
    {
        if (count($this->connections) < 1) {
            $this->setResultFailed(2);

            return $this->failedAssert('is connected.');
        }

        return true;
    }

    private function assertDelay($delay)
    {
        if (!is_int($delay)) {
            return $this->failedAssert(sprintf('delay is an integer, got "%s".', is_object($delay) ? get_class($delay) : gettype($delay)));
        }

        if ($delay < 0) {
            return $this->failedAssert(sprintf('delay is greater than or equals 0, got "%d".', $delay));
        }

        return true;
    }

    private function assertExpiration($expiration)
    {
        if (null !== $expiration && !is_int($expiration)) {
            return $this->failedAssert(sprintf('expiration is an integer, got "%s".', is_object($expiration) ? get_class($expiration) : gettype($expiration)));
        }

        return true;
    }

    private function assertHasInCache($key)
    {
        if (!$this->isInCache($key)) {
            return $this->failedAssert(sprintf('key "%s" is in cache.', $key));
        }

        return true;
    }

    private function assertHasNotInCache($key)
    {
        if ($this->isInCache($key)) {
            return $this->failedAssert(sprintf('key "%s" is not in cache.', $key));
        }

        return true;
    }

    private function assertHasNotInDeleteQueue($key)
    {
        if ($this->isInDeleteQueue($key)) {
            return $this->failedAssert(sprintf('key is not in delete queue, got "%s".', $key));
        }

        return true;
    }

    private function assertArrayValue($value, $message = null)
    {
        if (!is_array($value)) {
            return $this->failedAssert(sprintf('value is an array, got "%s".', is_object($value) ? get_class($value) : gettype($value)), $message);
        }

        return true;
    }

    private function assertIntValue($value, $message = null)
    {
        if (!is_int($value)) {
            return $this->failedAssert(sprintf('value is an integer, got "%s".', is_object($value) ? get_class($value) : gettype($value)), $message);
        }

        return true;
    }

    private function assertKey($key, $message = null)
    {
        if (!is_string($key)) {
            return $this->failedAssert(sprintf('key is a string, got "%s".', is_object($key) ? get_class($key) : gettype($key)), $message);
        }

        $key = $this->getPrefix().$key;
        if (strlen($key) > 256) {
            return $this->failedAssert(sprintf('key (+ prefix) is less than 256 characters, got "%s" (%d).', $key, strlen($key)), $message);
        }

        return true;
    }

    private function assertOffset($offset)
    {
        if (!is_int($offset)) {
            return $this->failedAssert(sprintf('offset is an integer, got "%s".', is_object($offset) ? get_class($offset) : gettype($offset)));
        }

        return true;
    }

    private function assertOption($option)
    {
        if (!is_int($option)) {
            // if not int, memcached triggers an error and returns null and not false as documented
            return $this->failedAssert(sprintf('option is an integer, got "%s".', is_object($option) ? get_class($option) : gettype($option)));
        }

        static $knownOptions = [
             -1004, // \Memcached::OPT_COMPRESSION_TYPE
             -1003, // \Memcached::OPT_SERIALIZER
             -1002, // \Memcached::OPT_PREFIX_KEY
             -1001, // \Memcached::OPT_COMPRESSION
                 0, // \Memcached::OPT_NO_BLOCK
                 1, // \Memcached::OPT_TCP_NODELAY
                 2, // \Memcached::OPT_HASH
                 5, // \Memcached::OPT_SOCKET_RECV_SIZE
                 6, // \Memcached::OPT_CACHE_LOOKUPS
                 8, // \Memcached::OPT_POLL_TIMEOUT
                10, // \Memcached::OPT_BUFFER_WRITES
                12, // \Memcached::OPT_SORT_HOSTS
                13, // \Memcached::OPT_VERIFY_KEY
                14, // \Memcached::OPT_CONNECT_TIMEOUT
                15, // \Memcached::OPT_RETRY_TIMEOUT
                16, // \Memcached::OPT_LIBKETAMA_COMPATIBLE
                17, // \Memcached::OPT_LIBKETAMA_HASH
                18, // \Memcached::OPT_BINARY_PROTOCOL
                19, // \Memcached::OPT_SEND_TIMEOUT
                20, // \Memcached::OPT_RECV_TIMEOUT
                21, // \Memcached::OPT_SERVER_FAILURE_LIMIT
                25, // \Memcached::OPT_HASH_WITH_PREFIX_KEY
                26, // \Memcached::OPT_NOREPLY
                27, // \Memcached::OPT_USE_UDP
                28, // \Memcached::OPT_AUTO_EJECT_HOSTS
                29, // \Memcached::OPT_NUMBER_OF_REPLICAS
                30, // \Memcached::OPT_RANDOMIZE_REPLICA_READ
                32, // \Memcached::OPT_TCP_KEEPALIVE
                35, // \Memcached::OPT_REMOVE_FAILED_SERVERS
        ];

        if (false === in_array($option, $knownOptions, true)) {
            return $this->failedAssert(sprintf('option is known, got "%d".', $option));
        }

        return true;
    }

    //private function assertOptionIsSet($option)
    //{
    //   return $this->assertOption($option) && array_key_exists($option, $this->options);
    //}

    private function assertOptionValue($value)
    {
        if (is_resource($value)) {
            return $this->failedAssert(sprintf('value is not a resource, got "%s".', get_resource_type($value)));
        }

        return true;
    }

    private function assertPrefix($prefix)
    {
        if (!is_string($prefix)) {
            return $this->failedAssert(sprintf('prefix is a string, got "%s".', is_object($prefix) ? get_class($prefix) : gettype($prefix)));
        }

        if (strlen($prefix) > 128) {
            return $this->failedAssert(sprintf('prefix is less than 128 characters, got "%s" (%d).', $prefix, strlen($prefix)));
        }

        return true;
    }

    private function assertScalarValue($value, $message = null)
    {
        if (!is_scalar($value)) {
            return $this->failedAssert(sprintf('value is a scalar, got "%s".', is_object($value) ? get_class($value) : gettype($value)), $message);
        }

        return true;
    }

    private function assertServer($host, $port, $weight)
    {
        if (!is_string($host)) {
            $this->setResultFailed(2); // RES_HOST_LOOKUP_FAILURE
            return $this->failedAssert(sprintf('host is a string, got "%s".', is_object($host) ? get_class($host) : gettype($host)));
        }

        if (!is_int($port)) {
            $this->setResultFailed(2); // RES_HOST_LOOKUP_FAILURE
            return $this->failedAssert(sprintf('port is an integer, got "%s".', is_object($port) ? get_class($port) : gettype($port)));
        }

        if ($port < 1) {
            $this->setResultFailed(2); // RES_HOST_LOOKUP_FAILURE
            return $this->failedAssert(sprintf('port is greater than 0, got "%d".', $port));
        }

        if (!is_int($weight)) {
            $this->setResultFailed(2); // RES_HOST_LOOKUP_FAILURE
            return $this->failedAssert(sprintf('weight is an integer, got "%s".', is_object($weight) ? get_class($weight) : gettype($weight)));
        }

        if ($weight < 0) {
            $this->setResultFailed(2); // RES_HOST_LOOKUP_FAILURE
            return $this->failedAssert(sprintf('weight greater than or equals 0, got "%d".', $weight));
        }

        return true;
    }

    private function assertValue($value, $message = null)
    {
        if (is_resource($value)) {
            return $this->failedAssert(sprintf('value is not a resource, got "%s".', get_resource_type($value)), $message);
        }

        return true;
    }

    /**
     * @param string      $assert
     * @param string|null $message
     *
     * @throws MemcachedMockAssertException (when configured)
     *
     * @return false
     */
    private function failedAssert($assert, $message = null)
    {
        $stack = debug_backtrace(false);
        $message = sprintf('%s failed %s%s', $stack[1]['function'], $assert, null === $message ? '' : ' '.$message);

        if (null !== $this->logger && null !== $logger = $this->logger->getLogger()) {
            $logger->error($message);
        }

        if ($this->throwExceptionOnFailure) {
            throw new MemcachedMockAssertException($message);
        }

        return false;
    }

    //--------------------------------------------------------
    // result tracking
    //--------------------------------------------------------

    /**
     * @param int         $code
     * @param string|null $message
     */
    private function setResultFailed($code, $message = null)
    {
        if (null === $message) {
            switch ($code) {
                case 2: {
                    $message = 'getaddrinfo() or getnameinfo() HOSTNAME LOOKUP FAILURE'; // not sure why memcached returns the function names
                    break;
                }
                case 5: {
                    $message = 'WRITE FAILURE';
                    break;
                }
                case 12: {
                    $message = 'CONNECTION DATA EXISTS';
                    break;
                }
                case 14: {
                    $message = 'NOT STORED';
                    break;
                }
                case 16: {
                    $message = 'NOT FOUND';
                    break;
                }
                default: {
                    throw new \UnexpectedValueException(sprintf('Unknown result failed code "%d", supply an error message.', $code));
                }
            }
        }

        $this->resultCode = $code;
        $this->resultMessage = $message;
    }

    private function setResultOK()
    {
        $this->resultCode = 0; // \Memcached::RES_SUCCESS
        $this->resultMessage = 'SUCCESS';
    }

    private function stopMethod()
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->stopMethod();
    }
}
