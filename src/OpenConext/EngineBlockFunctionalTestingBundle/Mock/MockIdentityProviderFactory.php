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

use DOMDocument;
use OpenConext\EngineBlockFunctionalTestingBundle\Saml2\Response;
use SAML2\Compat\ContainerSingleton;
use SAML2\Constants;
use SAML2\XML\md\EntityDescriptor;
use SAML2\XML\md\IDPSSODescriptor;
use SAML2\XML\md\IndexedEndpointType;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class MockIdentityProviderFactory
 * @package OpenConext\EngineBlockFunctionalTestingBundle\Service
 */
class MockIdentityProviderFactory extends AbstractMockEntityFactory
{
    protected $router;

    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @param $idpName
     * @return MockIdentityProvider
     */
    public function createNew($idpName)
    {
        $mockIdp = new MockIdentityProvider($idpName, $this->generateDefaultEntityMetadata($idpName));
        $mockIdp->signAssertions();
        $mockIdp->setResponse($this->generateDefaultResponse($mockIdp));
        return $mockIdp;
    }

    /**
     * @param string $idpName
     * @return EntityDescriptor
     */
    protected function generateDefaultEntityMetadata($idpName)
    {
        $entityMetadata = new EntityDescriptor();
        $entityMetadata->setEntityID(
            $this->router->generate(
                'functional_testing_idp_metadata',
                ['idpName' => $idpName],
                RouterInterface::ABSOLUTE_URL
            )
        );

        $acsService = (new IndexedEndpointType())
            ->setIndex(0)
            ->setBinding(Constants::BINDING_HTTP_REDIRECT)
            ->setLocation(
                $this->router->generate(
                    'functional_testing_idp_sso',
                    ['idpName' => $idpName],
                    RouterInterface::ABSOLUTE_URL
                )
            );

        $idpSsoDescriptor = (new IDPSSODescriptor())
            ->setProtocolSupportEnumeration([Constants::NS_SAMLP])
            ->setSingleSignOnService([0 => $acsService])
            ->setKeyDescriptor([$this->generateDefaultSigningKeyPair()]);

        $entityMetadata->setRoleDescriptor([$idpSsoDescriptor]);

        return $entityMetadata;
    }

    private function generateDefaultResponse(MockIdentityProvider $mockIdp)
    {
        $idpEntityId = $mockIdp->entityId();
        $container = ContainerSingleton::getInstance();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $tomorrow = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 day'));
        $timestamp = time();

        $document = new DOMDocument();
        $document->loadXML(sprintf(
            '<samlp:Response
              xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
              xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
              ID="%s"
              IssueInstant="%s"
              InResponseTo="FIXME"
              Version="2.0">
                <saml:Issuer>%s</saml:Issuer>
                <samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success" /></samlp:Status>
                <saml:Assertion IssueInstant="%s" Version="2.0" ID="%s">
                    <saml:Issuer>%s</saml:Issuer>
                    <saml:Subject>
                        <saml:NameID>ETS-MOCK-IDP-%s</saml:NameID>
                        <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
                            <saml:SubjectConfirmationData
                              NotOnOrAfter="%s"
                              InResponseTo="FIXME" />
                        </saml:SubjectConfirmation>
                    </saml:Subject>
                    <saml:AuthnStatement AuthnInstant="%s">
                        <saml:AuthnContext>
                            <saml:AuthnContextClassRef>
                                urn:oasis:names:tc:SAML:2.0:ac:classes:Password
                            </saml:AuthnContextClassRef>
                        </saml:AuthnContext>
                    </saml:AuthnStatement>
                    <saml:AttributeStatement>
                        <saml:Attribute Name="urn:mace:dir:attribute-def:uid">
                            <saml:AttributeValue>test%s%s</saml:AttributeValue>
                        </saml:Attribute>
                        <saml:Attribute Name="urn:mace:terena.org:attribute-def:schacHomeOrganization">
                            <saml:AttributeValue>engine-test-stand.openconext.org</saml:AttributeValue>
                        </saml:Attribute>
                    </saml:AttributeStatement>
                </saml:Assertion>
            </samlp:Response>',
            $container->generateId(),
            $now,
            $idpEntityId,
            $now,
            $container->generateId(),
            $idpEntityId,
            $timestamp,
            $tomorrow,
            $now,
            $timestamp,
            random_int(10000, 99999)
        ));

        return new Response($document->firstChild);
    }
}
