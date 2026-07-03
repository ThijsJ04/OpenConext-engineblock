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

namespace OpenConext\EngineBlockBundle\Configuration;

use OpenConext\EngineBlock\Assert\Assertion;
use OpenConext\EngineBlock\Exception\LogicException;

/**
 * By default the feature configuration enables the features, they can be reset/disabled
 * by calling the setFeature method.
 */
class TestFeatureConfiguration implements FeatureConfigurationInterface
{
    /**
     * @var Feature[]
     */
    private $features = [];

public function __construct()
{
    $this->features = [
        'api.deprovision' => new Feature('api.deprovision', true),
        'api.metadata_push' => new Feature('api.metadata_push', true),
        'api.consent_listing' => new Feature('api.consent_listing', true),
        'api.consent_remove' => new Feature('api.consent_remove', true),
        'eb.run_all_manipulations_prior_to_consent' => new Feature('eb.run_all_manipulations_prior_to_consent', false),
        'eb.block_user_on_violation' => new Feature('eb.block_user_on_violation', true),
        'eb.encrypted_assertions' => new Feature('eb.encrypted_assertions', true),
        'eb.encrypted_assertions_require_outer_signature' => new Feature('eb.encrypted_assertions_require_outer_signature', true),
        'eb.enable_sso_notification' => new Feature('eb.enable_sso_notification', false),
        'eb.feature_enable_consent' => new Feature('eb.feature_enable_consent', true),
        'eb.enable_sso_session_cookie' => new Feature('eb.enable_sso_session_cookie', true),
        'eb.stepup.sfo.override_engine_entityid' => new Feature('eb.stepup.sfo.override_engine_entityid', false),
        'eb.feature_enable_idp_initiated_flow' => new Feature('eb.feature_enable_idp_initiated_flow', true),
        'eb.stepup.send_user_attributes' => new Feature('eb.stepup.send_user_attributes', true),
    ];
}

    public function setFeature(Feature $feature): void
    {
        $this->features[$feature->getFeatureKey()] = $feature;
    }

    public function hasFeature($featureKey)
    {
        Assertion::nonEmptyString($featureKey, 'featureKey');

        return array_key_exists($featureKey, $this->features);
    }

public function isEnabled($featureKey)
{
    Assertion::nonEmptyString($featureKey, 'featureKey');

    if (!isset($this->features[$featureKey])) {
        $features = implode(
            ', ',
            array_keys($this->features)
        );
        throw new LogicException(
            sprintf(
                'Cannot state if feature "%s" is enabled as it does not exist. Please ensure that you configured it '
                .'correctly or verify with hasFeature() that the feature exists. Features configured: "%s"',
                $featureKey,
                $features
            )
        );
    }

    return $this->features[$featureKey]->isEnabled();
}
}
