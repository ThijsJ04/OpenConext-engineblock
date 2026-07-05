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

/**
 * FORKED from OpenConext EngineBlock_Corto_XmlToArray.
 *
 * Note that this format is deprecated so there is little risk of missing an update
 * and in the future it should be possible to remove this fork.
 */
// @codingStandardsIgnoreStart

namespace OpenConext\EngineBlockFunctionalTestingBundle\Parser\Corto;

/**
 * Class XmlToArray
 * @package OpenConext\EngineBlockFunctionalTestingBundle\Parser\Corto
 * @SuppressWarnings("PMD")
 */
class XmlToArray
{
    const PRIVATE_PFX           = '__';
    const COMMENT_PFX           = '__c';
    const TAG_NAME_PFX          = '__t';
    const VALUE_PFX             = '__v';
    const PLACEHOLDER_VALUE     = '__placeholder__';
    const ATTRIBUTE_PFX         = '_';
    const MAX_RECURSION_LEVEL   = 50;

    /**
     * @var array All namespaces used in SAML2 messages.
     */
    protected static $_namespaces = [
        'urn:oasis:names:tc:SAML:1.0:protocol'          => 'samlp',
        'urn:oasis:names:tc:SAML:1.0:assertion'         => 'saml',
        'urn:oasis:names:tc:SAML:2.0:protocol'          => 'samlp',
        'urn:oasis:names:tc:SAML:2.0:assertion'         => 'saml',
        'urn:oasis:names:tc:SAML:2.0:metadata'          => 'md',
        'urn:oasis:names:tc:SAML:2.0:metadata:ui'       => 'mdui',
        'http://www.w3.org/2001/XMLSchema-instance'     => 'xsi',
        'http://www.w3.org/2001/XMLSchema'              => 'xs',
        'http://schemas.xmlsoap.org/soap/envelope/'     => 'SOAP-ENV',
        'http://www.w3.org/2000/09/xmldsig#'            => 'ds',
        'http://www.w3.org/2001/04/xmlenc#'             => 'xenc',
        'http://www.w3.org/2001/10/xml-exc-c14n#'       => 'ec',
    ];

    /**
     * @var array All XML entities which are treated as single values in Corto.
     */
    protected static $_singulars = [
        'md:AffiliationDescriptor',
#        'md:AttributeAuthorityDescriptor',
#        'md:AuthnAuthorityDescriptor',
        'md:Company',
#        'md:EntitiesDescriptor',
#        'md:EntityDescriptor',
        'md:Extensions',
        'md:GivenName',
#        'md:IDPSSODescriptor',
        'md:Organization',
#        'md:PDPDescriptor',
#        'md:RoleDescriptor',
#        'md:SPSSODescriptor',
        'md:SurName',
        'saml:Advice',
        'saml:Assertion',             #
        'saml:AssertionIDRef',        #
        'saml:AssertionURIRef',        #
#        'saml:Attribute',
#        'saml:AttributeStatement',
        'saml:Audience',
        'saml:AudienceRestriction',
        'saml:AuthnContext',
        'saml:AuthnContextClassRef',
        'saml:AuthnContextDecl',
        'saml:AuthnContextDeclRef',
        'saml:AuthnStatement',        #
#        'saml:AuthzDecisionStatement',
        'saml:BaseID',
#        'saml:Condition',
        'saml:Conditions',
        'saml:EncryptedAssertion',    #
#        'saml:EncryptedAttribute',
        'saml:EncryptedID',
        'saml:Evidence',
        'saml:Issuer',
        'saml:NameID',
#        'saml:OneTimeUse',
#        'saml:ProxyRestriction',
#        'saml:Statement',
        'saml:Subject',
        'saml:SubjectConfirmation',
        'saml:SubjectConfirmationData',
        'saml:SubjectLocality',
        'samlp:Artifact',
        'samlp:Extensions',
        'samlp:GetComplete',
        'samlp:IDPList',
        'samlp:NameIDPolicy',
        'samlp:NewEncryptedID',
        'samlp:NewID',
        'samlp:RequestedAuthnContext',
        'samlp:Scoping',
        'samlp:Status',
        'samlp:StatusCode',
        'samlp:StatusDetail',
        'samlp:StatusMessage',
        'samlp:Terminate',
        'xenc:EncryptedData',
        'ds:CanonicalizationMethod',
        'ds:DigestMethod',
        'ds:DigestValue',
        'ds:DSAKeyValue',
        'ds:KeyInfo',
#        'ds:KeyName',
#        'ds:KeyValue',
#        'ds:MgmtData',
#        'ds:PGPData',
#        'ds:RetrievalMethod',
        'ds:RSAKeyValue',
        'ds:Signature',
        'ds:SignatureMethod',
        'ds:SignatureValue',
        'ds:SignedInfo',
#        'ds:SPKIData',
        'ds:Transforms',
#        'ds:X509Data',
        'ec:InclusiveNamespaces',
    ];

