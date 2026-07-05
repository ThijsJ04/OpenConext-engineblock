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

namespace OpenConext\EngineBlockBundle\Localization;

use Symfony\Component\HttpFoundation\Request;

final class LocaleProvider
{
    /**
     * @var string[]
     */
    private $availableLocales;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @var Request|null
     */
    private $request;

    /**
     * @param LanguageSupportProvider $languageSupportProvider
     * @param string $defaultLocale
     */
    public function __construct(LanguageSupportProvider $languageSupportProvider, $defaultLocale)
    {
        $this->availableLocales = $languageSupportProvider->getSupportedLanguages();
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * @param Request $request
     *
     * @return void
     */
    public function scopeWithRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        if (!$this->request) {
            return $this->defaultLocale;
        }

        // Helper function to check and return valid locale
        $getValidLocale = function ($value) {
            return in_array($value, $this->availableLocales, true) ? $value : null;
        };

        // Check query parameter
        $locale = $getValidLocale($this->request->query->get('lang'));
        if ($locale !== null) {
            return $locale;
        }

        // Check request body
        $locale = $getValidLocale($this->request->request->get('lang'));
        if ($locale !== null) {
            return $locale;
        }

        // Check cookies
        $locale = $getValidLocale($this->request->cookies->get('lang'));
        if ($locale !== null) {
            return $locale;
        }

        // Prepare available locales with default locale prioritized
        $availableLocales = array_unique(array_merge([$this->defaultLocale], $this->availableLocales));

        return $this->request->getPreferredLanguage($availableLocales);
    }
}
