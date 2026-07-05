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

namespace OpenConext\EngineBlock\Metadata\Entity\Assembler;

use DateTime;
use OpenConext\EngineBlock\Metadata\AttributeReleasePolicy;
use OpenConext\EngineBlock\Metadata\ConsentSettings;
use OpenConext\EngineBlock\Metadata\ContactPerson;
use OpenConext\EngineBlock\Metadata\EmptyMduiElement;
use OpenConext\EngineBlock\Metadata\Entity\IdentityProvider;
use OpenConext\EngineBlock\Metadata\Entity\ServiceProvider;
use OpenConext\EngineBlock\Metadata\Factory\MduiPushAssemblerFactory;
use OpenConext\EngineBlock\Metadata\IndexedService;
use OpenConext\EngineBlock\Metadata\Logo;
use OpenConext\EngineBlock\Metadata\Mdui;
use OpenConext\EngineBlock\Metadata\MduiElement;
use OpenConext\EngineBlock\Metadata\MfaEntityCollection;
use OpenConext\EngineBlock\Metadata\MultilingualElement;
use OpenConext\EngineBlock\Metadata\MultilingualValue;
use OpenConext\EngineBlock\Metadata\Organization;
use OpenConext\EngineBlock\Metadata\Service;
use OpenConext\EngineBlock\Metadata\ShibMdScope;
use OpenConext\EngineBlock\Metadata\StepupConnections;
use OpenConext\EngineBlock\Metadata\Discovery;
use OpenConext\EngineBlock\Metadata\Utils;
use OpenConext\EngineBlock\Metadata\X509\X509CertificateFactory;
use OpenConext\EngineBlock\Metadata\X509\X509CertificateLazyProxy;
use OpenConext\EngineBlock\Validator\ValidatorInterface;
use OpenConext\EngineBlockBundle\Localization\LanguageSupportProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;

use function array_key_exists;

/**
 * @SuppressWarnings(PMD)
 */
class PushMetadataAssembler implements MetadataAssemblerInterface
{
    /**
     * @var ValidatorInterface
     */
    private $allowedAcsLocationsValidator;

    /**
     * @var DiscoveryAssembler
     */
    private $discoveryAssembler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Maximum length that will fit in certain string type fields.
     */
    private const FIELDS_MAX_LENGTH = 255;

    public function __construct(
        ValidatorInterface $allowedAcsLocations,
        LanguageSupportProvider $languageSupportProvider,
        LoggerInterface $logger
    ) {
        $this->allowedAcsLocationsValidator = $allowedAcsLocations;
        $this->discoveryAssembler = new DiscoveryAssembler($languageSupportProvider);
        $this->logger = $logger;
    }

    public function assemble($connections)
    {
        $roles = array();
        $allIdpEntityIds = array();
        $spAllowedEntityIds = array();
        $idpAllowedEntityIds = array();

        foreach ($connections as $connection) {
            $role = $this->assembleConnection($connection);

            if ($role instanceof ServiceProvider) {
                $spAllowedEntityIds[$role->entityId] = $this->getAllowedEntityIds($connection);
            }

            if ($role instanceof IdentityProvider) {
                $allIdpEntityIds[] = $role->entityId;
                $idpAllowedEntityIds[$role->entityId] = $this->getAllowedEntityIds($connection);
            }

            $roles[] = $role;
        }

        // For all service providers
        foreach ($roles as $role) {
            if (!$role instanceof ServiceProvider) {
                continue;
            }

            // Get the IdPs that are allowed for this SP.
            $allowedIdpEntityIds = $spAllowedEntityIds[$role->entityId] ?? null;
            if ($allowedIdpEntityIds === true) {
                $allowedIdpEntityIds = $allIdpEntityIds;
            }

            // Filter out IdPs that don't allow this SP
            $allowedIdpEntityIds = $this->filterAllowedIdpEntityIds($allowedIdpEntityIds, $idpAllowedEntityIds, $role->entityId);

            if ($allowedIdpEntityIds === $allIdpEntityIds) {
                // If a blacklist was configured, and no IDPs were explicitly
                // blacklisted, then don't keep track of all entity IDs, but
                // remember that all IDPs are allowed.
                $role->allowAll = true;
            } else {
                $role->allowedIdpEntityIds = $allowedIdpEntityIds;
            }
        }

        if (count($roles) === 0) {
            throw new RuntimeException('Received 0 connections, refusing to process');
        }

        return $roles;
    }

