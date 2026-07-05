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
        $supportEmail = null;
        $supportContacts = array_filter(
            $this->serviceProvider->contactPersons,
            function (ContactPerson $contact) {
                return $contact->contactType === Consent::CONTACT_TYPE_SUPPORT;
            }
        );
        
        if (!empty($supportContacts)) {
            $supportEmail = reset($supportContacts)->emailAddress;
        }

        $serviceProvider = [
            'entity_id'    => $this->serviceProvider->entityId,
            'support_url' => [
                'en' => $this->serviceProvider->supportUrlEn,
                'nl' => $this->serviceProvider->supportUrlNl,
                'pt' => $this->serviceProvider->supportUrlPt,
            ],
            'eula_url' => $this->serviceProvider->getCoins()->termsOfServiceUrl(),
            'support_email' => $supportEmail,
            'name_id_format' => $this->serviceProvider->nameIdFormat,
        ];

        return [
            'service_provider' => array_merge(
                $serviceProvider,
                $this->getDisplayNameFields(),
                $this->getOrganizationDisplayNameFields()
            ),
            'consent_given_on' => $this->consent->getDateConsentWasGivenOn()->format(DateTime::ATOM),
            'consent_type'     => $this->consent->getConsentType()->jsonSerialize(),
        ];
    }

    private function getDisplayNameFields(): array
    {
        $fields = [];
        foreach (['en', 'nl', 'pt'] as $lang) {
            $nameProperty = 'name' . ucfirst($lang);
            $mdui = $this->serviceProvider->getMdui();
            
            $fields['display_name'][$lang] = !empty($mdui->hasDisplayName($lang))
                ? $mdui->getDisplayName($lang)
                : (!empty($this->serviceProvider->$nameProperty)
                    ? $this->serviceProvider->$nameProperty
                    : $this->serviceProvider->entityId);
        }

        return $fields;
    }

    private function getOrganizationDisplayNameFields(): array
    {
        $fields = [];
        $languages = ['en', 'nl', 'pt'];
        $englishFallback = "unknown";
        
        // Process English first to establish fallback
        if (!empty($this->serviceProvider->organizationEn->displayName)) {
            $englishFallback = $this->serviceProvider->organizationEn->displayName;
        } elseif (!empty($this->serviceProvider->organizationEn->name)) {
            $englishFallback = $this->serviceProvider->organizationEn->name;
        }
        
        // Process all languages with unified logic
        foreach ($languages as $lang) {
            $orgProperty = 'organization' . ucfirst($lang);
            
            if (!empty($this->serviceProvider->$orgProperty->displayName)) {
                $fields['organization_display_name'][$lang] = $this->serviceProvider->$orgProperty->displayName;
            } elseif (!empty($this->serviceProvider->$orgProperty->name)) {
                $fields['organization_display_name'][$lang] = $this->serviceProvider->$orgProperty->name;
            } else {
                $fields['organization_display_name'][$lang] = $lang === 'en' ? $englishFallback : ($fields['organization_display_name']['en'] ?? $englishFallback);
            }
        }

        return $fields;
    }
}
