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

namespace OpenConext\EngineBlock\Metadata\Entity;

use Doctrine\ORM\Mapping as ORM;
use OpenConext\EngineBlock\Metadata\AttributeReleasePolicy;
use OpenConext\EngineBlock\Metadata\Coins;
use OpenConext\EngineBlock\Metadata\Factory\ServiceProviderEntityInterface;
use OpenConext\EngineBlock\Metadata\Logo;
use OpenConext\EngineBlock\Metadata\Mdui;
use OpenConext\EngineBlock\Metadata\MetadataRepository\Visitor\VisitorInterface;
use OpenConext\EngineBlock\Metadata\Organization;
use OpenConext\EngineBlock\Metadata\RequestedAttribute;
use OpenConext\EngineBlock\Metadata\IndexedService;
use OpenConext\EngineBlock\Metadata\Service;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SAML2\Constants;

/**
 * @package OpenConext\EngineBlock\Metadata\Entity
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 *
 * WARNING: Please don't use this entity directly but use the dedicated factory instead.
 * @see \OpenConext\EngineBlock\Factory\Factory\ServiceProviderFactory
 */
#[ORM\Entity]
class ServiceProvider extends AbstractRole
{
    /**
     * @var null|AttributeReleasePolicy
     */
    #[ORM\Column(name: 'attribute_release_policy', type: \Doctrine\DBAL\Types\Types::ARRAY, length: 65535)]
    public $attributeReleasePolicy;

    /**
     * @var IndexedService[]
     */
    #[ORM\Column(name: 'assertion_consumer_services', type: \Doctrine\DBAL\Types\Types::ARRAY, length: 65535)]
    public $assertionConsumerServices;

    /**
     * @var string[]
     */
    #[ORM\Column(name: 'allowed_idp_entity_ids', type: \Doctrine\DBAL\Types\Types::ARRAY, length: 6777215)]
    public $allowedIdpEntityIds;

    /**
     * @var bool
     */
    #[ORM\Column(name: 'allow_all', type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    public ?bool $allowAll = null;

    /**
     * @var null|RequestedAttribute[]
     */
    #[ORM\Column(name: 'requested_attributes', type: \Doctrine\DBAL\Types\Types::ARRAY, length: 65535)]
    public $requestedAttributes;

    /**
     * @var null|string
     */
    #[ORM\Column(name: 'support_url_en', type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    public ?string $supportUrlEn = null;

    /**
     * @var null|string
     */
    #[ORM\Column(name: 'support_url_nl', type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    public ?string $supportUrlNl = null;

    /**
     * @var null|string
     */
    #[ORM\Column(name: 'support_url_pt', type: \Doctrine\DBAL\Types\Types::STRING, nullable: true)]
    public ?string $supportUrlPt = null;