    /**
     * Extract allowed entity IDs from connection configuration.
     * 
     * @param stdClass $connection
     * @return array|bool Array of entity IDs or true if all entities are allowed
     */
    private function getAllowedEntityIds($connection)
    {
        if (isset($connection->allow_all_entities) && $connection->allow_all_entities) {
            return true;
        }

        if (isset($connection->allowed_connections)) {
            return array_map(
                function ($allowedConnection) {
                    return $allowedConnection->name;
                },
                $connection->allowed_connections
            );
        }

        return array();
    }

    /**
     * Filter allowed IDP entity IDs based on IDP restrictions.
     * 
     * @param array|null $allowedIdpEntityIds
     * @param array $idpAllowedEntityIds
     * @param string $spEntityId
     * @return array
     */
    private function filterAllowedIdpEntityIds($allowedIdpEntityIds, $idpAllowedEntityIds, $spEntityId)
    {
        if (empty($allowedIdpEntityIds) || !is_array($allowedIdpEntityIds)) {
            return array();
        }

        foreach ($idpAllowedEntityIds as $idpEntityId => $allowedSpEntityIds) {
            if ($allowedSpEntityIds === true) {
                continue;
            }

            if (in_array($spEntityId, $allowedSpEntityIds)) {
                continue;
            }

            // Remove IDP from allowed list if it doesn't allow this SP
            $key = array_search($idpEntityId, $allowedIdpEntityIds, true);
            if ($key !== false) {
                unset($allowedIdpEntityIds[$key]);
            }
        }

        return array_values($allowedIdpEntityIds);
    }

    /**
     * Shorten possibly lengthy fields that need to fit into a varchar(255).
     * If they are longer than this, we likely do not use the contents for
     * anything useful so it's OK to just chop it off. Otherwise the assembler
     * will get errors when using MySQL with strict mode enabled.
     */
    private function limitValueLength(string $value): string
    {
        if (strlen($value) < self::FIELDS_MAX_LENGTH) {
            return $value;
        }
        $this->logger->info(sprintf("Push Metadata Assembler: truncating too long value: '%s'", $value));
        return mb_strcut($value, 0, self::FIELDS_MAX_LENGTH);
    }

    /**
     * @param stdClass $connection
     * @return IdentityProvider|ServiceProvider
     */
    private function assembleConnection(stdClass $connection)
    {
        if ($connection->type === 'saml20-sp') {
            return $this->assembleSp($connection);
        }

        if ($connection->type === 'saml20-idp') {
            return $this->assembleIdp($connection);
        }

        throw new RuntimeException(
            sprintf('Unrecognized type: "%s" "%s"', $connection->type, var_export($connection, true))
        );
    }

