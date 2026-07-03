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

namespace OpenConext\EngineBlockBundle\Twig\Extensions\Extension;

use OpenConext\EngineBlock\Metadata\Entity\ServiceProvider;
use OpenConext\EngineBlockBundle\Service\IdpHistoryService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Wayf extends AbstractExtension
{
    const PREVIOUS_SELECTION_COOKIE_NAME = 'selectedidps';
    const REMEMBER_CHOICE_COOKIE_NAME = 'rememberchoice';

    private const ACCESS_ENABLED = '1';

    /**
     * @var \Symfony\Contracts\Translation\TranslatorInterface
     */
    private $translator;

    /**
     * @var array|null
     */
    private $previousSelection;

    public function __construct(RequestStack $requestStack, \Symfony\Contracts\Translation\TranslatorInterface $translator)
    {
        $this->previousSelection = $this->loadPreviousSelectionFromCookie($requestStack);
        $this->translator = $translator;
    }

public function getFunctions(): array
{
    return [
        new TwigFunction('wayfConfig', [$this, 'getWayfJsonConfig']),
        new TwigFunction('connectedIdps', [$this, 'getConnectedIdps']),
        new TwigFunction('idpDiscoveryHash', [$this, 'idpDiscoveryHash']),
    ];
}

    /**
     * @param array $idpList
     *
     * @return ConnectedIdps
     */
    public function getConnectedIdps(array $idpList): ConnectedIdps
    {
        $previousSelectionIndex = $this->indexPreviousSelection();

        $formattedIdpList = $this->formatIdpList($idpList);
        $previousSelected = $this->filterPreviouslySelected(
            $formattedIdpList,
            $previousSelectionIndex
        );

        return new ConnectedIdps($previousSelected, $formattedIdpList);
    }

    /**
     * Create an index of previous selections by IDP identifier
     *
     * @return array<string, array<mixed>>
     */
    private function indexPreviousSelection(): array
    {
        if (empty($this->previousSelection)) {
            return [];
        }

        return array_column($this->previousSelection, null, 'idp');
    }

private function formatIdpEntry(array $idp): array
{
    $keywords = $idp['Keywords'] === 'Undefined' ? [] : array_values((array)$idp['Keywords']);
    $connected = isset($idp['Access']) && $idp['Access'] === self::ACCESS_ENABLED;

    return [
        'entityId' => $idp['EntityID'] ?? null,
        'connected' => $connected,
        'displayTitle' => $idp['Name'] ?? null,
        'title' => strtolower($idp['Name'] ?? ''),
        'keywords' => strtolower(implode('|', $keywords)),
        'logo' => $idp['Logo'] ?? null,
        'isDefaultIdp' => (bool) ($idp['isDefaultIdp'] ?? null),
        'discoveryHash' => $idp['DiscoveryHash'] ?? null,
    ];
}

    private function formatIdpList(array $idpList): array
    {
        return array_map(
            function (array $idp) {
                return $this->formatIdpEntry($idp);
            },
            $idpList
        );
    }

private function filterPreviouslySelected(
    array $formattedList,
    array $previousSelectionIndex
): array {
    $result = [];
    foreach ($formattedList as $idp) {
        $entryKey = $this->idpDiscoveryHash($idp['entityId'], $idp['discoveryHash']);
        if (isset($previousSelectionIndex[$entryKey])) {
            $result[] = array_merge($previousSelectionIndex[$entryKey], $idp);
        }
    }
    return $result;
}

    /**
     * Retrieve the Wayf config used in JavaScript
     *
     * @param ConnectedIdps $connectedIdPs,
     * @param ServiceProvider $serviceProvider
     * @param string $currentLocale
     * @param bool $showRequestAccess Show unconnected IdP's ?
     * @param bool $rememberChoiceFeature Remember the chosen IdP in Wayf?
     * @param int $cutoffPointForShowingUnfilteredIdps The cutoff point for showing unfiltered IdP's
     *
     * @return string Returns a json encoded config string. Used by the JavaScript of the Wayf to behave as intended.
     */
public function getWayfJsonConfig(
    ConnectedIdps $connectedIdPs,
    ServiceProvider $serviceProvider,
    $currentLocale,
    $showRequestAccess,
    $rememberChoiceFeature,
    $cutoffPointForShowingUnfilteredIdps
) {
    $unconnectedIdps = $showRequestAccess
        ? array_values(array_filter(
            $connectedIdPs->getFormattedIdpList(),
            fn($idp) => !$idp['connected']
        ))
        : [];

    $config = [
        'previousSelectionCookieName' => self::PREVIOUS_SELECTION_COOKIE_NAME,
        'previousSelectionList' => $connectedIdPs->getFormattedPreviousSelectionList(),
        'connectedIdps' => array_values($connectedIdPs->getConnectedIdps()),
        'unconnectedIdps' => $unconnectedIdps,
        'cutoffPointForShowingUnfilteredIdps' => $cutoffPointForShowingUnfilteredIdps,
        'rememberChoiceCookieName' => self::REMEMBER_CHOICE_COOKIE_NAME,
        'rememberChoiceFeature' => $rememberChoiceFeature,
        'messages' => [
            'moreIdpResults' => $this->translator->trans('more_idp_results'),
            'requestAccess' => $this->translator->trans('request_access'),
        ],
        'requestAccessUrl' => '/authentication/idp/requestAccess?'.http_build_query([
            'lang' => $currentLocale,
            'spEntityId' => $serviceProvider->entityId,
            'spName' => $serviceProvider->getDisplayName($currentLocale),
        ]),
    ];

    return json_encode($config, JSON_PRETTY_PRINT);
}

private function loadPreviousSelectionFromCookie(RequestStack $requestStack)
{
    $request = $requestStack->getCurrentRequest();
    if (!$request) {
        return [];
    }

    $cookieValue = $request->cookies->get(self::PREVIOUS_SELECTION_COOKIE_NAME, '');
    if (empty($cookieValue)) {
        return [];
    }

    $previousSelection = json_decode($cookieValue, true);
    if (!is_array($previousSelection)) {
        return [];
    }

    $previousSelectionIndexed = [];
    foreach ($previousSelection as $item) {
        if (isset($item['idp'])) {
            $previousSelectionIndexed[$item['idp']] = $item;
        }
    }

    return $previousSelectionIndexed;
}

    public function idpDiscoveryHash(string $entityId, ?string $discoveryHash = null): string
    {
        return (new IdpHistoryService())->makeIdpDiscoveryHash($entityId, $discoveryHash);
    }
}