    /**
     * WARNING: Please don't use this entity directly but use the dedicated factory instead.
     * @see \OpenConext\EngineBlock\Factory\Factory\ServiceProviderFactory
     */
public function __construct(
    $entityId,
    ?Mdui $mdui = null,
    Organization $organizationEn = null,
    Organization $organizationNl = null,
    Organization $organizationPt = null,
    Service $singleLogoutService = null,
    bool $additionalLogging = false,
    array $certificates = [],
    array $contactPersons = [],
    ?string $descriptionEn = '',
    ?string $descriptionNl = '',
    ?string $descriptionPt = '',
    bool $disableScoping = false,
    ?string $displayNameEn = '',
    ?string $displayNameNl = '',
    ?string $displayNamePt = '',
    ?string $keywordsEn = '',
    ?string $keywordsNl = '',
    ?string $keywordsPt = '',
    ?Logo $logo = null,
    ?string $nameEn = '',
    ?string $nameNl = '',
    ?string $namePt = '',
    ?string $nameIdFormat = null,
    array $supportedNameIdFormats = [
        Constants::NAMEID_TRANSIENT,
        Constants::NAMEID_PERSISTENT,
    ],
    bool $requestsMustBeSigned = false,
    string $signatureMethod = XMLSecurityKey::RSA_SHA256,
    string $workflowState = self::WORKFLOW_STATE_DEFAULT,
    array $allowedIdpEntityIds = [],
    bool $allowAll = false,
    array $assertionConsumerServices = [],
    bool $displayUnconnectedIdpsWayf = false,
    ?string $termsOfServiceUrl = null,
    bool $isConsentRequired = true,
    bool $isTransparentIssuer = false,
    bool $isTrustedProxy = false,
    ?array $requestedAttributes = null,
    bool $skipDenormalization = false,
    bool $policyEnforcementDecisionRequired = false,
    bool $requesteridRequired = false,
    bool $signResponse = false,
    string $manipulation = '',
    ?AttributeReleasePolicy $attributeReleasePolicy = null,
    ?string $supportUrlEn = null,
    ?string $supportUrlNl = null,
    ?string $supportUrlPt = null,
    ?bool $stepupAllowNoToken = null,
    ?string $stepupRequireLoa = null,
    bool $stepupForceAuthn = false,
    bool $collabEnabled = false
) {
    $mdui = $mdui ?? Mdui::emptyMdui();

    parent::__construct(
        $entityId,
        $mdui,
        $organizationEn,
        $organizationNl,
        $organizationPt,
        $singleLogoutService,
        $certificates,
        $contactPersons,
        $descriptionEn,
        $descriptionNl,
        $descriptionPt,
        $displayNameEn,
        $displayNameNl,
        $displayNamePt,
        $keywordsEn,
        $keywordsNl,
        $keywordsPt,
        $logo,
        $nameEn,
        $nameNl,
        $namePt,
        $nameIdFormat,
        $supportedNameIdFormats,
        $requestsMustBeSigned,
        $workflowState,
        $manipulation
    );

    $this->attributeReleasePolicy = $attributeReleasePolicy;
    $this->allowedIdpEntityIds = $allowedIdpEntityIds;
    $this->allowAll = $allowAll;
    $this->assertionConsumerServices = $assertionConsumerServices;
    $this->requestedAttributes = $requestedAttributes;
    $this->supportUrlEn = $supportUrlEn;
    $this->supportUrlNl = $supportUrlNl;
    $this->supportUrlPt = $supportUrlPt;

    $this->coins = Coins::createForServiceProvider(
        $isConsentRequired,
        $isTransparentIssuer,
        $isTrustedProxy,
        $displayUnconnectedIdpsWayf,
        $termsOfServiceUrl,
        $skipDenormalization,
        $policyEnforcementDecisionRequired,
        $requesteridRequired,
        $signResponse,
        $stepupAllowNoToken,
        $stepupRequireLoa,
        $disableScoping,
        $additionalLogging,
        $signatureMethod,
        $stepupForceAuthn,
        $collabEnabled
    );
}

    /**
     * This is a factory method to convert the immutable ServiceProviderEntityInterface to the legacy domain entity.
     *
     * @param ServiceProviderEntityInterface $serviceProvider
     * @return ServiceProvider
     */
public static function fromServiceProviderEntity(ServiceProviderEntityInterface $serviceProvider): ServiceProvider
{
    $entity = new self(
        $serviceProvider->getEntityId(),
        $serviceProvider->getMdui(),
        $serviceProvider->getOrganization('en'),
        $serviceProvider->getOrganization('nl'),
        $serviceProvider->getOrganization('pt'),
        $serviceProvider->getSingleLogoutService(),
        $serviceProvider->isAdditionalLogging(),
        $serviceProvider->getCertificates(),
        $serviceProvider->getContactPersons(),
        $serviceProvider->getDescription('en'),
        $serviceProvider->getDescription('nl'),
        $serviceProvider->getDescription('pt'),
        $serviceProvider->isDisableScoping(),
        $serviceProvider->getDisplayName('en'),
        $serviceProvider->getDisplayName('nl'),
        $serviceProvider->getDisplayName('pt'),
        $serviceProvider->getKeywords('en'),
        $serviceProvider->getKeywords('nl'),
        $serviceProvider->getKeywords('pt'),
        $serviceProvider->getLogo(),
        $serviceProvider->getName('en'),
        $serviceProvider->getName('nl'),
        $serviceProvider->getName('pt'),
        $serviceProvider->getNameIdFormat(),
        $serviceProvider->getSupportedNameIdFormats(),
        $serviceProvider->isRequestsMustBeSigned(),
        $serviceProvider->getSignatureMethod(),
        $serviceProvider->getWorkflowState(),
        $serviceProvider->getAllowedIdpEntityIds(),
        $serviceProvider->isAllowAll(),
        $serviceProvider->getAssertionConsumerServices(),
        $serviceProvider->isDisplayUnconnectedIdpsWayf(),
        $serviceProvider->isConsentRequired(),
        $serviceProvider->isTransparentIssuer(),
        $serviceProvider->isTrustedProxy(),
        $serviceProvider->getRequestedAttributes(),
        $serviceProvider->isSkipDenormalization(),
        $serviceProvider->isPolicyEnforcementDecisionRequired(),
        $serviceProvider->isRequesteridRequired(),
        $serviceProvider->isSignResponse(),
        $serviceProvider->getManipulation(),
        $serviceProvider->getAttributeReleasePolicy(),
        $serviceProvider->getSupportUrl('en'),
        $serviceProvider->getSupportUrl('nl'),
        $serviceProvider->getSupportUrl('pt'),
        $serviceProvider->isStepupAllowNoToken(),
        $serviceProvider->getStepupRequireLoa(),
        $serviceProvider->isStepupForceAuthn(),
        $serviceProvider->isCollabEnabled()
    );

    $entity->id = $serviceProvider->getId();
    $entity->entityId = $serviceProvider->getEntityId();

    return $entity;
}