    protected static $_multipleValues = [
        'saml:Attribute',
        'saml:EncryptedAttribute',
        'saml:AttributeValue',
        'samlp:IDPEntry',
        'saml:AuthenticatingAuthority',
        'samlp:RequesterID',
        'ds:X509Certificate',
        'ds:Transform',
#        'md:AssertionConsumerService',
        'md:AttributeConsumingService',
        'md:DisplayName',
        'md:EntityDescriptor',
        'md:EncryptionMethod',
        'md:KeyDescriptor',
        'md:NameIDFormat',
        'md:ServiceDescription',
        'md:ServiceName'
    ];

    /**
     * Non static alias function for use in unit testable code
     *
     * @param array $attributes
     * @return array
     */
    public function attributesToArray(array $attributes) {
        if (empty($attributes)) {
            return [];
        }

        $res = [];
        foreach ($attributes as $attribute) {
            // Validate attribute name exists
            if (!isset($attribute['_Name'])) {
                throw new \RuntimeException('Missing attribute name');
            }

            $attributeName = $attribute['_Name'];
            $res[$attributeName] = [];

            // Skip if no attribute values
            if (!isset($attribute['saml:AttributeValue'])) {
                continue;
            }

            $attributeValues = $attribute['saml:AttributeValue'];
            if (!is_array($attributeValues)) {
                throw new \RuntimeException('AttributeValue collection is not an array');
            }

            // Process each attribute value
            foreach ($attributeValues as $value) {
                if (!is_array($value)) {
                    throw new \RuntimeException('AttributeValue is not an array');
                }

                if (isset($value[self::VALUE_PFX])) {
                    $res[$attributeName][] = $value[self::VALUE_PFX];
                }
            }
        }

        return $res;
    }

    public static function xml2array($xml)
    {
        $parser = xml_parser_create_ns();
        if (!xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0)) {
            throw new \RuntimeException(
                'Unable to set XML_OPTION_CASE_FOLDING on parser object? Error message: "' . xml_error_string(xml_get_error_code($parser)) . '"'
            );
        }

        $values = [];
        $parserResultStatus = xml_parse_into_struct($parser, $xml, $values);
        if ($parserResultStatus !== 1) {
            throw new \RuntimeException(
                sprintf(
                    'Error parsing incoming XML. '.PHP_EOL.
                    'Error code: "%s"'.PHP_EOL.
                    'XML: "%s"',
                    xml_error_string(xml_get_error_code($parser)),
                    $xml
                )
            );
        }

