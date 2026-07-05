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
        $mdui = $identityProvider->getMdui();
        $logo = $mdui->getLogo();

        return json_encode([
            'entity_id'               => $identityProvider->entityId,
            'organization'            => self::serializeOrganizationData($identityProvider),
            'contact_persons'         => self::serializeContactPersons($identityProvider->contactPersons),
            'description'             => self::serializeLocalizedData($mdui, 'getDescriptionOrNull'),
            'display_name'            => self::serializeLocalizedData($mdui, 'getDisplayNameOrNull'),
            'logo'                    => [
                'height' => $logo->height,
                'width'  => $logo->width,
                'url'    => $logo->url,
            ],
            'name'                    => [
                'en' => $identityProvider->nameEn,
                'nl' => $identityProvider->nameNl,
                'pt' => $identityProvider->namePt,
            ],
            'shib_md_scopes'          => self::serializeShibMdScopes($identityProvider->shibMdScopes),
            'single_sign_on_services' => self::serializeSingleSignOnServices($identityProvider->singleSignOnServices),
        ]);
    }

    /**
     * @param IdentityProvider $identityProvider
     * @return array
     */
    private static function serializeOrganizationData(IdentityProvider $identityProvider)
    {
        return [
            'en' => [
                'name'         => $identityProvider->organizationEn->name,
                'display_name' => $identityProvider->organizationEn->displayName,
                'url'          => $identityProvider->organizationEn->url,
            ],
            'nl' => [
                'name'         => $identityProvider->organizationNl->name,
                'display_name' => $identityProvider->organizationNl->displayName,
                'url'          => $identityProvider->organizationNl->url,
            ],
            'pt' => [
                'name'         => $identityProvider->organizationPt->name,
                'display_name' => $identityProvider->organizationPt->displayName,
                'url'          => $identityProvider->organizationPt->url,
            ]
        ];
    }

    /**
     * @param array $contactPersons
     * @return array
     */
    private static function serializeContactPersons(array $contactPersons)
    {
        $result = [];
        foreach ($contactPersons as $contactPerson) {
            $result[] = [
                'contact_type'      => $contactPerson->contactType,
                'email_address'     => $contactPerson->emailAddress,
                'telephone_number'  => $contactPerson->telephoneNumber,
            ];
        }
        return $result;
    }

    /**
     * @param object $mdui
     * @param string $methodName
     * @return array
     */
    private static function serializeLocalizedData($mdui, $methodName)
    {
        return [
            'en' => $mdui->$methodName('en'),
            'nl' => $mdui->$methodName('nl'),
            'pt' => $mdui->$methodName('pt'),
        ];
    }

    /**
     * @param array $shibMdScopes
     * @return array
     */
    private static function serializeShibMdScopes(array $shibMdScopes)
    {
        $result = [];
        foreach ($shibMdScopes as $shibMdScope) {
            $result[] = [
                'regexp'  => (bool) $shibMdScope->regexp,
                'allowed' => $shibMdScope->allowed,
            ];
        }
        return $result;
    }

    /**
     * @param array $services
     * @return array
     */
    private static function serializeSingleSignOnServices(array $services)
    {
        $result = [];
        foreach ($services as $service) {
            $result[] = [
                'binding'  => $service->binding,
                'location' => $service->location,
            ];
        }
        return $result;
    }
}
