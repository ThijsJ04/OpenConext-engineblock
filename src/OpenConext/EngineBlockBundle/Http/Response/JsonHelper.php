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
        $organizationData = self::createOrganizationData(
            $identityProvider->organizationEn,
            $identityProvider->organizationNl,
            $identityProvider->organizationPt
        );

        $contactPersons = self::serializeContactPersons($identityProvider->contactPersons);
        $description = self::createLocalizedData(
            $identityProvider->getMdui(),
            'getDescriptionOrNull'
        );
        $displayName = self::createLocalizedData(
            $identityProvider->getMdui(),
            'getDisplayNameOrNull'
        );
        $logo = self::serializeLogo($identityProvider->getMdui()->getLogo());
        $name = self::createNameData(
            $identityProvider->nameEn,
            $identityProvider->nameNl,
            $identityProvider->namePt
        );
        $shibMdScopes = self::serializeShibMdScopes($identityProvider->shibMdScopes);
        $singleSignOnServices = self::serializeSingleSignOnServices($identityProvider->singleSignOnServices);

        return json_encode([
            'entity_id'               => $identityProvider->entityId,
            'organization'            => $organizationData,
            'contact_persons'         => $contactPersons,
            'description'             => $description,
            'display_name'            => $displayName,
            'logo'                    => $logo,
            'name'                    => $name,
            'shib_md_scopes'          => $shibMdScopes,
            'single_sign_on_services' => $singleSignOnServices,
        ]);
    }

    /**
     * @param object $organizationEn
     * @param object $organizationNl
     * @param object $organizationPt
     * @return array
     */
    private static function createOrganizationData($organizationEn, $organizationNl, $organizationPt)
    {
        return [
            'en' => [
                'name'         => $organizationEn->name,
                'display_name' => $organizationEn->displayName,
                'url'          => $organizationEn->url,
            ],
            'nl' => [
                'name'         => $organizationNl->name,
                'display_name' => $organizationNl->displayName,
                'url'          => $organizationNl->url,
            ],
            'pt' => [
                'name'         => $organizationPt->name,
                'display_name' => $organizationPt->displayName,
                'url'          => $organizationPt->url,
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
    private static function createLocalizedData($mdui, $methodName)
    {
        return [
            'en' => $mdui->$methodName('en'),
            'nl' => $mdui->$methodName('nl'),
            'pt' => $mdui->$methodName('pt'),
        ];
    }

    /**
     * @param object $logo
     * @return array
     */
    private static function serializeLogo($logo)
    {
        return [
            'height' => $logo->height,
            'width'  => $logo->width,
            'url'    => $logo->url,
        ];
    }

    /**
     * @param string $nameEn
     * @param string $nameNl
     * @param string $namePt
     * @return array
     */
    private static function createNameData($nameEn, $nameNl, $namePt)
    {
        return [
            'en' => $nameEn,
            'nl' => $nameNl,
            'pt' => $namePt,
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