    /**
     * @param stdClass $connection
     * @return ServiceProvider
     */
    private function assembleSp(stdClass $connection)
    {
        $properties = $this->assembleCommon($connection);

        // Merge larger component arrays
        $properties = array_merge(
            $properties,
            $this->assembleAttributeReleasePolicy($connection),
            $this->assembleAssertionConsumerServices($connection),
            $this->assembleIsConsentRequired($connection)
        );

        // Set boolean properties directly
        $properties['isTransparentIssuer'] = $this->getBooleanValue($connection, 'metadata:coin:transparant_issuer');
        $properties['isTrustedProxy'] = $this->getBooleanValue($connection, 'metadata:coin:trusted_proxy');
        $properties['displayUnconnectedIdpsWayf'] = $this->getBooleanValue($connection, 'metadata:coin:display_unconnected_idps_wayf');
        $properties['skipDenormalization'] = $this->getBooleanValue($connection, 'metadata:coin:do_not_add_attribute_aliases');
        $properties['policyEnforcementDecisionRequired'] = $this->getBooleanValue($connection, 'metadata:coin:policy_enforcement_decision_required');
        $properties['requesteridRequired'] = $this->getBooleanValue($connection, 'metadata:coin:requesterid_required');
        $properties['signResponse'] = $this->getBooleanValue($connection, 'metadata:coin:sign_response');
        $properties['stepupAllowNoToken'] = $this->getBooleanValue($connection, 'metadata:coin:stepup:allow_no_token');
        $properties['stepupForceAuthn'] = $this->getBooleanValue($connection, 'metadata:coin:stepup:forceauthn');
        $properties['collabEnabled'] = $this->getBooleanValue($connection, 'metadata:coin:collab_enabled');

        // Set string properties directly
        $properties['termsOfServiceUrl'] = $this->getStringValue($connection, 'metadata:coin:eula');
        $properties['stepupRequireLoa'] = $this->getStringValue($connection, 'metadata:coin:stepup:requireloa');

        return Utils::instantiate(
            ServiceProvider::class,
            $properties
        );
    }

    /**
     * @param stdClass $connection
     * @return IdentityProvider
     */
    private function assembleIdp(stdClass $connection)
    {
        $properties = $this->assembleCommon($connection);

        // Merge all properties in a single operation for better performance
        $properties = array_merge(
            $properties,
            $this->assembleSingleSignOnServices($connection),
            $this->setPathFromObjectString(array($connection, 'metadata:coin:guest_qualifier'), 'guestQualifier'),
            $this->setPathFromObjectString(array($connection, 'metadata:coin:schachomeorganization'), 'schacHomeOrganization'),
            $this->assembleConsentSettings($connection),
            $this->setPathFromObjectBool(array($connection, 'metadata:coin:hidden'), 'hidden'),
            $this->assembleShibMdScopes($connection),
            $this->assembleStepupConnections($connection),
            $this->assembleMfaEntities($connection),
            $this->discoveryAssembler->assembleDiscoveries($connection),
            $this->setPathFromObjectString(array($connection, 'metadata:coin:defaultRAC'), 'defaultRAC'),
            $this->setPathFromObjectBool(
                [$connection, 'metadata:coin:policy_enforcement_decision_required'],
                'policyEnforcementDecisionRequired'
            )
        );

        return Utils::instantiate(
            IdentityProvider::class,
            $properties
        );
    }

