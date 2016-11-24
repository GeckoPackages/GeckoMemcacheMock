<?php

/*
 * This file is part of the GeckoPackages.
 *
 * (c) GeckoPackages https://github.com/GeckoPackages
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Test all methods of \Memcached are on the MemcachedMock as well.
 *
 * @author SpacePossum
 *
 * @internal
 *
 * @requires extension memcached
 */
final class MemcachedMockCompletenessTest extends PHPUnit_Framework_TestCase
{
    public function testMemcachedMockCompleteness()
    {
        $mockReflection = new ReflectionClass('GeckoPackages\MemcacheMock\MemcachedMock');
        $mockMethodsFiltered = [];
        foreach ($mockReflection->getMethods() as $mockMethod) {
            if ('GeckoPackages\MemcacheMock\MemcachedMock' === $mockMethod->getDeclaringClass()->getName()) {
                $mockMethodsFiltered[] = $mockMethod;
            }
        }

        $reflection = new ReflectionClass('Memcached');
        $this->assertMethodList($reflection->getMethods(), $mockMethodsFiltered);
    }

    /**
     * @param ReflectionMethod[] $expected
     * @param ReflectionMethod[] $actual
     */
    private function assertMethodList(array $expected, array $actual)
    {
        $expected = self::transformMethodList($expected);
        $actual = self::transformMethodList($actual);

        $failures = [];
        foreach ($expected as $name => $expectedMethod) {
            try {
                if (!array_key_exists($name, $actual)) {
                    $this->fail(sprintf('Method name "%s" missing in list.', $name));
                }

                if ($name === 'append' || $name === 'appendByKey' || $name === 'prepend' || $name === 'prependByKey') {
                    continue; // what is wrong with these?
                }

                $actualMethod = $actual[$name];
                if ($expectedMethod->getNumberOfParameters() !== $actualMethod->getNumberOfParameters()) {
                    $this->fail(sprintf(
                        "Number of parameters mismatched for method \"%s\".\nExpected:\n\"%s\"\nGot:\n\"%s\"",
                        $name, self::describeParameters($expectedMethod), self::describeParameters($actualMethod)
                    ));
                }

                if ($expectedMethod->getNumberOfRequiredParameters() !== $actualMethod->getNumberOfRequiredParameters()) {
                    $this->fail(sprintf(
                        "Number of required parameters mismatched for method \"%s\".\nExpected:\n\"%s\"\nGot:\n\"%s\"",
                        $name, self::describeParameters($expectedMethod), self::describeParameters($actualMethod)
                    ));
                }

                if ($expectedMethod->getNumberOfParameters() > 0) {
                    $expectedParameters = $expectedMethod->getParameters();
                    $actualParameters = $actualMethod->getParameters();
                    for ($i = 0, $count = count($expectedParameters); $i < $count - 1; ++$i) {
                        $this->assertSame($expectedParameters[$i]->getName(), $actualParameters[$i]->getName(), sprintf('Parameter naming mismatched for method "%s".', $name));
                        $this->assertSame($expectedParameters[$i]->isOptional(), $actualParameters[$i]->isOptional(), sprintf('Parameter being optional mismatched for method "%s".', $name));
                    }
                }
            } catch (\PHPUnit_Framework_AssertionFailedError $e) {
                $failures[] = $e;
            } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                $failures[] = $e;
            }
        }

        if (count($failures)) {
            $memcachedVersion = phpversion('memcached');
            $failMessage = sprintf("Memcached version \"%s\"\n---------------------------------------\nFailures:\n---------------------------------------\n", $memcachedVersion);
            /** @var \Exception $failure */
            foreach ($failures as $failure) {
                $failMessage .= sprintf("\n---------------------------------------\n%s\n", $failure->toString());
                $trace = $failure->getTrace();

                foreach ($trace as $item) {
                    if (!array_key_exists('file', $item) || $item['file'] !== __FILE__) {
                        continue;
                    }

                    $failMessage .= sprintf("\n%s:%d", $item['file'], $item['line']);
                }
            }

            if (1 !== preg_match('#^\d+.\d+(.\d+)?$#', $memcachedVersion)) {
                $this->markTestSkipped(sprintf("Memcached %s is not a stabled one, failures on it:\n%s", $memcachedVersion, $failMessage));

                return;
            }

            $this->fail($failMessage."\n---------------------------------------\n");
        }
    }

    private static function describeParameters(\ReflectionMethod $method)
    {
        $params = $method->getParameters();
        $paramsFilter = [];
        /** @var \ReflectionParameter $param */
        foreach ($params as $param) {
            $paramsFilter[$param->getPosition()] = sprintf('%s%s', $param->getName(), $param->isOptional() ? ' [optional]' : '');
        }

        ksort($paramsFilter);

        return implode('","', $paramsFilter);
    }

    /**
     * @param ReflectionMethod[] $list
     *
     * @return ReflectionMethod[]
     */
    private static function transformMethodList(array $list)
    {
        $transformed = [];
        foreach ($list as $method) {
            $transformed[$method->getName()] = $method;
        }

        return $transformed;
    }
}
