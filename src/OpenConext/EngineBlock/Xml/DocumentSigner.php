<?php declare(strict_types=1);

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

namespace OpenConext\EngineBlock\Xml;

use DOMDocument;
use DOMElement;
use OpenConext\EngineBlock\Exception\RuntimeException;
use OpenConext\EngineBlock\Metadata\X509\X509KeyPair;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

class DocumentSigner
{
    const SIGN_ALGORITHM = XMLSecurityDSig::SHA256;

    public function sign(string $source, X509KeyPair $signingKeyPair) : string
    {
        $doc = new DOMDocument();
        $doc->loadXML($source);

        // Find root element to sign, skipping the TOS comment (first child)
        $rootNode = $doc->childNodes[1] ?? null;
        if (!$rootNode instanceof DOMElement) {
            throw new RuntimeException("Could not locate root element to sign");
        }

        // Configure and execute signing
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        
        $objDSig->addReference(
            $rootNode,
            self::SIGN_ALGORITHM,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N],
            ['id_name' => 'ID', 'overwrite' => false]
        );

        // Load private key and sign
        $privateKey = $signingKeyPair->getPrivateKey();
        $objKey = $privateKey->toXmlSecurityKey();
        $objKey->loadKey($privateKey->getFilePath(), true);
        $objDSig->sign($objKey);

        // Add public certificate and insert signature
        $objDSig->add509Cert($signingKeyPair->getCertificate()->toPem());
        $objDSig->insertSignature($doc->documentElement, $doc->documentElement->firstChild);

        return $doc->saveXML();
    }
}
