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

namespace OpenConext\EngineBlock\Metadata\Factory;

use OpenConext\EngineBlock\Metadata\EmptyMduiElement;
use OpenConext\EngineBlock\Metadata\Mdui;
use OpenConext\EngineBlock\Metadata\MduiElement;
use OpenConext\EngineBlock\Metadata\MultilingualElement;
use OpenConext\EngineBlock\Metadata\MultilingualValue;
use stdClass;

/**
 * Tasked with building Mdui value objects based on a
 * Manage (metadata push) JSON payload. This payload
 * is already converted to a stdClass.
 */
class MduiPushAssemblerFactory
{
public static function buildFrom(array $properties, stdClass $connection): Mdui
{
    $displayNameElement = self::assembleElement(
        'DisplayName',
        $properties['displayNameEn'] ?? null,
        $properties['displayNameNl'] ?? null,
        $properties['displayNamePt'] ?? null
    );

    $descriptionElement = self::assembleElement(
        'Description',
        $properties['descriptionEn'] ?? null,
        $properties['descriptionNl'] ?? null,
        $properties['descriptionPt'] ?? null
    );

    $keywordsElement = self::assembleElement(
        'Keywords',
        $properties['keywordsEn'] ?? null,
        $properties['keywordsNl'] ?? null,
        $properties['keywordsPt'] ?? null
    );

    $privacyStatementUrlElement = self::assemblePrivacyStatement($connection);

    $logoElement = $properties['logo'] ?? new EmptyMduiElement('Logo');

    return Mdui::fromMetadata(
        $displayNameElement,
        $descriptionElement,
        $keywordsElement,
        $logoElement,
        $privacyStatementUrlElement
    );
}

    /**
     * Creates a MduiElement (or EmptyMduiElement when no appropriate data
     * is available). Consisting of MultilingualValue objects.
     */
private static function assembleElement(
    string $elementName,
    ?string $enValue,
    ?string $nlValue,
    ?string $ptValue
): MultilingualElement {
    if (is_null($enValue) || $enValue === '') {
        return new EmptyMduiElement($elementName);
    }

    $values = [];
    if (!is_null($enValue)) {
        $values[] = new MultilingualValue($enValue, 'en');
    }
    if (!is_null($nlValue)) {
        $values[] = new MultilingualValue($nlValue, 'nl');
    }
    if (!is_null($ptValue)) {
        $values[] = new MultilingualValue($ptValue, 'pt');
    }

    return new MduiElement($elementName, $values);
}

private static function assemblePrivacyStatement(stdClass $connection): MultilingualElement
{
    if (empty($connection->metadata->PrivacyStatementURL)) {
        return new EmptyMduiElement('PrivacyStatementURL');
    }

    $enValue = $connection->metadata->PrivacyStatementURL->en ?? null;
    $nlValue = $connection->metadata->PrivacyStatementURL->nl ?? null;
    $ptValue = $connection->metadata->PrivacyStatementURL->pt ?? null;

    return self::assembleElement('PrivacyStatementURL', $enValue, $nlValue, $ptValue);
}
}