        xml_parser_free($parser);
        $singularsBackup = self::$_singulars;
        self::$_singulars = array_fill_keys(self::$_singulars, 1);
        $counter = 0;
        $result = self::xml2arrayRecursive($values, 1, [], $counter);
        self::$_singulars = $singularsBackup;
        return $result[0];
    }

    /**
     * Convert a flat array of entities, begotten from the PHP xml_parser into a hierarchical array recursively.
     *
     * @static
     * @param array $elements
     * @param int   $level
     * @param array $namespaceMapping
     * @return array
     */

    protected static function xml2arrayRecursive(&$elements, $level = 1, $namespaceMapping = [], &$counter = 0)
    {
        $newElement = [];

        while(isset($elements[$counter])) {
            $value = $elements[$counter];
            $counter++;

            // Handle close and cdata types
            if ($value['type'] == 'close') {
                return $newElement;
            }
            if ($value['type'] == 'cdata') {
                continue;
            }

            // Process attributes
            $hashedAttributes = [];
            if (isset($value['attributes'])) {
                foreach($value['attributes'] as $attributeKey => $attributeValue) {
                    $hashedAttributes[self::ATTRIBUTE_PFX . $attributeKey] = $attributeValue;
                }
            }

            // Map namespace and create base element
            $tagName = self::mapNamespacesToSaml($value['tag']);
            $complete = [self::TAG_NAME_PFX => $tagName];

            // Add attributes if they exist
            if (!empty($hashedAttributes)) {
                foreach ($hashedAttributes as $key => $val) {
                    $complete[$key] = $val;
                }
            }

            // Add value if it exists and is not empty
            if (isset($value['value'])) {
                $trimmedValue = trim($value['value']);
                if ($trimmedValue !== '') {
                    $complete[self::VALUE_PFX] = $trimmedValue;
                }
            }

            // Handle open tags recursively
            if ($value['type'] == 'open') {
                $cs = self::xml2arrayRecursive($elements, $level + 1, $namespaceMapping, $counter);
                foreach($cs as $c) {
                    $childTagName = $c[self::TAG_NAME_PFX];
                    unset($c[self::TAG_NAME_PFX]);

                    if (!isset(self::$_singulars[$childTagName])) {
                        $complete[$childTagName][] = $c;
                    } else {
                        $complete[$childTagName] = $c;
                        if (isset($complete[$childTagName][self::TAG_NAME_PFX])) {
                            unset($complete[$childTagName][self::TAG_NAME_PFX]);
                        }
                    }
                }
            }

            $newElement[] = $complete;
        }

        return $newElement;
    }

    /**
     * Maps namespace prefixes to the correct ones as used in saml
     *
     * @param string $tagName
     * @return string
     */
    private static function mapNamespacesToSaml($tagName)
    {
        // find prefix and elementname. Prefix is lookup of the namespace within self::_namespaces
        $fullNamespace =  substr($tagName, 0, strrpos($tagName, ':'));
        if ($fullNamespace != "") {
            // search _namespaces for namespace_prefix
            if (isset(self::$_namespaces[$fullNamespace])) {
                // prefix is found, replaces tagName with prefix:elementName
                $tagName =  self::$_namespaces[$fullNamespace] . ":" . substr($tagName, strrpos($tagName, ':') +1 );
            }
        }

        return $tagName;
    }

    /**
     * Convert a hash (array) to XML.
     *
     * Example:
     * hash2xml(array('book'=>array('_id'=>'1','title'=>array('__v'=>'SAML For beginners'))), 'catalog');
     * Converts to:
     * <catalog><book id='1'><title>SAML For Beginners</title></book></catalog>
     *
     * @static
     * @param array  $hash        Hash/array to convert
     * @param string $elementName Specific element to convert, if empty then the top level element is used
     * @return string XML from array
     */
    public static function array2xml(array $hash, $elementName = "", $useIndentation=false)
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent($useIndentation);
        $writer->setIndentString("    ");

        if (!$elementName) {
            if (isset($hash[self::TAG_NAME_PFX])) {
                $elementName = $hash[self::TAG_NAME_PFX];
            }
            else {
                throw new \RuntimeException("No top level tag provided or defined in hash!");
            }
        }

        self::array2xmlRecursive($hash, $elementName, $writer);

        $writer->endDocument();
        return $writer->outputMemory();
    }

    protected static function array2xmlRecursive($hash, $elementName, \XMLWriter $writer, $level = 0)
    {
        // Early return for placeholders
        if ($hash === self::PLACEHOLDER_VALUE) {
            return;
        }

        // Check recursion level early
        if ($level > self::MAX_RECURSION_LEVEL) {
            throw new \RuntimeException(
                sprintf(
                    'Recursion threshold exceeded on element: "%s" for hash value: "%s"',
                    $elementName,
                    var_export($hash, true)
                )
            );
        }

        // Handle comments if present
        if (is_array($hash) && isset($hash[self::COMMENT_PFX])) {
            $writer->writeComment($hash[self::COMMENT_PFX]);
        }

        // Start element if not a numeric array
        $isNumericArray = isset($hash[0]);
        if (!$isNumericArray) {
            $writer->startElement($elementName);
        }

        foreach ((array)$hash as $key => $value) {
            // Skip private attributes early
            if (strpos($key, self::PRIVATE_PFX) === 0) {
                continue;
            }

            if (is_int($key)) {
                // Recurse for numeric indices
                self::array2xmlRecursive($value, $elementName, $writer, $level + 1);
            } elseif ($key === self::VALUE_PFX) {
                // Write text content
                $writer->text($value);
            } elseif (strpos($key, self::ATTRIBUTE_PFX) === 0) {
                // Write attributes
                $writer->writeAttribute(substr($key, 1), $value);
            } elseif (is_array($value)) {
                // Recurse for array values
                self::array2xmlRecursive($value, $key, $writer, $level + 1);
            } else {
                // Unrecognized value type
                throw new \RuntimeException(
                    sprintf(
                        'Value for key "%s" unrecognized (key naming error?)! Value: "%s"',
                        $key,
                        print_r($value, true)
                    )
                );
            }
        }

        // End element if not a numeric array
        if (!$isNumericArray) {
            $writer->endElement();
        }
    }

    public static function array2attributes($attributes)
    {
        // Early return for empty input
        if (empty($attributes)) {
            return [];
        }

        $res = [];
        $attributes = (array)$attributes;
        
        foreach($attributes as $name => $attribute) {
            // Name must be a uri - check for scheme (colon) as basic validation
            assert((bool) preg_match("|(\\w+)\\:.+|", $name));
            assert(strpos($name, ':') !== false, 'Attribute name must contain a URI scheme');
            $newAttribute = [
                '_Name' => $name,
                '_NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
            ];
            foreach ((array)$attribute as $value) {
                $newAttribute['saml:AttributeValue'][] = is_array($value) 
                    ? $value 
                    : [self::VALUE_PFX => $value];
            }
            $res[] = $newAttribute;
        }
        return $res;
    }

    /**
     * Format XML, adds newlines and whitespace.
     *
     * @static
     * @param string $xml Unformatted XML
     * @return string Formatted XML
     */
    public static function formatXml($xml)
    {
        // Early return for empty input
        if (empty(trim($xml))) {
            return $xml;
        }

        // Use DOMDocument for reliable XML formatting
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        // Suppress warnings for malformed XML - let the function handle it gracefully
        $previousErrorHandling = libxml_use_internal_errors(true);
        
        try {
            // Load the XML
            $loaded = $dom->loadXML($xml);
            
            if (!$loaded) {
                // Fallback to simple formatting if DOMDocument fails
                return self::simpleFormatXmlFallback($xml);
            }
            
            // Get formatted XML
            $formattedXml = $dom->saveXML();
            
            // Clean up empty lines that DOMDocument might leave
            $formattedXml = preg_replace('/^\s+$/m', '', $formattedXml);
            
            return $formattedXml;
        } finally {
            // Restore previous error handling
            libxml_use_internal_errors($previousErrorHandling);
        }
    }

    /**
     * Fallback XML formatter using simple regex-based approach
     * Used when DOMDocument fails to parse the XML
     *
     * @param string $xml
     * @return string
     */
    private static function simpleFormatXmlFallback($xml)
    {
        // Add line breaks between tags
        $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);
        
        // Remove empty lines
        $xml = preg_replace('/\n+/', "\n", $xml);
        $xml = trim($xml);
        
        $lines = explode("\n", $xml);
        $result = '';
        $indentLevel = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Calculate indentation
            $indent = str_repeat('    ', $indentLevel);
            
            // Check if line contains opening or closing tags
            if (preg_match('/^<\w/', $line) && !preg_match('/^<\//', $line)) {
                // Opening tag - current line gets current indent, next lines get more
                $result .= $indent . $line . "\n";
                if (!preg_match('/\/>$/', $line)) { // Not self-closing
                    $indentLevel++;
                }
            } elseif (preg_match('/^<\//', $line)) {
                // Closing tag - reduce indent after this line
                $indentLevel = max(0, $indentLevel - 1);
                $result .= $indent . $line . "\n";
            } else {
                // Other content
                $result .= $indent . $line . "\n";
            }
        }
        
        return $result;
    }
}
// @codingStandardsIgnoreEnd
