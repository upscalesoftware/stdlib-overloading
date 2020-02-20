<?php
/**
 * Copyright © Upscale Software. All rights reserved.
 * See COPYRIGHT.txt for license details.
 */
declare(strict_types=1);

namespace Upscale\Stdlib\Overloading\Tests;

use function Upscale\Stdlib\Overloading\overload;

class OverloadTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var callable
     */
    protected $subject;

    protected function setUp()
    {
        $this->subject = overload(
            function (int $num1, int $num2, int $num3) {
                // Prohibit calls with excess arguments swallowed otherwise
                if (func_num_args() > 3) {
                    throw new \ArgumentCountError('Too many arguments provided');
                }
                return "three required integers: $num1, $num2, $num3";
            },
            function (int $num1, int $num2) {
                return "two required integers: $num1, $num2";
            },
            function (int $num1, int $num2, int $num3, int $num4) {
                // Unreachable because preceding declaration swallows excess arguments
                return "four required integers: $num1, $num2, $num3, $num4";
            },
            function (string $str1, string $str2) {
                return "two required strings: $str1, $str2";
            },
            function (string $str1, int $num2 = 42) {
                return "one required string, one optional integer: $str1, $num2";
            },
            function (string $str1, string $str2 = 'default2') {
                // Unreachable because preceding declaration matches first
                return "one required string, one optional string: $str1, $str2";
            },
            function (string $str1 = 'default1', string $str2 = 'default2') {
                return "two optional strings: $str1, $str2";
            },
            function (string ...$strings) {
                // Unreachable because preceding declarations match first
                return "many optional strings: " . implode(', ', $strings);
            },
            function (float $num1, float $num2) {
                return "two required floats: $num1, $num2";
            },
            function (\stdClass $obj1, self $obj2) {
                return "two required objects";
            }
        );
    }

    public static function fixtureStaticMethod()
    {
        return 'static method fixture result'; 
    }

    public function fixtureMethod()
    {
        return 'regular method fixture result'; 
    }

    public function __invoke()
    {
        return 'magic method fixture result'; 
    }

    /**
     * @dataProvider callbackPriorityDataProvider
     */
    public function testCallbackPriority(array $args, $expected)
    {
        $subject = $this->subject;
        $actual = $subject(...$args);
        $this->assertEquals($expected, $actual);
    }

    public function callbackPriorityDataProvider()
    {
        return [
            'two integers'                  => [[1, 2], 'two required integers: 1, 2'],
            'three integers'                => [[1, 2, 3], 'three required integers: 1, 2, 3'],
            'four integers - unreachable'   => [[1, 2, 3, 4], 'two required integers: 1, 2'],
            'one string'                    => [['a'], 'one required string, one optional integer: a, 42'],
            'two strings'                   => [['a', 'bb'], 'two required strings: a, bb'],
            'one string, one integer'       => [['a', 2], 'one required string, one optional integer: a, 2'],
            'no arguments'                  => [[], 'two optional strings: default1, default2'],
            'four strings - unreachable'    => [['a', 'b', 'c', 'd'], 'two required strings: a, b'],
            'two floats'                    => [[1.41, 3.14], 'two required floats: 1.41, 3.14'],
            'one integer, one float'        => [[1, 3.14], 'two required floats: 1, 3.14'],
            'two objects'                   => [[new \stdClass, $this], 'two required objects'],
        ];
    }

    /**
     * @dataProvider callbackMismatchDataProvider
     * @expectedException \TypeError
     */
    public function testCallbackMismatch(array $args)
    {
        $subject = $this->subject;
        $subject(...$args);
    }

    public function callbackMismatchDataProvider()
    {
        return [
            'one integer'               => [[1]],
            'one string, one float'     => [['a', 2.71]],
            'one object'                => [[new \stdClass]],
            'two objects'               => [[$this, new \stdClass]],
        ];
    }

    /**
     * @dataProvider callbackValidDataProvider
     */
    public function testCallbackValid(callable $callback, $expected)
    {
        $subject = overload($callback);
        $actual = $subject();
        $this->assertEquals($expected, $actual);
    }

    public function callbackValidDataProvider()
    {
        $class = self::class;
        $closure = function () {
            return 'closure fixture result';
        };
        return [
            'closure'       => [$closure, 'closure fixture result'],
            'invokable'     => [$this, 'magic method fixture result'],
            'object method' => [[$this, 'fixtureMethod'], 'regular method fixture result'],
            'static method' => [[$class, 'fixtureStaticMethod'], 'static method fixture result'],
            'class method'  => ["$class::fixtureStaticMethod", 'static method fixture result'],
            'function'      => ['php_sapi_name', 'cli'],
        ];
    }

    /**
     * @dataProvider callbackInvalidDataProvider
     * @expectedException \TypeError
     */
    public function testCallbackInvalid(callable $callback)
    {
        $subject = overload($callback);
        $subject();
    }

    public function callbackInvalidDataProvider()
    {
        $class = self::class;
        return [
            'object'        => [new \stdClass],
            'object method' => [[$this, 'missingMethod']],
            'static method' => [[$class, 'missingStaticMethod']],
            'class method'  => ["$class::missingStaticMethod"],
            'function'      => ['missing_function'],
        ];
    }

    /**
     * @expectedException \TypeError
     * @expectedExceptionMessage must be of the type integer, string returned
     */
    public function testCallbackExclusive()
    {
        $subject = overload(
            function (): int {
                return 'return type mismatch';
            },
            function () {
                // Unreachable because preceding callback is executed even though unsuccessfully
                throw new \LogicException('Unreachable implementation executed');
            }
        );
        $subject();
    }

    /**
     * @expectedException \LogicException
     */
    public function testCallbackNone()
    {
        $subject = overload();
        $subject();
    }
}
