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
    $roles = [];
    $allIdpEntityIds = [];
    $spAllowedEntityIds = [];
    $idpAllowedEntityIds = [];

    foreach ($connections as $connection) {
        $role = $this->assembleConnection($connection);

        if ($role instanceof ServiceProvider) {
            if (isset($connection->allowed_connections)) {
                $spAllowedEntityIds[$role->entityId] = array_map(
                    function ($allowedConnection) {
                        return $allowedConnection->name;
                    },
                    $connection->allowed_connections
                );
            } elseif (isset($connection->allow_all_entities) && $connection->allow_all_entities) {
                $spAllowedEntityIds[$role->entityId] = true;
            }
        }

        if ($role instanceof IdentityProvider) {
            $allIdpEntityIds[] = $role->entityId;

            if (isset($connection->allowed_connections)) {
                $idpAllowedEntityIds[$role->entityId] = array_map(
                    function ($allowedConnection) {
                        return $allowedConnection->name;
                    },
                    $connection->allowed_connections
                );
            } elseif (isset($connection->allow_all_entities) && $connection->allow_all_entities) {
                $idpAllowedEntityIds[$role->entityId] = true;
            }
        }

        $roles[] = $role;
    }

    if (empty($roles)) {
        throw new RuntimeException('Received 0 connections, refusing to process');
    }

    foreach ($roles as $role) {
        if (!$role instanceof ServiceProvider) {
            continue;
        }

        $allowedIdpEntityIds = $spAllowedEntityIds[$role->entityId] ?? null;

        if ($allowedIdpEntityIds === true) {
            $allowedIdpEntityIds = $allIdpEntityIds;
        }

        if ($allowedIdpEntityIds !== null) {
            foreach ($idpAllowedEntityIds as $idpEntityId => $allowedSpEntityIds) {
                if ($allowedSpEntityIds === true || in_array($role->entityId, $allowedSpEntityIds, true)) {
                    continue;
                }

                $index = array_search($idpEntityId, $allowedIdpEntityIds, true);
                if ($index !== false) {
                    unset($allowedIdpEntityIds[$index]);
                }
            }

            if ($allowedIdpEntityIds === $allIdpEntityIds) {
                $role->allowAll = true;
            } else {
                $role->allowedIdpEntityIds = array_values($allowedIdpEntityIds);
            }
        }
    }

    return $roles;
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

    $properties += $this->assembleAttributeReleasePolicy($connection);
    $properties += $this->assembleAssertionConsumerServices($connection);
    $properties += $this->setPathFromObjectBool([$connection, 'metadata:coin:transparant_issuer'], 'isTransparentIssuer');
    $properties += $this->setPathFromObjectBool([$connection, 'metadata:coin:trusted_proxy'], 'isTrustedProxy');
    $properties += $this->setPathFromObjectBool([$connection, 'metadata:coin:display_unconnected_idps_wayf'], 'displayUnconnectedIdpsWayf');

    $properties += $this->assembleIsConsentRequired($connection);

    $properties += $this->setPathFromObjectString([$connection, 'metadata:coin:eula'], 'termsOfServiceUrl');
    $properties += $this->setPathFromObjectBool([$connection, 'metadata:coin:do_not_add_attribute_aliases'], 'skipDenormalization');
    $properties += $this->setPathFromObjectBool(
        [$connection, 'metadata:coin:policy_enforcement_decision_required'],
        'policyEnforcementDecisionRequired'
    );
    $properties += $this->setPathFromObjectBool([$connection, 'metadata:coin:requesterid_required'], 'requesteridRequired');
    $properties += $this->setPathFromObjectBool([$connection, 'metadata:coin:sign_response'], 'signResponse');
    $properties += $this->setPathFromObjectString([$connection, 'metadata:coin:stepup:requireloa'], 'stepupRequireLoa');
    $properties += $this->setPathFromObjectBool([$connection, 'metadata:coin:stepup:allow_no_token'], 'stepupAllowNoToken');
    $properties += $this->setPathFromObjectBool([$connection, 'metadata:coin:stepup:forceauthn'], 'stepupForceAuthn');

    $properties += $this->setPathFromObjectBool([$connection, 'metadata:coin:collab_enabled'], 'collabEnabled');

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

    $properties += $this->assembleSingleSignOnServices($connection);
    $properties += $this->setPathFromObjectString(array($connection, 'metadata:coin:guest_qualifier'), 'guestQualifier');
    $properties += $this->setPathFromObjectString(array($connection, 'metadata:coin:schachomeorganization'), 'schacHomeOrganization');
    $properties += $this->assembleConsentSettings($connection);
    $properties += $this->setPathFromObjectBool(array($connection, 'metadata:coin:hidden'), 'hidden');
    $properties += $this->assembleShibMdScopes($connection);

    $properties += $this->assembleStepupConnections($connection);
    $properties += $this->assembleMfaEntities($connection);

    $properties += $this->discoveryAssembler->assembleDiscoveries($connection);
    $properties += $this->setPathFromObjectString(array($connection, 'metadata:coin:defaultRAC'), 'defaultRAC');

    $properties += $this->setPathFromObjectBool(
        [$connection, 'metadata:coin:policy_enforcement_decision_required'],
        'policyEnforcementDecisionRequired'
    );

    return Utils::instantiate(
        IdentityProvider::class,
        $properties
    );
}

