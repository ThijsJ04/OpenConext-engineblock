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

namespace OpenConext\EngineBlock\Authentication\Dto;

use DateTime;
use OpenConext\EngineBlock\Metadata\ContactPerson;
use OpenConext\EngineBlock\Metadata\Entity\ServiceProvider;
use OpenConext\EngineBlock\Authentication\Model\Consent as ConsentEntity;

final class Consent
{
    const CONTACT_TYPE_SUPPORT = 'support';

    /**
     * @var ConsentEntity
     */
    private $consent;

    /**
     * @var ServiceProvider
     */
    private $serviceProvider;

    public function __construct(ConsentEntity $consent, ServiceProvider $serviceProvider)
    {
        $this->consent         = $consent;
        $this->serviceProvider = $serviceProvider;
    }

public function jsonSerialize(): array
{
    $supportContacts = array_values(
        array_filter(
            $this->serviceProvider->contactPersons,
            fn(ContactPerson $contact) => $contact->contactType === Consent::CONTACT_TYPE_SUPPORT
        )
    );

    $serviceProvider = [
        'entity_id'    => $this->serviceProvider->entityId,
        'support_url' => [
            'en' => $this->serviceProvider->supportUrlEn,
            'nl' => $this->serviceProvider->supportUrlNl,
            'pt' => $this->serviceProvider->supportUrlPt,
        ],
        'eula_url' => $this->serviceProvider->getCoins()->termsOfServiceUrl(),
        'support_email' => $supportContacts[0]?->emailAddress,
        'name_id_format' => $this->serviceProvider->nameIdFormat,
    ];

    $serviceProvider += $this->getDisplayNameFields();
    $serviceProvider += $this->getOrganizationDisplayNameFields();

    return [
        'service_provider' => $serviceProvider,
        'consent_given_on' => $this->consent->getDateConsentWasGivenOn()->format(DateTime::ATOM),
        'consent_type'     => $this->consent->getConsentType()->jsonSerialize(),
    ];
}

private function getDisplayNameFields(): array
{
    $fields = [];
    $languages = ['en', 'nl', 'pt'];

    foreach ($languages as $lang) {
        $displayName = $this->serviceProvider->getMdui()->hasDisplayName($lang)
            ? $this->serviceProvider->getMdui()->getDisplayName($lang)
            : ($this->serviceProvider->{"name$lang"} ?? $this->serviceProvider->entityId);

        $fields['display_name'][$lang] = $displayName;
    }

    return $fields;
}

private function getOrganizationDisplayNameFields(): array
{
    $fields = [];
    $organizations = [
        'en' => $this->serviceProvider->organizationEn,
        'nl' => $this->serviceProvider->organizationNl,
        'pt' => $this->serviceProvider->organizationPt,
    ];

    foreach (['en', 'nl', 'pt'] as $lang) {
        $organization = $organizations[$lang];
        $fields['organization_display_name'][$lang] =
            (!empty($organization->displayName) ? $organization->displayName :
            (!empty($organization->name) ? $organization->name :
            ($lang === 'en' ? 'unknown' : null)));
    }

    // Ensure fallback for non-English languages if they are still null
    foreach (['nl', 'pt'] as $lang) {
        if ($fields['organization_display_name'][$lang] === null) {
            $fields['organization_display_name'][$lang] = $fields['organization_display_name']['en'];
        }
    }

    return $fields;
}
}
