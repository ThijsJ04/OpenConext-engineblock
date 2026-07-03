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

namespace OpenConext\EngineBlockFunctionalTestingBundle\Saml2;

use OpenConext\EngineBlockFunctionalTestingBundle\Mock\MockIdentityProvider;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SAML2\AuthnRequest as SAMLAuthnRequest;
use SAML2\EncryptedAssertion;
use SAML2\XML\saml\Issuer;
use SAML2\XML\saml\SubjectConfirmation;

class ResponseFactory
{
public function createForEntityWithRequest(
    MockIdentityProvider $mockIdp,
    SAMLAuthnRequest $request
) {
    $response = $mockIdp->getResponse();

    $this->setResponseReferencesToRequest($request, $response);
    $this->setResponseStatus($mockIdp, $response);
    $this->setResponseSignatureKey($mockIdp, $response);
    $this->setResponseIssuer($mockIdp, $response);

    if (!$mockIdp->shouldNotSendAssertions()) {
        $this->encryptAssertions($mockIdp, $response);

        if ($mockIdp->shouldTurnBackTheTime()) {
            $response->getAssertions()[0]->setNotOnOrAfter(0);
        }

        if ($mockIdp->isFromTheFuture()) {
            $response->getAssertions()[0]->setNotBefore(strtotime('+1 year'));
        }
    } else {
        $response->setAssertions([]);
    }

    return $response;
}

    /**
     * @param SAMLAuthnRequest $request
     * @param $response
     */
    private function setResponseReferencesToRequest(SAMLAuthnRequest $request, Response $response)
    {
        $response->setInResponseTo($request->getId());
        $assertions = $response->getAssertions();
        /** @var SubjectConfirmation[] $subjectConfirmations */
        $subjectConfirmations = $assertions[0]->getSubjectConfirmation();

        foreach ($subjectConfirmations as $subjectConfirmation) {
            $subjectConfirmation->getSubjectConfirmationData()->setInResponseTo($request->getId());
        }

        $assertions[0]->setSubjectConfirmation($subjectConfirmations);
    }

    /**
     * @param MockIdentityProvider $mockIdp
     * @param Response $response
     */
private function setResponseStatus(MockIdentityProvider $mockIdp, Response $response)
{
    $responseStatus = $response->getStatus();
    $statusOverride = [
        'Code' => $mockIdp->getStatusCodeTop(),
        'SubCode' => $mockIdp->getStatusCodeSecond() ?: $responseStatus['SubCode'],
        'Message' => $mockIdp->getStatusMessage() ?? $responseStatus['Message']
    ];

    $response->setStatus($statusOverride);
}

    /**
     * @param MockIdentityProvider $mockIdp
     * @param $response
     */
    private function setResponseSignatureKey(MockIdentityProvider $mockIdp, Response $response)
    {
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($mockIdp->getPrivateKeyPem());

        if ($mockIdp->mustSignResponses()) {
            $response->setSignatureKey($key);
        }

        if ($mockIdp->mustSignAssertions()) {
            $assertions = $response->getAssertions();
            foreach ($assertions as $assertion) {
                $assertion->setSignatureKey($key);
            }
        }
    }

    private function setResponseIssuer(MockIdentityProvider $mockIdp, Response $response)
    {
        $issuer = new Issuer();
        $issuer->setValue($mockIdp->entityId());
        $response->setIssuer($issuer);
    }

private function encryptAssertions(MockIdentityProvider $mockIdp, Response $response)
{
    $encryptionKey = $mockIdp->getEncryptionKey();
    if (!$encryptionKey) {
        return;
    }

    $assertions = $response->getAssertions();
    $encryptedAssertions = array_map(function($assertion) use ($encryptionKey) {
        $encryptedAssertion = new EncryptedAssertion();
        $encryptedAssertion->setAssertion($assertion, $encryptionKey);
        return $encryptedAssertion;
    }, $assertions);

    $response->setAssertions($encryptedAssertions);
}
}
