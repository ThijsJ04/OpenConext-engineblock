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

namespace OpenConext\EngineBlock\Logger;

use DateTime;
use OpenConext\EngineBlock\Authentication\Value\CollabPersonId;
use OpenConext\EngineBlock\Authentication\Value\KeyId;
use OpenConext\Value\Saml\Entity;
use Psr\Log\LoggerInterface;

class AuthenticationLogger
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Logs a granted login with comprehensive authentication details.
     *
     * KeyId is nullable to differentiate between no specific key, default key, and specific key.
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @param Entity $serviceProvider The service provider entity
     * @param Entity $identityProvider The identity provider entity
     * @param CollabPersonId $collabPersonId The collaborator person ID
     * @param Entity[] $proxiedServiceProviders Array of proxied service provider entities
     * @param string $workflowState The workflow state
     * @param string $originalNameId The original name ID
     * @param string|null $authnContextClassRef The authentication context class reference
     * @param string|null $engineSsoEndpointUsed The engine SSO endpoint used
     * @param array|null $requestedIdPlist The requested identity provider list
     * @param KeyId|null $keyId The key ID (nullable)
     * @param array $logAttributes Additional log attributes
     */
    public function logGrantedLogin(
        Entity $serviceProvider,
        Entity $identityProvider,
        CollabPersonId $collabPersonId,
        array $proxiedServiceProviders,
        string $workflowState,
        string $originalNameId,
        ?string $authnContextClassRef,
        ?string $engineSsoEndpointUsed,
        ?array $requestedIdPlist,
        KeyId $keyId = null,
        array $logAttributes = []
    ) {
        // Extract entity IDs from proxied service providers using foreach for better performance
        $proxiedServiceProviderEntityIds = [];
        foreach ($proxiedServiceProviders as $entity) {
            $proxiedServiceProviderEntityIds[] = $entity->getEntityId()->getEntityId();
        }

        $timestamp = $this->generateTimestamp();

        // Build log data array with all required information
        $logData = [
            'login_stamp' => $timestamp,
            'user_id' => $collabPersonId->getCollabPersonId(),
            'sp_entity_id' => $serviceProvider->getEntityId()->getEntityId(),
            'idp_entity_id' => $identityProvider->getEntityId()->getEntityId(),
            'key_id' => $keyId ? $keyId->getKeyId() : null,
            'proxied_sp_entity_ids' => $proxiedServiceProviderEntityIds,
            'workflow_state' => $workflowState,
            'original_name_id' => $originalNameId,
            'authncontextclassref' => $authnContextClassRef,
            'requestedidps' => $requestedIdPlist,
            'engine_sso_endpoint_used' => $engineSsoEndpointUsed
        ];

        // Add log attributes if provided
        if ($logAttributes) {
            $logData['response_attributes'] = $logAttributes;
        }

        $this->logger->info('login granted', $logData);
    }

    /**
     * Generates a timestamp that is equal to the RFC3339_EXTENDED format
     *
     * This format is introduced in PHP7, as PHP5 does not support this kind of precision.
     * This method fakes the PHP7 behaviour by adding the microseconds manually.
     *
     * One day when the PHP5 dependency is lost, we can simply use RFC3339_EXTENDED
     *
     * @return string
     */
    private function generateTimestamp()
    {
        return (new DateTime())->format('Y-m-d\TH:i:s.uP');
    }
}