private function assembleCommon(stdClass $connection)
{
    $properties = [];

    // Process all string properties in a single loop to reduce function call overhead
    $stringProperties = [
        ['name', 'entityId'],
        ['metadata:name:nl', 'nameNl'],
        ['metadata:name:en', 'nameEn'],
        ['metadata:name:pt', 'namePt'],
        ['metadata:displayName:nl', 'displayNameNl'],
        ['metadata:displayName:en', 'displayNameEn'],
        ['metadata:displayName:pt', 'displayNamePt'],
        ['metadata:description:nl', 'descriptionNl', true],
        ['metadata:description:en', 'descriptionEn', true],
        ['metadata:description:pt', 'descriptionPt', true],
        ['metadata:keywords:en', 'keywordsEn', true],
        ['metadata:keywords:nl', 'keywordsNl', true],
        ['metadata:keywords:pt', 'keywordsPt', true],
        ['metadata:NameIDFormat', 'nameIdFormat'],
        ['metadata:coin:guest_qualifier', 'guestQualifier'],
        ['metadata:coin:schachomeorganization', 'schacHomeOrganization'],
        ['metadata:coin:eula', 'termsOfServiceUrl'],
        ['metadata:coin:stepup:requireloa', 'stepupRequireLoa'],
        ['metadata:coin:defaultRAC', 'defaultRAC'],
        ['metadata:coin:signature_method', 'signatureMethod'],
        ['metadata:url:en', 'supportUrlEn'],
        ['metadata:url:nl', 'supportUrlNl'],
        ['metadata:url:pt', 'supportUrlPt'],
        ['manipulation_code', 'manipulation'],
        ['state', 'workflowState'],
    ];

    foreach ($stringProperties as $property) {
        $from = [$connection, $property[0]];
        $to = $property[1];
        $limitLength = $property[2] ?? false;
        $properties += $this->setPathFromObjectString($from, $to, $limitLength);
    }

    // Process all boolean properties in a single loop
    $boolProperties = [
        ['metadata:coin:transparant_issuer', 'isTransparentIssuer'],
        ['metadata:coin:trusted_proxy', 'isTrustedProxy'],
        ['metadata:coin:display_unconnected_idps_wayf', 'displayUnconnectedIdpsWayf'],
        ['metadata:coin:do_not_add_attribute_aliases', 'skipDenormalization'],
        ['metadata:coin:policy_enforcement_decision_required', 'policyEnforcementDecisionRequired'],
        ['metadata:coin:requesterid_required', 'requesteridRequired'],
        ['metadata:coin:sign_response', 'signResponse'],
        ['metadata:coin:stepup:allow_no_token', 'stepupAllowNoToken'],
        ['metadata:coin:stepup:forceauthn', 'stepupForceAuthn'],
        ['metadata:coin:collab_enabled', 'collabEnabled'],
        ['metadata:coin:hidden', 'hidden'],
        ['metadata:coin:disable_scoping', 'disableScoping'],
        ['metadata:coin:additional_logging', 'additionalLogging'],
        ['metadata:redirect:sign', 'requestsMustBeSigned'],
    ];

    foreach ($boolProperties as $property) {
        $from = [$connection, $property[0]];
        $to = $property[1];
        $properties += $this->setPathFromObjectBool($from, $to);
    }

    // Process array properties
    $properties += $this->setPathFromObjectArray([$connection, 'metadata:NameIDFormats'], 'supportedNameIdFormats');

    // Process complex properties
    $properties += $this->assembleLogo($connection);
    $properties += $this->assembleOrganization($connection, 'nl');
    $properties += $this->assembleOrganization($connection, 'en');
    $properties += $this->assembleOrganization($connection, 'pt');
    $properties += $this->assembleCertificates($connection);
    $properties += $this->assembleContactPersons($connection);
    $properties += $this->assembleSingleLogoutServices($connection);

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

    return ['organization' . ucfirst($langCode) => new Organization($name, $displayName, $url)];
}

