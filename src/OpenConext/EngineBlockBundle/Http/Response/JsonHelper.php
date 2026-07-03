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

namespace OpenConext\EngineBlockBundle\Http\Response;

use OpenConext\EngineBlock\Metadata\ContactPerson;
use OpenConext\EngineBlock\Metadata\Entity\IdentityProvider;
use OpenConext\EngineBlock\Metadata\Service;
use OpenConext\EngineBlock\Metadata\ShibMdScope;

final class JsonHelper
{
    /**
     * @param IdentityProvider $identityProvider
     * @return string
     */
public static function serializeIdentityProvider(IdentityProvider $identityProvider)
{
    $data = [
        'entity_id' => $identityProvider->entityId,
        'organization' => [
            'en' => [
                'name' => $identityProvider->organizationEn->name,
                'display_name' => $identityProvider->organizationEn->displayName,
                'url' => $identityProvider->organizationEn->url,
            ],
            'nl' => [
                'name' => $identityProvider->organizationNl->name,
                'display_name' => $identityProvider->organizationNl->displayName,
                'url' => $identityProvider->organizationNl->url,
            ],
            'pt' => [
                'name' => $identityProvider->organizationPt->name,
                'display_name' => $identityProvider->organizationPt->displayName,
                'url' => $identityProvider->organizationPt->url,
            ]
        ],
        'name' => [
            'en' => $identityProvider->nameEn,
            'nl' => $identityProvider->nameNl,
            'pt' => $identityProvider->namePt,
        ],
    ];

    $mdui = $identityProvider->getMdui();
    $data['description'] = [
        'en' => $mdui->getDescriptionOrNull('en'),
        'nl' => $mdui->getDescriptionOrNull('nl'),
        'pt' => $mdui->getDescriptionOrNull('pt'),
    ];
    $data['display_name'] = [
        'en' => $mdui->getDisplayNameOrNull('en'),
        'nl' => $mdui->getDisplayNameOrNull('nl'),
        'pt' => $mdui->getDisplayNameOrNull('pt'),
    ];

    $logo = $mdui->getLogo();
    $data['logo'] = [
        'height' => $logo->height,
        'width' => $logo->width,
        'url' => $logo->url,
    ];

    $contactPersons = [];
    foreach ($identityProvider->contactPersons as $contactPerson) {
        $contactPersons[] = [
            'contact_type' => $contactPerson->contactType,
            'email_address' => $contactPerson->emailAddress,
            'telephone_number' => $contactPerson->telephoneNumber,
        ];
    }
    $data['contact_persons'] = $contactPersons;

    $shibMdScopes = [];
    foreach ($identityProvider->shibMdScopes as $shibMdScope) {
        $shibMdScopes[] = [
            'regexp' => (bool) $shibMdScope->regexp,
            'allowed' => $shibMdScope->allowed,
        ];
    }
    $data['shib_md_scopes'] = $shibMdScopes;

    $singleSignOnServices = [];
    foreach ($identityProvider->singleSignOnServices as $service) {
        $singleSignOnServices[] = [
            'binding' => $service->binding,
            'location' => $service->location,
        ];
    }
    $data['single_sign_on_services'] = $singleSignOnServices;

    return json_encode($data);
}
}
