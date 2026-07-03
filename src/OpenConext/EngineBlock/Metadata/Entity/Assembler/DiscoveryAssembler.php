<?php

/**
 * Copyright 2025 SURFnet B.V.
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

namespace OpenConext\EngineBlock\Metadata\Entity\Assembler;

use OpenConext\EngineBlock\Metadata\Discovery;
use OpenConext\EngineBlock\Metadata\Logo;
use OpenConext\EngineBlockBundle\Localization\LanguageSupportProvider;
use stdClass;

class DiscoveryAssembler
{
    /**
     * @var LanguageSupportProvider
     */
    private $languageSupportProvider;

    public function __construct(LanguageSupportProvider $languageSupportProvider)
    {
        $this->languageSupportProvider = $languageSupportProvider;
    }

public function assembleDiscoveries(stdClass $connection): array
{
    if (!isset($connection->metadata->discoveries)) {
        return [];
    }

    $discoveries = [];
    $supportedLanguages = $this->languageSupportProvider->getSupportedLanguages();

    foreach ($connection->metadata->discoveries as $discovery) {
        $names = $this->extractLocalizedFields($discovery, 'name');
        if (!isset($names['en'])) {
            continue;
        }

        $keywords = $this->extractLocalizedFields($discovery, 'keywords');
        $logo = $this->assembleLogo($discovery);

        $discoveries[] = Discovery::create($names, $keywords, $logo);
    }

    return $discoveries ? ['discoveries' => $discoveries] : [];
}

    private function extractLocalizedFields(stdClass $discovery, string $fieldPrefix): array
    {
        $fields = [];
        foreach ($this->languageSupportProvider->getSupportedLanguages() as $language) {
            $accessor = sprintf('%s_%s', $fieldPrefix, $language);
            if (isset($discovery->$accessor)) {
                $fields[$language] = $discovery->$accessor;
            }
        }

        return array_filter(array_map('trim', $fields));
    }

private function assembleLogo(stdClass $discovery): ?Logo
{
    if (!isset($discovery->logo_url) || trim($discovery->logo_url) === '') {
        return null;
    }

    $logo = new Logo($discovery->logo_url);

    if (isset($discovery->logo_height)) {
        $logo->height = $discovery->logo_height;
    }
    if (isset($discovery->logo_width)) {
        $logo->width = $discovery->logo_width;
    }

    return $logo;
}
}
