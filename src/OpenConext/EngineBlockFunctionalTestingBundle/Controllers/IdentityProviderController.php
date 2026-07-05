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

namespace OpenConext\EngineBlockFunctionalTestingBundle\Controllers;

use OpenConext\EngineBlockFunctionalTestingBundle\Mock\EntityRegistry;
use OpenConext\EngineBlockFunctionalTestingBundle\Mock\MockIdentityProvider;
use RuntimeException;
use SAML2\AuthnRequest;
use SAML2\HTTPPost;
use SAML2\HTTPRedirect;
use SAML2\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenConext\EngineBlockFunctionalTestingBundle\Saml2\ResponseFactory;
use OpenConext\EngineBlockFunctionalTestingBundle\Saml2\Compat\Container;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) just does a lot of manual lifting :(
 */
class IdentityProviderController extends AbstractController
{
    /**
     * @var EntityRegistry
     */
    private $mockIdpRegistry;
    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    public function __construct(EntityRegistry $mockIdpRegistry, ResponseFactory $responseFactory)
    {
        $this->mockIdpRegistry = $mockIdpRegistry;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @param $idpName
     * @return Response
     */
    public function metadataAction($idpName)
    {
        $entityDescriptor = $this->mockIdpRegistry->get($idpName)->getEntityDescriptor();

        return new Response(
            $entityDescriptor->toXML()->ownerDocument->saveXML(),
            200,
            ['Content-Type' => 'application/xml']
        );
    }

    /**
     * @param Request $request
     * @param string $idpName
     * @return Response
     * @throws RuntimeException
     */
    public function singleSignOnAction(Request $request, string $idpName): Response
    {
        // Handle HTTP method and receive message
        $message = $request->isMethod('GET') 
            ? (new HTTPRedirect())->receive()
            : (new HTTPPost())->receive();

        if (!$message instanceof AuthnRequest) {
            throw new RuntimeException(sprintf('Unknown message type: "%s"', get_class($message)));
        }

        $mockIdp = $this->mockIdpRegistry->get($idpName);
        $response = $this->responseFactory->createForEntityWithRequest($mockIdp, $message);

        // Set destination using null coalescing operator for cleaner code
        $destination = $message->getAssertionConsumerServiceURL() ?? $response->getDestination();
        $response->setDestination($destination);

        // Handle redirect if required
        if ($mockIdp->mustUseHttpRedirect()) {
            $redirect = new HTTPRedirect();
            $redirect->setDestination($destination);
            return new RedirectResponse($redirect->getRedirectURL($response));
        }

        // Handle POST response
        $container = Utils::getContainer();
        $container->postRedirect(
            $destination,
            [
                'authnRequestXml' => htmlentities($container->getLastDebugMessageOfType(Container::DEBUG_TYPE_IN)),
                'SAMLResponse' => base64_encode($response->toXml()),
            ]
        );

        return $container->getPostResponse();
    }
}
