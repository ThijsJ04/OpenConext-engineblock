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

namespace OpenConext\EngineBlockFunctionalTestingBundle\Mock;

use SAML2\AuthnRequest;
use SAML2\Constants;
use SAML2\XML\md\EntityDescriptor;
use SAML2\XML\md\IndexedEndpointType;
use SAML2\XML\md\SPSSODescriptor;
use SAML2\XML\saml\Issuer;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class MockServiceProviderFactory
 * @package OpenConext\EngineBlockFunctionalTestingBundle\Service
 */
class MockServiceProviderFactory extends AbstractMockEntityFactory
{
    protected $router;

    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function createNew($spName)
    {
        $descriptor = $this->generateDefaultEntityMetadata($spName);

        $mockSp = new MockServiceProvider($spName, $descriptor);

        $mockSp->setAuthnRequest($this->generateDefaultAuthnRequest($mockSp));

        return $mockSp;
    }

    protected function generateDefaultEntityMetadata($spName)
    {
        // Generate URLs more efficiently by reusing the route parameters
        $routeParams = ['spName' => $spName];
        
        $descriptor = new EntityDescriptor();
        $descriptor->setEntityID(
            $this->router->generate('functional_testing_sp_metadata', $routeParams, RouterInterface::ABSOLUTE_URL)
        );

        // Configure ACS service with method chaining
        $acsService = (new IndexedEndpointType())
            ->setIndex(0)
            ->setBinding(Constants::BINDING_HTTP_POST)
            ->setLocation(
                $this->router->generate('functional_testing_sp_acs', $routeParams, RouterInterface::ABSOLUTE_URL)
            );

        // Configure SP SSO descriptor with method chaining
        $spSsoDescriptor = (new SPSSODescriptor())
            ->setProtocolSupportEnumeration([Constants::NS_SAMLP])
            ->setAssertionConsumerService([$acsService])
            ->setKeyDescriptor([$this->generateDefaultSigningKeyPair()]);

        $descriptor->setRoleDescriptor([$spSsoDescriptor]);

        // Generate extensions more efficiently
        $descriptor->setExtensions([
            'LoginRedirectUrl' => $this->router->generate('functional_testing_sp_login_redirect', $routeParams, RouterInterface::ABSOLUTE_URL),
            'LoginPostUrl' => $this->router->generate('functional_testing_sp_login_post', $routeParams, RouterInterface::ABSOLUTE_URL),
        ]);

        return $descriptor;
    }

    private function generateDefaultAuthnRequest(MockServiceProvider $mockSp)
    {
        $request = new AuthnRequest();
        $issuer = new Issuer();
        $issuer->setValue($mockSp->entityId());
        $request->setIssuer($issuer);
        return $request;
    }
}
