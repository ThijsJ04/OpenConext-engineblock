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

namespace OpenConext\EngineBlock\Stepup;

use SAML2\Assertion;
use SAML2\DOMDocumentFactory;
use SAML2\Message;
use SAML2\XML\Chunk;

class StepupGsspUserAttributeExtension
{
    /**
     * @param string[] $userAttributes
     */
    public static function add(Message $message, Assertion $assertion, array $userAttributes)
    {
        $assertionAttributes = $assertion->getAttributes();
        
        // Use array_intersect_key for more efficient filtering by keys
        $stepupUserAttributes = array_intersect_key($assertionAttributes, array_flip($userAttributes));

        if (empty($stepupUserAttributes)) {
            return;
        }

        $dom = DOMDocumentFactory::create();
        $gsspNs = 'urn:mace:surf.nl:stepup:gssp-extensions';
        $xmlnsNs = 'http://www.w3.org/2000/xmlns/';
        $xsiUrl = 'http://www.w3.org/2001/XMLSchema-instance';
        $xsUrl = 'http://www.w3.org/2001/XMLSchema';

        $ce = $dom->createElementNS($gsspNs, 'gssp:UserAttributes');
        $ce->setAttributeNS($xmlnsNs, 'xmlns:xsi', $xsiUrl);
        $ce->setAttributeNS($xmlnsNs, 'xmlns:xs', $xsUrl);

        $nameFormat = $assertion->getAttributeNameFormat();
        foreach ($stepupUserAttributes as $attributeKey => $attributeValues) {
            self::addAttribute($ce, $attributeKey, $nameFormat, $attributeValues);
        }

        $ext = $message->getExtensions();
        $ext['saml:Extensions'] = new Chunk($ce);
        $message->setExtensions($ext);
    }

    /**
     * @param string[] $values
     */
    public static function addAttribute(\DOMElement $parent, string $name, string $format, array $values): void
    {
        $attrib = $parent->ownerDocument->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Attribute');
        $attrib->setAttribute('NameFormat', $format);
        $attrib->setAttribute('Name', $name);

        foreach ($values as $value) {
            $attribValue = $parent->ownerDocument->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:AttributeValue', $value);
            $attribValue->setAttribute('xsi:type', 'xs:string');
            $attrib->appendChild($attribValue);
        }

        $parent->appendChild($attrib);
    }
}
