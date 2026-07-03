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

namespace OpenConext\EngineBlockFunctionalTestingBundle\Helper;

use OpenConext\EngineBlock\Exception\LogicException;
use OpenConext\EngineBlock\Metadata\Entity\IdentityProvider;
use OpenConext\EngineBlock\Metadata\Entity\ServiceProvider;
use OpenConext\EngineBlock\Metadata\Logo;
use OpenConext\EngineBlock\Metadata\Discovery;
use OpenConext\EngineBlockBundle\Service\DiscoverySelectionService;
use Webmozart\Assert\Assert;

class TestEntitySeeder
{
    /**
     * Build a collection of IdPs
     *
     * This is not an array of IdentityProvider value objects, but a derivative that can be used for showing IdPs on
     * the WAYF.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @param int $numberOfIdps
     * @param int $numberOfUnconnectedIdps
     * @param string $locale
     * @return array[]
     */
public static function buildIdps($numberOfIdps, $numberOfUnconnectedIdps, $locale, $defaultIdpEntityId, bool $addDiscoveries)
{
    Assert::integer($numberOfIdps);
    Assert::integer($numberOfUnconnectedIdps);
    Assert::stringNotEmpty($locale);

    if ($numberOfIdps < $numberOfUnconnectedIdps) {
        throw new LogicException('The number of IdPs that are to be created should be greater or equal to the number of unconnected IdPs');
    }

    $idps = [];
    $connectedCount = $numberOfIdps - $numberOfUnconnectedIdps;

    // Precompute discoveries for connected IdPs
    $connectedDiscoveries = $addDiscoveries ? [
        Discovery::create(
            ['en' => 'National University of the Netherlands', 'nl' => 'Rijksuniversiteit der Nederlanden'],
            ['en' => 'royal', 'nl' => 'koninklijke'],
            new Logo('/images/logo.png')
        ),
        Discovery::create(
            ['en' => 'Foreign embassy of the Republic'],
            [],
            null
        )
    ] : [];

    // Precompute discoveries for unconnected IdPs
    $unconnectedDiscoveries = $addDiscoveries ? [
        Discovery::create(
            ['en' => 'Disconnected National University of the Netherlands', 'nl' => 'Disconnected Rijksuniversiteit der Nederlanden'],
            ['en' => 'Disconnected royal', 'nl' => 'Disconnected koninklijke'],
            new Logo('/images/logo.png')
        ),
        Discovery::create(
            ['en' => 'Disconnected Foreign embassy of the Republic'],
            [],
            null
        )
    ] : [];

    // Process connected IdPs
    for ($i = 1; $i <= $connectedCount; $i++) {
        $entityId = "https://example.com/entityId/$i";
        $name = "Connected IdP $i $locale";
        $isDefaultIdp = $defaultIdpEntityId === $entityId;
        $discoveries = $i === 1 ? $connectedDiscoveries : [];

        $idps[$entityId] = [
            'name' => $name,
            'enabled' => true,
            'isDefaultIdp' => $isDefaultIdp,
            'discoveries' => $discoveries,
        ];
    }

    // Process unconnected IdPs
    for ($i = 1; $i <= $numberOfUnconnectedIdps; $i++) {
        $entityId = "https://unconnected.example.com/entityId/$i";
        $name = "Disconnected IdP $i $locale";
        $isDefaultIdp = $defaultIdpEntityId === $entityId;
        $discoveries = $i === 1 ? $unconnectedDiscoveries : [];

        $idps[$entityId] = [
            'name' => $name,
            'enabled' => false,
            'isDefaultIdp' => $isDefaultIdp,
            'discoveries' => $discoveries,
        ];
    }

    return self::transformIdpsForWayf($idps, $locale);
}

    /**
     * Build a random collection of (unconnected) IdPs
     *
     * This is not an array of IdentityProvider value objects, but a derivative that can be used for showing IdPs on
     * the WAYF.
     *
     * @param int $numberOfIdps
     * @param int $numberOfUnconnectedIdps
     * @param string $locale
     * @return array[]
     */
public static function buildRandomIdps($numberOfIdps, $locale, $defaultIdpEntityId)
{
    Assert::integer($numberOfIdps);
    Assert::stringNotEmpty($locale);

    $idpNames = [
        'Academisch Medisch Centrum (AMC)',
        'AMOLF',
        'Amphia Hospital',
        'Breda University of Applied Sciences',
        'Centraal Planbureau',
        'Centrum Wiskunde & Informatica',
        'Cito',
        'Delft University of Technology',
        'Drenthe College',
        'eduID (NL)',
        'Erasmus MC',
        'Fontys University of Applied Sciences',
        'Friesland College',
        'Graafschap College',
        'GÉANT Staff Identity Provider',
        'HAN University of Applied Sciences',
        'Hotelschool The Hague',
        'IHE Delft Institute for Water Education',
        'KNMI',
        'Koninklijke Nederlandse Akademie van Wetenschappen (KNAW)',
        'Leids Universitair Medisch Centrum',
        'Maastricht University',
        'Netherlands eScience Center',
        'SURF bv',
        'Thomas More Hogeschool',
        'VSNU',
    ];

    $idps = [];
    $randomIdpNames = $numberOfIdps < count($idpNames) ? array_rand($idpNames, $numberOfIdps) : array_keys($idpNames);

    for ($i = 1; $i <= $numberOfIdps; $i++) {
        $connected = random_int(0, 1) === 1;
        $entityId = $connected ? "https://example.com/entityId/$i" : "https://unconnected.example.com/entityId/$i";

        if ($i <= 24) {
            $name = sprintf("%s %d %s", $idpNames[$randomIdpNames[$i - 1]], $i, $locale);
        } else {
            $name = sprintf("%s IdP %d %s", $connected ? 'Connected' : 'Disconnected', $i, $locale);
        }

        $idps[$entityId] = [
            'name' => $name,
            'enabled' => $connected,
            'isDefaultIdp' => $defaultIdpEntityId === $entityId,
        ];
    }

    return self::transformIdpsForWayf($idps, $locale);
}

