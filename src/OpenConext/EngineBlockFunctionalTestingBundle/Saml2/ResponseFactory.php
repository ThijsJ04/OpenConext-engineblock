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
        // Note that we expect the Mock IdP to always have a 'template' Response.
        $response = $mockIdp->getResponse();

        $this->setResponseReferencesToRequest($request, $response);
        $this->setResponseStatus($mockIdp, $response);
        $this->setResponseSignatureKey($mockIdp, $response);
        $this->setResponseIssuer($mockIdp, $response);
        $this->encryptAssertions($mockIdp, $response);

        $this->handleAssertionModifications($mockIdp, $response);

        return $response;
    }

    /**
     * Handle assertion modifications based on MockIdentityProvider settings.
     * 
     * @param MockIdentityProvider $mockIdp
     * @param Response $response
     */
    private function handleAssertionModifications(MockIdentityProvider $mockIdp, Response $response)
    {
        if ($mockIdp->shouldNotSendAssertions()) {
            $response->setAssertions([]);
            return;
        }

        $assertions = $response->getAssertions();
        if (empty($assertions)) {
            return;
        }

        if ($mockIdp->shouldTurnBackTheTime()) {
            $assertions[0]->setNotOnOrAfter(0);
        }

        if ($mockIdp->isFromTheFuture()) {
            $assertions[0]->setNotBefore(strtotime('+1 year'));
        }
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
        $statusOverride = [];
        $hasChanges = false;

        $mockIdpTopStatusCode = $mockIdp->getStatusCodeTop();
        if (!empty($mockIdpTopStatusCode)) {
            $statusOverride['Code'] = $mockIdpTopStatusCode;
            $hasChanges = true;
        }

        $mockIdpSubStatusCode = $mockIdp->getStatusCodeSecond();
        if (!empty($mockIdpSubStatusCode)) {
            $statusOverride['SubCode'] = $mockIdpSubStatusCode;
            $hasChanges = true;
        } elseif (isset($responseStatus['SubCode'])) {
            $statusOverride['SubCode'] = $responseStatus['SubCode'];
        }

        $mockIdpStatusMessage = $mockIdp->getStatusMessage();
        if ($mockIdpStatusMessage !== null) {
            $statusOverride['Message'] = $mockIdpStatusMessage;
            $hasChanges = true;
        } elseif (isset($responseStatus['Message'])) {
            $statusOverride['Message'] = $responseStatus['Message'];
        }

        if ($hasChanges) {
            $response->setStatus($statusOverride);
        }
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
            foreach ($response->getAssertions() as $assertion) {
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
        if (empty($assertions)) {
            return;
        }

        $encryptedAssertions = array_map(function ($assertion) use ($encryptionKey) {
            $encryptedAssertion = new EncryptedAssertion();
            $encryptedAssertion->setAssertion($assertion, $encryptionKey);
            return $encryptedAssertion;
        }, $assertions);

        $response->setAssertions($encryptedAssertions);
    }
}