    private function assembleCommon(stdClass $connection)
    {
        // Use array_merge for better performance instead of multiple += operations
        $properties = array_merge(
            $this->setPathFromObjectString(array($connection, 'name'), 'entityId'),
            $this->setPathFromObjectString(array($connection, 'metadata:name:nl'), 'nameNl'),
            $this->setPathFromObjectString(array($connection, 'metadata:name:en'), 'nameEn'),
            $this->setPathFromObjectString(array($connection, 'metadata:name:pt'), 'namePt'),
            $this->setPathFromObjectString(array($connection, 'metadata:displayName:nl'), 'displayNameNl'),
            $this->setPathFromObjectString(array($connection, 'metadata:displayName:en'), 'displayNameEn'),
            $this->setPathFromObjectString(array($connection, 'metadata:displayName:pt'), 'displayNamePt'),
            $this->setPathFromObjectString(array($connection, 'metadata:description:nl'), 'descriptionNl', true),
            $this->setPathFromObjectString(array($connection, 'metadata:description:en'), 'descriptionEn', true),
            $this->setPathFromObjectString(array($connection, 'metadata:description:pt'), 'descriptionPt', true),
            $this->assembleLogo($connection),
            $this->assembleOrganization($connection, 'nl'),
            $this->assembleOrganization($connection, 'en'),
            $this->assembleOrganization($connection, 'pt'),
            $this->setPathFromObjectString(array($connection, 'metadata:keywords:en'), 'keywordsEn', true),
            $this->setPathFromObjectString(array($connection, 'metadata:keywords:nl'), 'keywordsNl', true),
            $this->setPathFromObjectString(array($connection, 'metadata:keywords:pt'), 'keywordsPt', true),
            $this->assembleCertificates($connection),
            $this->setPathFromObjectString(array($connection, 'state'), 'workflowState'),
            $this->assembleContactPersons($connection),
            $this->setPathFromObjectString(array($connection, 'metadata:NameIDFormat'), 'nameIdFormat'),
            $this->setPathFromObjectArray(array($connection, 'metadata:NameIDFormats'), 'supportedNameIdFormats'),
            $this->assembleSingleLogoutServices($connection),
            $this->setPathFromObjectBool(array($connection, 'metadata:coin:disable_scoping'), 'disableScoping'),
            $this->setPathFromObjectBool(array($connection, 'metadata:coin:additional_logging'), 'additionalLogging'),
            $this->setPathFromObjectString(array($connection, 'metadata:coin:signature_method'), 'signatureMethod'),
            $this->setPathFromObjectBool(array($connection, 'metadata:redirect:sign'), 'requestsMustBeSigned'),
            $this->setPathFromObjectString(array($connection, 'manipulation_code'), 'manipulation'),
            $this->setPathFromObjectString(array($connection, 'metadata:url:en'), 'supportUrlEn'),
            $this->setPathFromObjectString(array($connection, 'metadata:url:nl'), 'supportUrlNl'),
            $this->setPathFromObjectString(array($connection, 'metadata:url:pt'), 'supportUrlPt')
        );

        $properties['mdui'] = MduiPushAssemblerFactory::buildFrom($properties, $connection);

        return $properties;
    }

    private function assembleLogo(stdClass $connection)
    {
        if (empty($connection->metadata->logo[0]->url)) {
            return array();
        }

        $assembled = new Logo($connection->metadata->logo[0]->url);
        if (!empty($connection->metadata->logo[0]->width)) {
            $assembled->width = $connection->metadata->logo[0]->width;
        }
        if (!empty($connection->metadata->logo[0]->height)) {
            $assembled->height = $connection->metadata->logo[0]->height;
        }
        return array('logo' => $assembled);
    }

    private function assembleOrganization(stdClass $connection, $langCode)
    {
        $name = $connection->metadata->OrganizationName->$langCode ?? null;
        $displayName = $connection->metadata->OrganizationDisplayName->$langCode ?? null;
        $url = $connection->metadata->OrganizationURL->$langCode ?? null;
        
        return array('organization' . ucfirst($langCode) => new Organization($name, $displayName, $url));
    }

    private function assembleCertificates(stdClass $connection)
    {
        $certificateFactory = new X509CertificateFactory();
        $certificates = array();

        // Process certificate data in a loop to reduce code duplication
        for ($i = 1; $i <= 3; $i++) {
            $certDataProperty = 'certData' . ($i > 1 ? $i : '');
            
            if (!empty($connection->metadata->$certDataProperty)) {
                $certificates[] = new X509CertificateLazyProxy($certificateFactory, $connection->metadata->$certDataProperty);
            } else {
                // Stop processing if we encounter an empty certificate field
                break;
            }
        }

        return empty($certificates) ? array() : array('certificates' => $certificates);
    }

