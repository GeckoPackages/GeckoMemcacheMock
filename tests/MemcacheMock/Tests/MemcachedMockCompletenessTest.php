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
    public static function testMemcachedMockCompleteness()
    {
        $mockReflection = new ReflectionClass('GeckoPackages\MemcacheMock\MemcachedMock');
        $mockMethodsFiltered = [];
        foreach ($mockReflection->getMethods() as $mockMethod) {
            if ('GeckoPackages\MemcacheMock\MemcachedMock' === $mockMethod->getDeclaringClass()->getName()) {
                $mockMethodsFiltered[] = $mockMethod;
            }
        }

        $reflection = new ReflectionClass('Memcached');
        self::assertMethodList($reflection->getMethods(), $mockMethodsFiltered);
    }

    /**
     * @param ReflectionMethod[] $expected
     * @param ReflectionMethod[] $actual
     */
    private static function assertMethodList(array $expected, array $actual)
    {
        $expected = self::transformMethodList($expected);
        $actual = self::transformMethodList($actual);

        $failures = [];
        foreach ($expected as $name => $expectedMethod) {
            try {
                if (!array_key_exists($name, $actual)) {
                    self::fail(sprintf('Method name "%s" missing in list.', $name));
                }

                if ($name === 'append' || $name === 'appendByKey' || $name === 'prepend' || $name === 'prependByKey') {
                    continue; // what is wrong with these?
                }

                $actualMethod = $actual[$name];
                if ($expectedMethod->getNumberOfParameters() !== $actualMethod->getNumberOfParameters()) {
                    self::fail(sprintf(
                        "Number of parameters mismatched for method \"%s\".\nExpected:\n\"%s\"\nGot:\n\"%s\"",
                        $name, self::describeParameters($expectedMethod), self::describeParameters($actualMethod)
                    ));
                }

                if ($expectedMethod->getNumberOfRequiredParameters() !== $actualMethod->getNumberOfRequiredParameters()) {
                    self::fail(sprintf(
                        "Number of required parameters mismatched for method \"%s\".\nExpected:\n\"%s\"\nGot:\n\"%s\"",
                        $name, self::describeParameters($expectedMethod), self::describeParameters($actualMethod)
                    ));
                }

                if ($expectedMethod->getNumberOfParameters() > 0) {
                    $expectedParameters = $expectedMethod->getParameters();
                    $actualParameters = $actualMethod->getParameters();
                    for ($i = 0, $count = count($expectedParameters); $i < $count - 1; ++$i) {
                        self::assertSame($expectedParameters[$i]->getName(), $actualParameters[$i]->getName(), sprintf('Parameter naming mismatched for method "%s".', $name));
                        self::assertSame($expectedParameters[$i]->isOptional(), $actualParameters[$i]->isOptional(), sprintf('Parameter being optional mismatched for method "%s".', $name));
                    }
                }
            } catch (\PHPUnit_Framework_AssertionFailedError $e) {
                $failures[] = $e;
            } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                $failures[] = $e;
            }
        }

        if (count($failures)) {
            $failMessage = sprintf("Memcached version \"%s\"\n---------------------------------------\nFailures:\n---------------------------------------\n", phpversion('memcached'));
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

            self::fail($failMessage."\n---------------------------------------\n");
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