    /**
     * @param array $idpEntityIds
     * @param string $currentLocale
     * @return array[]
     */
private static function transformIdpsForWayf(array $idpEntityIds, $currentLocale)
{
    $discoveryService = new DiscoverySelectionService();
    $identityProviders = self::findIdentityProvidersByEntityId($idpEntityIds);

    $wayfIdps = [];
    $currentLocaleKey = 'name' . ucfirst($currentLocale);

    foreach ($identityProviders as $identityProvider) {
        $name = $identityProvider->$currentLocaleKey;
        $logoUrl = $identityProvider->logo ? $identityProvider->logo->url : '/images/placeholder.png';
        $keywords = $identityProvider->keywordsEn;
        $access = $identityProvider->enabledInWayf ? '1' : '0';
        $entityId = $identityProvider->entityId;
        $idHash = md5($entityId);
        $isDefaultIdp = $idpEntityIds[$entityId]['isDefaultIdp'];

        $wayfIdps[] = [
            'Name' => $name,
            'Logo' => $logoUrl,
            'Keywords' => $keywords,
            'Access' => $access,
            'ID' => $idHash,
            'EntityID' => $entityId,
            'isDefaultIdp' => $isDefaultIdp,
        ];

        foreach ($identityProvider->getDiscoveries() as $discovery) {
            $wayfIdps[] = [
                'Name' => $discovery->getName($currentLocale),
                'Logo' => $discovery->getLogo() ? $discovery->getLogo()->url : '/images/placeholder.png',
                'Keywords' => $discovery->getKeywords('en'),
                'Access' => $access,
                'ID' => $idHash,
                'EntityID' => $entityId,
                'isDefaultIdp' => $isDefaultIdp,
                'DiscoveryHash' => $discoveryService->hash($discovery),
            ];
        }
    }

    usort($wayfIdps, static fn($a, $b) => strcmp(strtolower($a['Name']), strtolower($b['Name'])));

    return $wayfIdps;
}

    /**
     * @return IdentityProvider[]
     */
private static function findIdentityProvidersByEntityId(array $idpEntityIds): array
{
    $idps = [];
    $defaultLogo = new Logo('/images/logo.png');
    $defaultKeywords = 'Awesome IdP, Another keyword, Example';

    foreach ($idpEntityIds as $idpEntityId => $idpData) {
        $idp = new IdentityProvider($idpEntityId);
        $idp->getMdui()->setLogo($defaultLogo);
        $idp->nameEn = $idpData['name'];
        $idp->nameNl = $idpData['name'];
        $idp->namePt = $idpData['name'];
        $idp->keywordsEn = $defaultKeywords;
        $idp->enabledInWayf = $idpData['enabled'];
        $idp->setDiscoveries($idpData['discoveries'] ?? []);

        $idps[] = $idp;
    }

    return $idps;
}

    /**
     * Build a very rudimentary SP entity
     * @return ServiceProvider
     */
public static function buildSp(?string $spName = null)
{
    $spName = $spName ?: 'DisplayName';
    $serviceProvider = new ServiceProvider('https://acme-sp.example.com');
    $logo = new Logo('/images/logo.png');
    $serviceProvider->nameNl = $spName . ' NL';
    $serviceProvider->nameEn = $spName . ' EN';
    $serviceProvider->namePt = $spName . ' PT';
    $serviceProvider->displayNameNl = $spName;
    $serviceProvider->displayNameEn = $spName;
    $serviceProvider->displayNamePt = $spName;
    $serviceProvider->getMdui()->setLogo($logo);
    return $serviceProvider;
}

    /**
     * Build a very rudimentary IdP entity
     * @return IdentityProvider
     */
public static function buildIdP(?string $idpName)
{
    $idpName = $idpName ?: 'DisplayName';
    $identityProvider = new IdentityProvider('https://acme-idp.example.com');
    $logo = new Logo('/images/logo.png');
    $identityProvider->getMdui()->setLogo($logo);

    $locales = ['Nl', 'En', 'Pt'];
    foreach ($locales as $locale) {
        $identityProvider->{"name$locale"} = "$idpName $locale";
        $identityProvider->{"displayName$locale"} = "$idpName $locale";
    }

    return $identityProvider;
}
}
