<?php
/**
 * Copyright © Upscale Software. All rights reserved.
 * See COPYRIGHT.txt for license details.
 */
declare(strict_types=1);

namespace Upscale\Stdlib\Overloading;

/**
 * Return closure delegating calls to the first compatible implementation
 *
 * @param callable[] $implementations
 * @return callable
 * @throws \LogicException|\Error
 */
function overload(callable ...$implementations): callable
{
    return function (...$args) use ($implementations) {
        $error = new \LogicException('Invalid overloaded implementations');
        foreach ($implementations as $candidate) {
            try {
                return $candidate(...$args);
            } catch (\TypeError $e) {
                $error = $e;
                if (strncmp($error->getMessage(), 'Return value of ', 16) === 0) {
                    break;
                }
            }
        }
        throw $error;
    };
}
