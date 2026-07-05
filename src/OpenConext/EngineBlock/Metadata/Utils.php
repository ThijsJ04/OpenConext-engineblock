<?php

/**
 * Copyright 2010 SURFnet B.V.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace OpenConext\EngineBlock\Metadata;

use ReflectionClass;

/**
 * The dreaded 'Utils' class with static helper methods.
 * @package OpenConext\EngineBlock\Metadata
 */
class Utils
{
    /**
     * Returns the value if it exists or a given default value.
     *
     * @see https://wiki.php.net/rfc/ifsetor
     *
     * @param array $value
     * @param string $property
     * @param mixed $default
     * @return mixed
     */
    public static function ifsetor(array $value, $property, $default = null)
    {
        return isset($value[$property]) ? $value[$property] : $default;
    }

    /**
     * Instantiate a class with named arguments.
     *
     * An answer to a problem that should not exist (legacy large Entities).
     *
     * @param string $className
     * @param array $namedArguments
     * @return object
     */
    public static function instantiate($className, array $namedArguments)
    {
        // Early return for empty arguments - use default constructor
        if (empty($namedArguments)) {
            return new $className();
        }

        $reflectionClass = new ReflectionClass($className);
        $parameters = $reflectionClass->getConstructor()->getParameters();

        // Early return if no constructor parameters exist
        if (empty($parameters)) {
            return $reflectionClass->newInstance();
        }

        // Pre-allocate array with correct size for better performance
        $positionalDefaultFilledArguments = array();
        $hasMissingArguments = false;

        foreach ($parameters as $parameter) {
            if (isset($namedArguments[$parameter->name])) {
                $positionalDefaultFilledArguments[] = $namedArguments[$parameter->name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $positionalDefaultFilledArguments[] = $parameter->getDefaultValue();
                $hasMissingArguments = true;
            } else {
                // Required parameter not provided - this will cause an error
                $positionalDefaultFilledArguments[] = null;
            }
        }

        // If all arguments were provided, we could potentially use named arguments
        // but for backward compatibility, we'll stick with positional
        return $reflectionClass->newInstanceArgs($positionalDefaultFilledArguments);
    }
}
