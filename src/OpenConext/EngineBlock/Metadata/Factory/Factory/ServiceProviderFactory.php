<?php declare(strict_types=1);

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

namespace OpenConext\EngineBlock\Metadata\Factory\Factory;

use EngineBlock_Attributes_Metadata as AttributesMetadata;
use OpenConext\EngineBlock\Exception\MissingParameterException;
use OpenConext\EngineBlock\Metadata\Entity\ServiceProvider;
use OpenConext\EngineBlock\Metadata\Factory\Adapter\ServiceProviderEntity;
use OpenConext\EngineBlock\Metadata\Factory\Decorator\EngineBlockServiceProvider;
use OpenConext\EngineBlock\Metadata\Factory\Decorator\EngineBlockServiceProviderInformation;
use OpenConext\EngineBlock\Metadata\Factory\Decorator\ServiceProviderStepup;
use OpenConext\EngineBlock\Metadata\Factory\ServiceProviderEntityInterface;
use OpenConext\EngineBlock\Metadata\Factory\ValueObject\EngineBlockConfiguration;
use OpenConext\EngineBlock\Metadata\Mdui;
use OpenConext\EngineBlock\Metadata\X509\KeyPairFactory;
use OpenConext\EngineBlockBundle\Configuration\FeatureConfigurationInterface;
use OpenConext\EngineBlockBundle\Url\UrlProvider;

/**
 * This factory is used for instantiating an entity with the required adapters and/or decorators set.
 * It also makes sure that static, internally used, entities can be generated without the use of the database.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ServiceProviderFactory
{
    public function __construct(
        private AttributesMetadata $attributes,
        private KeyPairFactory $keyPairFactory,
        private EngineBlockConfiguration $engineBlockConfiguration,
        private UrlProvider $urlProvider,
        private FeatureConfigurationInterface $featureConfiguration,
        private string $entityIdOverrideValue
    ) {
    }

    public function createEngineBlockEntityFrom(string $keyId): ServiceProviderEntityInterface
    {
        $entityId = $this->urlProvider->getUrl('metadata_sp', false, null, null);

        $entity = $this->buildServiceProviderOrmEntity($entityId);

        return new EngineBlockServiceProvider( // Set EngineBlock specific functional properties so EB could act as proxy
            new EngineBlockServiceProviderInformation(  // Set EngineBlock specific presentation properties
                new ServiceProviderEntity($entity),
                $this->engineBlockConfiguration
            ),
            $this->keyPairFactory->buildFromIdentifier($keyId),
            $this->attributes,
            $this->urlProvider
        );
    }

    public function createStepupEntityFrom(string $keyId): ServiceProviderEntityInterface
    {
        $entityId = $this->urlProvider->getUrl('metadata_stepup', false, null, null);

        if ($this->featureConfiguration->hasFeature('eb.stepup.sfo.override_engine_entityid') 
            && $this->featureConfiguration->isEnabled('eb.stepup.sfo.override_engine_entityid')) {
            
            if (empty($this->entityIdOverrideValue)) {
                throw new MissingParameterException(
                    'When feature "feature_stepup_sfo_override_engine_entityid" is enabled, you must provide the '.
                    '"stepup.sfo.override_engine_entityid" parameter.'
                );
            }
            $entityId = $this->entityIdOverrideValue;
        }

        return new ServiceProviderStepup(
            new ServiceProviderEntity($this->buildServiceProviderOrmEntity($entityId)),
            $this->keyPairFactory->buildFromIdentifier($keyId),
            $this->urlProvider
        );
    }

    private function buildServiceProviderOrmEntity(
        string $entityId
    ): ServiceProvider {
        $entity = new ServiceProvider($entityId, Mdui::emptyMdui());
        return $entity;
    }
}
