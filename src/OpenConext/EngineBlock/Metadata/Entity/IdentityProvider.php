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
use InvalidArgumentException;
use OpenConext\EngineBlock\Exception\InvalidDiscoveryException;
use OpenConext\EngineBlock\Metadata\Coins;
use OpenConext\EngineBlock\Metadata\ConsentSettings;
use OpenConext\EngineBlock\Metadata\Discovery;
use OpenConext\EngineBlock\Metadata\Logo;
use OpenConext\EngineBlock\Metadata\Mdui;
use OpenConext\EngineBlock\Metadata\MetadataRepository\Visitor\VisitorInterface;
use OpenConext\EngineBlock\Metadata\MfaEntityCollection;
use OpenConext\EngineBlock\Metadata\Organization;
use OpenConext\EngineBlock\Metadata\ShibMdScope;
use OpenConext\EngineBlock\Metadata\Service;
use OpenConext\EngineBlock\Metadata\StepupConnections;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SAML2\Constants;

/**
 * @package OpenConext\EngineBlock\Metadata\Entity
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 * WARNING: Please don't use this entity directly but use the dedicated factory instead.
 * @see \OpenConext\EngineBlock\Factory\Factory\IdentityProviderFactory
 */
#[ORM\Entity]
class IdentityProvider extends AbstractRole
{
    const GUEST_QUALIFIER_ALL = 'All';
    const GUEST_QUALIFIER_SOME = 'Some';
    const GUEST_QUALIFIER_NONE = 'None';

    /**
     * In all-caps to indicate that though the language doesn't allow it, this should be an array constant.
     *
     * @var string[]
     */
    public static $GUEST_QUALIFIERS = array(
        self::GUEST_QUALIFIER_ALL,
        self::GUEST_QUALIFIER_SOME,
        self::GUEST_QUALIFIER_NONE
    );

    /**
     * @var bool
     */
    #[ORM\Column(name: 'enabled_in_wayf', type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    public ?bool $enabledInWayf = true;

    /**
     * @var Service[]
     */
    #[ORM\Column(name: 'single_sign_on_services', type: \Doctrine\DBAL\Types\Types::ARRAY, length: 65535)]
    public $singleSignOnServices = array();

    /**
     * @var ConsentSettings
     */
    #[ORM\Column(name: 'consent_settings', type: \Doctrine\DBAL\Types\Types::JSON, length: 16777215)]
    private $consentSettings;

    /**
     * @var ShibMdScope[]
     */
    #[ORM\Column(name: 'shib_md_scopes', type: \Doctrine\DBAL\Types\Types::ARRAY, length: 65535)]
    public $shibMdScopes = array();

    /**
     * @var array<int, Discovery>
     */
    #[ORM\Column(name: 'idp_discoveries', type: \Doctrine\DBAL\Types\Types::JSON)]
    private $discoveries;

    /**
     * WARNING: Please don't use this entity directly but use the dedicated factory instead.
     * @see \OpenConext\EngineBlock\Metadata\Factory\Factory\IdentityProviderFactory
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
        string $descriptionEn = '',
        string $descriptionNl = '',
        string $descriptionPt = '',
        bool $disableScoping = false,
        string $displayNameEn = '',
        string $displayNameNl = '',
        string $displayNamePt = '',
        string $keywordsEn = '',
        string $keywordsNl = '',
        string $keywordsPt = '',
        Logo $logo = null,
        string $nameEn = '',
        string $nameNl = '',
        string $namePt = '',
        ?string $nameIdFormat = null,
        array $supportedNameIdFormats = array(
            Constants::NAMEID_TRANSIENT,
            Constants::NAMEID_PERSISTENT,
        ),
        bool $requestsMustBeSigned = false,
        string $signatureMethod = XMLSecurityKey::RSA_SHA256,
        string $workflowState = self::WORKFLOW_STATE_DEFAULT,
        string $manipulation = '',
        bool $enabledInWayf = true,
        string $guestQualifier = self::GUEST_QUALIFIER_ALL,
        bool $hidden = false,
        ?string $schacHomeOrganization = null,
        array $shibMdScopes = array(),
        array $singleSignOnServices = array(),
        ConsentSettings $consentSettings = null,
        StepupConnections $stepupConnections = null,
        MfaEntityCollection $mfaEntities = null,
        array $discoveries = [],
        ?string $defaultRAC = null,
        bool $policyEnforcementDecisionRequired = false
    ) {
        $mdui = $mdui ?? Mdui::emptyMdui();
        
        // Prepare parent constructor parameters
        $parentParams = [
            $entityId, $mdui, $organizationEn, $organizationNl, $organizationPt,
            $singleLogoutService, $certificates, $contactPersons,
            $descriptionEn, $descriptionNl, $descriptionPt,
            $displayNameEn, $displayNameNl, $displayNamePt,
            $keywordsEn, $keywordsNl, $keywordsPt,
            $logo, $nameEn, $nameNl, $namePt,
            $nameIdFormat, $supportedNameIdFormats,
            $requestsMustBeSigned, $workflowState, $manipulation
        ];
        
        parent::__construct(...$parentParams);

        // Set properties efficiently
        $this->enabledInWayf = $enabledInWayf;
        $this->shibMdScopes = $shibMdScopes;
        $this->singleSignOnServices = $singleSignOnServices;
        $this->consentSettings = $consentSettings;

        // Create Coins object with optimized parameter passing
        $this->coins = Coins::createForIdentityProvider(
            $guestQualifier, $schacHomeOrganization, $hidden, $stepupConnections,
            $disableScoping, $additionalLogging, $signatureMethod, $mfaEntities,
            $defaultRAC, $policyEnforcementDecisionRequired
        );

        // Validate and set discoveries in one step
        $this->assertAllDiscoveries($discoveries);
        $this->discoveries = $discoveries;
    }

    /**
     * {@inheritdoc}
     */
    public function accept(VisitorInterface $visitor)
    {
        $visitor->visitIdentityProvider($this);
    }

