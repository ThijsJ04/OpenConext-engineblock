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
        array $certificates = array(),
        array $contactPersons = array(),
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
        array $supportedNameIdFormats = array(
            Constants::NAMEID_TRANSIENT,
            Constants::NAMEID_PERSISTENT,
        ),
        bool $requestsMustBeSigned = false,
        string $signatureMethod = XMLSecurityKey::RSA_SHA256,
        string $workflowState = self::WORKFLOW_STATE_DEFAULT,
        array $allowedIdpEntityIds = array(),
        bool $allowAll = false,
        array $assertionConsumerServices = array(),
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
        $mdui = $serviceProvider->getMdui();
        $mdui->setLogo($serviceProvider->getLogo());

        $entity = new self(
            $serviceProvider->getEntityId(),
            $mdui,
            $serviceProvider->getOrganization('en'),
            $serviceProvider->getOrganization('nl'),
            $serviceProvider->getOrganization('pt'),
            $serviceProvider->getSingleLogoutService(),
            $serviceProvider->isRequestsMustBeSigned(),
            $serviceProvider->getCertificates(),
            $serviceProvider->getContactPersons(),
            $serviceProvider->getDescription('en'),
            $serviceProvider->getDescription('nl'),
            $serviceProvider->getDescription('pt'),
            false,
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
            $serviceProvider->getWorkflowState(),
            $serviceProvider->getManipulation(),
            $serviceProvider->getAllowedIdpEntityIds(),
            $serviceProvider->isAllowAll(),
            $serviceProvider->getAssertionConsumerServices(),
            false,
            null,
            $serviceProvider->getCoins()->isConsentRequired(),
            $serviceProvider->getCoins()->isTransparentIssuer(),
            $serviceProvider->getCoins()->isTrustedProxy(),
            false,
            null,
            $serviceProvider->getCoins()->isConsentRequired(),
            false,
            false,
            false,
            false,
            $serviceProvider->getAttributeReleasePolicy(),
            $serviceProvider->getSupportUrl('en'),
            $serviceProvider->getSupportUrl('nl'),
            $serviceProvider->getSupportUrl('pt'),
            $serviceProvider->getCoins()->getStepupAllowNoToken(),
            $serviceProvider->getCoins()->getStepupRequireLoa(),
            $serviceProvider->getCoins()->isStepupForceAuthn(),
            $serviceProvider->getCoins()->isCollabEnabled()
        );

        $entity->id = $serviceProvider->getId();
        $entity->requestedAttributes = $serviceProvider->getRequestedAttributes();

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
        // Try preferred locale display name first
        $displayName = $this->mdui->getDisplayName($preferredLocale);
        if (!empty($displayName)) {
            return $displayName;
        }

        // Try preferred locale name
        $nameProperty = 'name' . ucfirst($preferredLocale);
        if (isset($this->$nameProperty) && !empty($this->$nameProperty)) {
            return $this->$nameProperty;
        }

        // Fallback to English if preferred locale is not English
        if ($preferredLocale !== 'en') {
            $englishDisplayName = $this->mdui->getDisplayName('en');
            if (!empty($englishDisplayName)) {
                return $englishDisplayName;
            }
            
            if (!empty($this->nameEn)) {
                return $this->nameEn;
            }
        }

        // Final fallback to entity ID (should never happen)
        return $this->entityId;
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
        
        // Try preferred locale organization display name first, then organization name
        if (isset($this->$orgLocale) && (!empty($this->$orgLocale->displayName) || !empty($this->$orgLocale->name))) {
            return !empty($this->$orgLocale->displayName) ? $this->$orgLocale->displayName : $this->$orgLocale->name;
        }

        // Fallback to English organization display name, then organization name
        if (isset($this->organizationEn) && (!empty($this->organizationEn->displayName) || !empty($this->organizationEn->name))) {
            return !empty($this->organizationEn->displayName) ? $this->organizationEn->displayName : $this->organizationEn->name;
        }

        // Final fallback: empty string
        return '';
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
        $properties = get_object_vars($this);
        $serializableProperties = array_keys($properties);
        
        // Remove certificates as they should not be serialized
        $certificatesKey = array_search('certificates', $serializableProperties);
        if ($certificatesKey !== false) {
            unset($serializableProperties[$certificatesKey]);
        }
        
        return array_values($serializableProperties);
    }
}
