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

namespace OpenConext\EngineBlock\Logger\Handler\FingersCrossed;

use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use OpenConext\EngineBlock\Assert\Assertion;
use OpenConext\EngineBlock\Exception\InvalidArgumentException;
use Psr\Log\LogLevel;

final class ManualOrErrorLevelActivationStrategyFactory implements ActivationStrategyFactory
{
    /**
     * @param array $config
     * @return ManualOrDecoratedActivationStrategy
     * @throws InvalidArgumentException
     */
    public static function createActivationStrategy(array $config)
    {
        $config = self::validateAndNormalizeConfig($config);

        return new ManualOrDecoratedActivationStrategy(
            new ErrorLevelActivationStrategy($config['action_level'])
        );
    }

    /**
     * @param array $config
     * @return array
     * @throws InvalidArgumentException
     */
    private static function validateAndNormalizeConfig(array $config)
    {
        // Validate required key exists
        Assertion::keyIsset($config, 'action_level', 'Missing configuration value, configuration key "%s" not found');
        
        // Validate it's a string
        Assertion::string($config['action_level']);

        // Normalize to lowercase for case-insensitive comparison
        $normalizedLevel = strtolower($config['action_level']);

        // Validate against allowed log levels
        Assertion::choice(
            $normalizedLevel,
            [
                LogLevel::EMERGENCY,
                LogLevel::ALERT,
                LogLevel::CRITICAL,
                LogLevel::ERROR,
                LogLevel::WARNING,
                LogLevel::NOTICE,
                LogLevel::INFO,
                LogLevel::DEBUG,
            ],
            'Configured action level must be a valid PSR-compliant log level: "%s"'
        );

        // Return optimized config with normalized level
        return ['action_level' => $normalizedLevel];
    }
}