    /**
     * @param string $preferredLocale
     * @return string
     */
    public function getDisplayName($preferredLocale = '')
    {
        $idpName = match ($preferredLocale) {
            'nl' => $this->nameNl,
            'en' => $this->nameEn,
            'pt' => $this->namePt,
            default => '',
        };
        
        return empty($idpName) ? $this->entityId : $idpName;
    }

    /**
     * @param ConsentSettings $settings
     * @return IdentityProvider
     */
    public function setConsentSettings(ConsentSettings $settings)
    {
        $this->consentSettings = $settings;

        return $this;
    }

    /**
     * @return ConsentSettings
     */
    public function getConsentSettings()
    {
        if (!$this->consentSettings instanceof ConsentSettings) {
            $this->setConsentSettings(
                new ConsentSettings(
                    (array)$this->consentSettings
                )
            );
        }

        return $this->consentSettings;
    }

    /**
     * @return array<int, Discovery>
     */
    public function getDiscoveries(): array
    {
        $this->ensureDiscoveriesDeserialized();
        return $this->discoveries;
    }

    /**
     * @param array<Discovery> $discoveries
     */
    public function setDiscoveries(array $discoveries)
    {
        $this->assertAllDiscoveries($discoveries);
        $this->discoveries = $discoveries;
    }

    private function ensureDiscoveriesDeserialized(): void
    {
        $this->discoveries = array_values(array_filter(array_map(function ($discovery) {
            try {
                if ($discovery instanceof Discovery) {
                    return $discovery;
                }
                
                return $this->createDiscoveryFromArray($discovery);
            } catch (InvalidDiscoveryException $e) {
                return null;
            }
        }, $this->discoveries ?? [])));
    }

    private function createDiscoveryFromArray(array $discoveryData): Discovery
    {
        $logo = $this->createLogoFromArray($discoveryData['logo'] ?? null);
        
        return Discovery::create(
            $discoveryData['names'] ?? [],
            $discoveryData['keywords'] ?? [],
            $logo
        );
    }

    private function createLogoFromArray(?array $logoData): ?Logo
    {
        if (!$logoData) {
            return null;
        }
        
        $logo = new Logo($logoData['url']);
        $logo->width = $logoData['width'] ?? null;
        $logo->height = $logoData['height'] ?? null;
        
        return $logo;
    }

    private function assertAllDiscoveries(array $discoveries): void
    {
        foreach ($discoveries as $discovery) {
            if (!$discovery instanceof Discovery) {
                throw new InvalidArgumentException('Discovery must be instance of Discovery');
            }
        }
    }

    /**
     * Certificates are not available on the object after deserialisation!
     *
     * @return string[]
     */
    public function __sleep()
    {
        return [
            // IdentityProvider specific properties
            'enabledInWayf',
            'singleSignOnServices',
            'consentSettings',
            'shibMdScopes',
            'discoveries',
            
            // Inherited from AbstractRole - core properties
            'id',
            'entityId',
            'nameNl',
            'nameEn',
            'namePt',
            'descriptionNl',
            'descriptionEn',
            'descriptionPt',
            'displayNameNl',
            'displayNameEn',
            'displayNamePt',
            'logo',
            'organizationNl',
            'organizationEn',
            'organizationPt',
            'keywordsNl',
            'keywordsEn',
            'keywordsPt',
            'workflowState',
            'contactPersons',
            'nameIdFormat',
            'supportedNameIdFormats',
            'singleLogoutService',
            'requestsMustBeSigned',
            'manipulation',
            'coins',
            'mdui',
        ];
    }
}