    private function assembleContactPersons($connection)
    {
        $contactPersons = array();
        
        // Early exit if no contacts exist
        if (empty($connection->metadata->contacts)) {
            return array();
        }
        
        // Process contacts in a single loop with early termination
        foreach ($connection->metadata->contacts as $contactMetadata) {
            // Skip if contactType is not set
            if (empty($contactMetadata->contactType)) {
                continue;
            }
            
            $contactPerson = new ContactPerson($contactMetadata->contactType);
            
            // Set properties only if they exist and are not empty
            if (!empty($contactMetadata->emailAddress)) {
                $contactPerson->emailAddress = $contactMetadata->emailAddress;
            }
            if (!empty($contactMetadata->telephoneNumber)) {
                $contactPerson->telephoneNumber = $contactMetadata->telephoneNumber;
            }
            if (!empty($contactMetadata->givenName)) {
                $contactPerson->givenName = $contactMetadata->givenName;
            }
            if (!empty($contactMetadata->surName)) {
                $contactPerson->surName = $contactMetadata->surName;
            }
            
            $contactPersons[] = $contactPerson;
        }
        
        return empty($contactPersons) ? array() : array('contactPersons' => $contactPersons);
    }

    private function assembleSingleLogoutServices($connection)
    {
        if (empty($connection->metadata->SingleLogoutService[0]->Location)) {
            return array();
        }
        $serviceMetadata = $connection->metadata->SingleLogoutService[0];
        return array('singleLogoutService' => new Service(
            $serviceMetadata->Location,
            $serviceMetadata->Binding
        ));
    }

    private function setPathFromObjectString(array $from, string $to, bool $limitlength = false): array
    {
        $reference = $this->getValueFromPath($from);
        if (is_null($reference)) {
            return array($to => null);
        }
        if ($limitlength) {
            $reference = $this->limitValueLength($reference);
        }

        return array($to => (string)$reference);
    }

    private function setPathFromObjectArray(array $from, string $to): array
    {
        $reference = $this->getValueFromPath($from);
        if (!array($reference)) {
            return array();
        }
        return array($to => $reference);
    }

    private function setPathFromObjectBool(array $from, string $to): array
    {
        $reference = $this->getValueFromPath($from);
        if (is_null($reference)) {
            return array($to => null);
        }
        return array($to => (bool)$reference);
    }

    private function getValueFromPath(array $from)
    {
        $pathParts = explode(':', $from[1]);

        $reference = $from[0];
        while ($pathPart = array_shift($pathParts)) {
            if (!isset($reference->$pathPart)) {
                return null;
            }

            $reference = $reference->$pathPart;
        }

        return $reference;
    }

    private function getBooleanValue(stdClass $connection, string $path): ?bool
    {
        $value = $this->getValueFromPath([$connection, $path]);
        return $value === null ? null : (bool)$value;
    }

    private function getStringValue(stdClass $connection, string $path): ?string
    {
        $value = $this->getValueFromPath([$connection, $path]);
        return $value === null ? null : (string)$value;
    }

    private function assembleSingleSignOnServices($connection)
    {
        if (empty($connection->metadata->SingleSignOnService)) {
            return array();
        }

        $services = array();
        foreach ($connection->metadata->SingleSignOnService as $singleSignOnServiceMetadata) {
            // Skip if required fields are missing
            if (empty($singleSignOnServiceMetadata->Location) || empty($singleSignOnServiceMetadata->Binding)) {
                continue;
            }

            $services[] = new Service($singleSignOnServiceMetadata->Location, $singleSignOnServiceMetadata->Binding);
        }
        
        return empty($services) ? array() : array('singleSignOnServices' => $services);
    }

    private function assembleConsentSettings(stdClass $connection)
    {
        if (empty($connection->disable_consent_connections)) {
            return array();
        }

        return array(
            'consentSettings' => new ConsentSettings(
                (array)$connection->disable_consent_connections
            ),
        );
    }

