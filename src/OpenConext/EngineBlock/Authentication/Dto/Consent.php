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
                function (ContactPerson $contact) {
                    return $contact->contactType === Consent::CONTACT_TYPE_SUPPORT;
                }
            )
        );

        $supportEmail = null;
        if (count($supportContacts) > 0) {
            $supportEmail = $supportContacts[0]->emailAddress;
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
        if (!empty($this->serviceProvider->getMdui()->hasDisplayName('en'))) {
            $fields['display_name']['en'] = $this->serviceProvider->getMdui()->getDisplayName('en');
        } elseif (!empty($this->serviceProvider->nameEn)) {
            $fields['display_name']['en'] = $this->serviceProvider->nameEn;
        } else {
            $fields['display_name']['en'] = $this->serviceProvider->entityId;
        }

        if (!empty($this->serviceProvider->getMdui()->hasDisplayName('nl'))) {
            $fields['display_name']['nl'] = $this->serviceProvider->getMdui()->getDisplayName('nl');
        } elseif (!empty($this->serviceProvider->nameNl)) {
            $fields['display_name']['nl'] = $this->serviceProvider->nameNl;
        } else {
            $fields['display_name']['nl'] = $this->serviceProvider->entityId;
        }

        if (!empty($this->serviceProvider->getMdui()->hasDisplayName('pt'))) {
            $fields['display_name']['pt'] = $this->serviceProvider->getMdui()->getDisplayName('pt');
        } elseif (!empty($this->serviceProvider->namePt)) {
            $fields['display_name']['pt'] = $this->serviceProvider->namePt;
        } else {
            $fields['display_name']['pt'] = $this->serviceProvider->entityId;
        }

        return $fields;
    }

    private function getOrganizationDisplayNameFields(): array
    {
        $fields = [];
        
        // Process English
        $organizationEn = $this->serviceProvider->organizationEn;
        if (!empty($organizationEn->displayName)) {
            $fields['organization_display_name']['en'] = $organizationEn->displayName;
        } elseif (!empty($organizationEn->name)) {
            $fields['organization_display_name']['en'] = $organizationEn->name;
        } else {
            $fields['organization_display_name']['en'] = "unknown";
        }
        
        // Process Dutch
        $organizationNl = $this->serviceProvider->organizationNl;
        if (!empty($organizationNl->displayName)) {
            $fields['organization_display_name']['nl'] = $organizationNl->displayName;
        } elseif (!empty($organizationNl->name)) {
            $fields['organization_display_name']['nl'] = $organizationNl->name;
        } else {
            $fields['organization_display_name']['nl'] = $fields['organization_display_name']['en'];
        }
        
        // Process Portuguese
        $organizationPt = $this->serviceProvider->organizationPt;
        if (!empty($organizationPt->displayName)) {
            $fields['organization_display_name']['pt'] = $organizationPt->displayName;
        } elseif (!empty($organizationPt->name)) {
            $fields['organization_display_name']['pt'] = $organizationPt->name;
        } else {
            $fields['organization_display_name']['pt'] = $fields['organization_display_name']['en'];
        }

        return $fields;
    }
}
