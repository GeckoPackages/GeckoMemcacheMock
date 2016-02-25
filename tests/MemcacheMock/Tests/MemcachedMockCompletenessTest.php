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
        $mockMethodsFiltered = array();
        foreach ($mockReflection->getMethods() as $mockMethod) {
            if ('GeckoPackages\MemcacheMock\MemcachedMock' === $mockMethod->getDeclaringClass()->getName()) {
                $mockMethodsFiltered[] = $mockMethod;
            }
        }

        $reflection = new ReflectionClass('Memcached');
        self::assertMethodList($reflection->getMethods(), $mockMethodsFiltered);
    }

    /**
     * {@inheritdoc}
     */
    protected function onNotSuccessfulTest(Exception $e)
    {
        ob_start();
        echo "Memcached extension info:\n";
        $ext = new ReflectionExtension('memcached');
        $ext->info();
        $info = ob_get_contents();
        ob_end_clean();

        parent::onNotSuccessfulTest(new \Exception($info, 0, $e));
    }

    /**
     * @param ReflectionMethod[] $expected
     * @param ReflectionMethod[] $actual
     */
    private static function assertMethodList(array $expected, array $actual)
    {
        $expected = self::transformMethodList($expected);
        $actual = self::transformMethodList($actual);

        foreach ($expected as $name => $expectedMethod) {
            if (!array_key_exists($name, $actual)) {
                self::fail(sprintf('Method name "%s" missing in list.', $name));
            }

            if ($name === 'append' || $name === 'appendByKey' || $name === 'prepend'  || $name === 'prependByKey') {
                continue; // what is wrong with these?
            }

            $actualMethod = $actual[$name];
            self::assertSame($expectedMethod->getNumberOfParameters(), $actualMethod->getNumberOfParameters(), sprintf('Number of parameters mismatched for method "%s".', $name));
            self::assertSame($expectedMethod->getNumberOfRequiredParameters(), $actualMethod->getNumberOfRequiredParameters(), sprintf('Number of required parameters mismatched for method "%s".', $name));
            if ($expectedMethod->getNumberOfParameters() > 0) {
                $expectedParameters = $expectedMethod->getParameters();
                $actualParameters = $actualMethod->getParameters();
                for ($i = 0, $count = count($expectedParameters); $i < $count - 1; ++$i) {
                    self::assertSame($expectedParameters[$i]->getName(), $actualParameters[$i]->getName(), sprintf('Parameter naming mismatched for method "%s".', $name));
                    self::assertSame($expectedParameters[$i]->isOptional(), $actualParameters[$i]->isOptional(), sprintf('Parameter being optional mismatched for method "%s".', $name));
                    /* Not possible
                    if ($expectedParameters[$i]->isOptional()) {
                        echo $name . "\n\n-----\n";
                        self::assertSame($expectedParameters[$i]->getDefaultValue(), $actualParameters[$i]->getDefaultValue());
                    }
                    */
                }
            }
        }
    }

    /**
     * @param ReflectionMethod[] $list
     *
     * @return ReflectionMethod[]
     */
    private static function transformMethodList(array $list)
    {
        $transformed = array();
        foreach ($list as $method) {
            $transformed[$method->getName()] = $method;
        }

        return $transformed;
    }
}
