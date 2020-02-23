<?php
/**
 * Copyright © Upscale Software. All rights reserved.
 * See COPYRIGHT.txt for license details.
 */
declare(strict_types=1);

namespace Upscale\Stdlib\Overloading;

use Exception as Stack;

/**
 * Return closure delegating calls to the first compatible implementation
 *
 * @param callable[] $implementations
 * @return callable
 * @throws \InvalidArgumentException
 */
function overload(callable ...$implementations): callable
{
    if (!$implementations) {
        throw new \InvalidArgumentException('Missing overload declaration.');
    }
    return function (...$args) use ($implementations) {
        $stack = new Stack();
        $error = null;
        foreach ($implementations as $candidate) {
            try {
                return $candidate(...$args);
            } catch (\TypeError $e) {
                $error = $e;
                $isThrownDirectly = (array_slice($error->getTrace(), 1) === $stack->getTrace());
                $isReturnTypeError = (strncmp($error->getMessage(), 'Return value of ', 16) === 0);
                if (!$isThrownDirectly || $isReturnTypeError) {
                    break;
                }
            }
        }
        throw $error;
    };
}