    /**
     * {@inheritdoc}
     */
    public function accept(VisitorInterface $visitor)
    {
        $visitor->visitServiceProvider($this);
    }

    /**
     * @return null|AttributeReleasePolicy
     */
    public function getAttributeReleasePolicy()
    {
        return $this->attributeReleasePolicy;
    }

    /**
     * @param string $idpEntityId
     * @return bool
     */
    public function isAllowed($idpEntityId)
    {
        return $this->allowAll || in_array($idpEntityId, $this->allowedIdpEntityIds);
    }

    /**
     * Algorithm for display name is:
     * 1. Display name in preferred locale
     * 2. Name in preferred locale
     * 3. Display name in English
     * 4. Name in English
     * 5. EntityID (should never happen)
     */
    public function getDisplayName(string $preferredLocale = 'en'): string
    {

        $preferredName = $this->mdui->getDisplayName($preferredLocale);
        $fallback = 'name' . ucfirst($preferredLocale);

        if ($preferredName !== '') {
            $spName = $preferredName;
        } elseif (isset($this->$fallback)) {
            $spName = $this->$fallback;
        }

        if ($preferredLocale !== 'en' & empty($spName)) {
            $englishDisplayName = $this->mdui->getDisplayName('en');
            $spName = !empty($englishDisplayName) ? $englishDisplayName : $this->nameEn;
        }

        if (empty($spName)) {
            $spName = $this->entityId;
        }

        return $spName;
    }

    /**
     * Algorithm for organization name is
     * 1. Organization display name in preferred locale
     * 2. Organization name in preferred locale
     * 3. English organization display name
     * 4. English organization name
     * 5. Empty string (will be set to the locale-specific variant of 'unknown' in the template)
     */
public function getOrganizationName(string $preferredLocale = 'en'): string
{
    $orgLocale = 'organization' . ucfirst($preferredLocale);
    $orgName = '';

    if (isset($this->$orgLocale)) {
        $orgName = $this->$orgLocale->displayName ?: $this->$orgLocale->name;
    }

    if (empty($orgName) && $preferredLocale !== 'en' && isset($this->organizationEn)) {
        $orgName = $this->organizationEn->displayName ?: $this->organizationEn->name;
    }

    return $orgName ?? '';
}

    /**
     * @return bool
     */
    public function isAttributeAggregationRequired()
    {
        if (is_null($this->attributeReleasePolicy)) {
            return false;
        }

        $rules = $this->attributeReleasePolicy->getRulesWithSourceSpecification();

        return count($rules) > 0;
    }

    /**
     * Certificates are not available on the object after deserialisation!
     *
     * @return array
     */
public function __sleep()
{
    return [
        'id',
        'entityId',
        'workflowState',
        'manipulation',
        'nameNl',
        'nameEn',
        'namePt',
        'descriptionNl',
        'descriptionEn',
        'descriptionPt',
        'displayNameNl',
        'displayNameEn',
        'displayNamePt',
        'keywordsNl',
        'keywordsEn',
        'keywordsPt',
        'logo',
        'organizationNl',
        'organizationEn',
        'organizationPt',
        'contactPersons',
        'nameIdFormat',
        'supportedNameIdFormats',
        'singleLogoutService',
        'requestsMustBeSigned',
        'coins',
        'mdui',
        'attributeReleasePolicy',
        'assertionConsumerServices',
        'allowedIdpEntityIds',
        'allowAll',
        'requestedAttributes',
        'supportUrlEn',
        'supportUrlNl',
        'supportUrlPt',
    ];
}
}
