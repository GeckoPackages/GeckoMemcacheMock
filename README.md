Travis test

#### GeckoPackages

# Memcache mock

Memcache mock (http://memcached.org/) which mimics the Memcached package (http://php.net/manual/en/book.memcached.php) (API) as much as possible.
Its use is for test purposes for software that support interaction with Memcache using the Memcached package.
It provides a way to perform tests without having an instance of Memcache running or even having the Memcached package available.
The mock is written in PHP as class and is therefor not persistent beyond the execution of the script that creates it.

### Requirements

PHP 5.3.3 for the Symfony Stopwatch Component

### Install

The package can be installed using Composer (https://getcomposer.org/).
Add the package to your `composer.json`.

```
"require-dev": {
    "gecko-packages/gecko-memcache-mock" : "0.1"
}
```

# Usage

## Basics

The mock supports the majority of Memcached methods, so it can used the same.
A small example:

```php
use GeckoPackages\MemcacheMock\MemcachedMock;

$cache = new MemcachedMock();
$cache->addServer('127.0.0.1', 11211);

// simple get set
$cache->set('a', 'b');
$cache->get('a');

// read through simulation
$callBack = function ($memc, $key, &$value) {
    $value = 'testValue';
    return true;
};

// returns `testValue`
$cache->get('c', $callBack);
```

## Logging (observing)

There is support for adding a logger so the usage of the methods can be observed.
For example:

```php
use GeckoPackages\MemcacheMock\MemcachedMock;

$cache = new MemcachedMock();
$cache->addServer('127.0.0.1', 11211);

// register logger, an instance of a StopWatch can be passed as second argument
$cache->setLogger(new MemcachedLogger(new MyPSR3Logger()));

// use mock

$logger = $cache->getLogger();
$myPSR3Logger = $logger->getLogger();
// test what is logged by using your own logger
```

## Asserts to exceptions

The mock can be configured to throw exceptions when invalid values are passed to methods or
when methods are called in an invalid order.
This makes finding bugs and issues more easy because the mock is more strict than the Memcached class.
For example:

```php
use GeckoPackages\MemcacheMock\MemcachedMock;

$cache = new MemcachedMock();
$cache->addServer('127.0.0.1', 11211);

// enable throwing exceptions when asserts fail
$cache->setThrowExceptionsOnFailure(true);

// throws:
// GeckoPackages\MemcacheMock\MemcachedMockAssertException:
// assertKey failed key (+ prefix) is less than 256 characters, got
$cache->get($keyLongerThan265Characters);

```

## Supported and additional methods

The following methods that are available on the Memcached class are available on the mock as well:
* __construct
* add
* addServer
* addServers
* append
* decrement
* delete
* deleteMulti
* flush
* get *note:* `$cas_token` is ignored
* getAllKeys
* getMulti *note:* `$cas_token` and `$flags` are ignored
* getOption
* getResultCode
* getResultMessage
* getServerList
* getStats
* getVersion
* increment
* isPersistent
* isPristine
* prepend
* prependByKey
* quit
* replace
* resetServerList
* set
* setMulti
* setOption
* setOptions
* touch

These methods are _not_ available:
* addByKey
* appendByKey
* cas
* casByKey
* decrementByKey
* deleteByKey
* deleteMultiByKey
* fetch
* fetchAll
* getByKey
* getDelayed
* getDelayedByKey
* getMultiByKey
* getServerByKey
* incrementByKey
* replaceByKey
* setByKey
* setMultiByKey
* touchByKey

The mock has the following additional methods that are not on the Memcached class:
* getAssertFailures
* getExpiry
* getLogger
* setLogger
* setThrowExceptionsOnFailure

### License

The project is released under the MIT license, see the LICENSE file.