    private function assembleShibMdScopes($connection)
    {
        if (empty($connection->metadata->shibmd->scope)) {
            return [];
        }

        $scopes = array_filter($connection->metadata->shibmd->scope, function ($scopeMetadata) {
            return !empty($scopeMetadata->allowed);
        });

        $shibMdScopes = array_map(function ($scopeMetadata) {
            $scope = new ShibMdScope();
            $scope->allowed = $scopeMetadata->allowed;
            if (!empty($scopeMetadata->regexp)) {
                $scope->regexp = $scopeMetadata->regexp;
            }
            return $scope;
        }, $scopes);

        return empty($shibMdScopes) ? [] : ['shibMdScopes' => $shibMdScopes];
    }

    private function assembleStepupConnections(stdClass $connection): array
    {
        if (empty($connection->stepup_connections)) {
            return [];
        }

        // Filter and map connections in a single pass for efficiency
        $connections = [];
        foreach ($connection->stepup_connections as $sp) {
            if (isset($sp->name, $sp->level)) {
                $connections[(string)$sp->name] = (string)$sp->level;
            }
        }

        // Early return if no valid connections found
        if (empty($connections)) {
            return [];
        }

        return [
            'stepupConnections' => new StepupConnections($connections),
        ];
    }

    private function assembleAssertionConsumerServices(stdClass $connection)
    {
        if (empty($connection->metadata->AssertionConsumerService)) {
            return array();
        }

        $services = array();
        $index = 0;
        
        foreach ($connection->metadata->AssertionConsumerService as $assertionConsumerServiceMetadata) {
            // Skip if required Location field is missing
            if (empty($assertionConsumerServiceMetadata->Location)) {
                continue;
            }

            // Skip if required Binding field is missing
            if (empty($assertionConsumerServiceMetadata->Binding)) {
                continue;
            }

            // Validate URI scheme for security
            if (!$this->allowedAcsLocationsValidator->validate($assertionConsumerServiceMetadata->Location)) {
                throw new RuntimeException('The acs metadata contained an invalid location uri scheme');
            }

            // Use provided Index if available, otherwise use auto-incremented index
            $currentIndex = !empty($assertionConsumerServiceMetadata->Index) 
                ? (int) $assertionConsumerServiceMetadata->Index 
                : $index;

            $services[] = new IndexedService(
                $assertionConsumerServiceMetadata->Location,
                $assertionConsumerServiceMetadata->Binding,
                $currentIndex
            );

            $index = $currentIndex + 1;
        }
        
        return array('assertionConsumerServices' => $services);
    }

    private function assembleIsConsentRequired(stdClass $connection)
    {
        if (empty($connection->metadata->coin->no_consent_required)) {
            return array();
        }

        return array( 'isConsentRequired' => !$connection->metadata->coin->no_consent_required );
    }

    private function assembleAttributeReleasePolicy(stdClass $connection)
    {
        if (empty($connection->arp_attributes)) {
            return array();
        }

        // EngineBlock expects objects in the metadata in many places so we
        // can't decode the metadata with assoc=true. ARP rules should always
        // be arrays so we explicitly cast the ARP rules to arrays here.
        $processedAttributes = array_map(function ($rules) {
            return array_map(function ($rule) {
                return is_object($rule) ? (array) $rule : $rule;
            }, $rules);
        }, $connection->arp_attributes);

        return array(
            'attributeReleasePolicy' => new AttributeReleasePolicy($processedAttributes)
        );
    }

    private function assembleMfaEntities(stdClass $connection): array
    {
        if (!isset($connection->mfa_entities) || empty($connection->mfa_entities)) {
            return [];
        }

        // Use array_map for more efficient processing
        $entities = array_map(function($sp) {
            return [
                'name' => (string)$sp->name,
                'level' => (string)$sp->level,
            ];
        }, $connection->mfa_entities);

        // Early return if no entities were processed
        if (empty($entities)) {
            return [];
        }

        return [
            'mfaEntities' => MfaEntityCollection::fromMetadataPush($entities)
        ];
    }
}