private function assembleCertificates(stdClass $connection)
{
    $certificateFactory = new X509CertificateFactory();
    $certificates = [];

    if (!empty($connection->metadata->certData)) {
        $certificates[] = new X509CertificateLazyProxy($certificateFactory, $connection->metadata->certData);
    }

    if (!empty($connection->metadata->certData2)) {
        $certificates[] = new X509CertificateLazyProxy($certificateFactory, $connection->metadata->certData2);
    }

    if (!empty($connection->metadata->certData3)) {
        $certificates[] = new X509CertificateLazyProxy($certificateFactory, $connection->metadata->certData3);
    }

    return empty($certificates) ? [] : ['certificates' => $certificates];
}

private function assembleContactPersons($connection)
{
    $contactPersons = [];
    $contacts = $connection->metadata->contacts ?? [];

    foreach ($contacts as $contactMetadata) {
        if (empty($contactMetadata->contactType)) {
            continue;
        }

        $contactPerson = new ContactPerson($contactMetadata->contactType);

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

        // Limit to maximum 3 contact persons to prevent unnecessary processing
        if (count($contactPersons) >= 3) {
            break;
        }
    }

    return empty($contactPersons) ? [] : ['contactPersons' => $contactPersons];
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

private function assembleSingleSignOnServices($connection)
{
    if (empty($connection->metadata->SingleSignOnService)) {
        return [];
    }

    $services = [];
    foreach ($connection->metadata->SingleSignOnService as $singleSignOnServiceMetadata) {
        if (!empty($singleSignOnServiceMetadata->Location) && !empty($singleSignOnServiceMetadata->Binding)) {
            $services[] = new Service($singleSignOnServiceMetadata->Location, $singleSignOnServiceMetadata->Binding);
        }
    }

    return empty($services) ? [] : ['singleSignOnServices' => $services];
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

    $shibMdScopes = [];
    $scopeMetadataList = $connection->metadata->shibmd->scope;

    foreach ($scopeMetadataList as $scopeMetadata) {
        if (empty($scopeMetadata->allowed)) {
            continue;
        }

        $scope = new ShibMdScope();
        $scope->allowed = $scopeMetadata->allowed;
        if (!empty($scopeMetadata->regexp)) {
            $scope->regexp = $scopeMetadata->regexp;
        }
        $shibMdScopes[] = $scope;

        // Early exit if we've collected a reasonable number of scopes
        if (count($shibMdScopes) >= 10) {
            break;
        }
    }

    return empty($shibMdScopes) ? [] : ['shibMdScopes' => $shibMdScopes];
}

private function assembleStepupConnections(stdClass $connection)
{
    if (empty($connection->stepup_connections)) {
        return [];
    }

    $connections = [];
    $maxConnections = min(count($connection->stepup_connections), 10); // Limit processing to first 10 connections

    for ($i = 0; $i < $maxConnections; $i++) {
        $sp = $connection->stepup_connections[$i];
        if (isset($sp->name, $sp->level)) {
            $connections[(string)$sp->name] = (string)$sp->level;
        }
    }

    return empty($connections) ? [] : [
        'stepupConnections' => new StepupConnections($connections),
    ];
}

private function assembleAssertionConsumerServices(stdClass $connection)
{
    if (empty($connection->metadata->AssertionConsumerService)) {
        return [];
    }

    $services = [];
    $index = 0;
    $maxServices = min(count($connection->metadata->AssertionConsumerService), 10); // Limit to first 10 services

    for ($i = 0; $i < $maxServices; $i++) {
        $acs = $connection->metadata->AssertionConsumerService[$i];

        if (empty($acs->Location) || empty($acs->Binding)) {
            continue;
        }

        if (!$this->allowedAcsLocationsValidator->validate($acs->Location)) {
            throw new RuntimeException('The acs metadata contained an invalid location uri scheme');
        }

        if (!empty($acs->Index)) {
            $index = (int) $acs->Index;
        }

        $services[] = new IndexedService($acs->Location, $acs->Binding, $index);
        $index++;
    }

    return empty($services) ? [] : ['assertionConsumerServices' => $services];
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

    $arpAttributes = $connection->arp_attributes;
    foreach ($arpAttributes as &$rules) {
        foreach ($rules as &$rule) {
            if (is_object($rule)) {
                $rule = (array) $rule;
            }
        }
    }

    return array(
        'attributeReleasePolicy' => new AttributeReleasePolicy($arpAttributes)
    );
}

private function assembleMfaEntities(stdClass $connection): array
{
    if (!isset($connection->mfa_entities)) {
        return [];
    }

    $entities = [];
    $maxEntities = min(count($connection->mfa_entities), 10); // Limit processing to first 10 entities

    for ($i = 0; $i < $maxEntities; $i++) {
        $sp = $connection->mfa_entities[$i];
        if (isset($sp->name, $sp->level)) {
            $entities[] = [
                'name' => (string)$sp->name,
                'level' => (string)$sp->level,
            ];
        }
    }

    return empty($entities) ? [] : [
        'mfaEntities' => MfaEntityCollection::fromMetadataPush($entities)
    ];
}
}
