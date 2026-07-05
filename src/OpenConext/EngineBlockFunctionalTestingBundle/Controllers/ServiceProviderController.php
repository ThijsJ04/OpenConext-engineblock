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

use DOMDocument;
use OpenConext\EngineBlockFunctionalTestingBundle\Mock\EntityRegistry;
use OpenConext\EngineBlockFunctionalTestingBundle\Mock\MockServiceProvider;
use OpenConext\EngineBlockFunctionalTestingBundle\Saml2\AuthnRequestFactory;
use OpenConext\EngineBlockFunctionalTestingBundle\Saml2\Compat\Container;
use OpenConext\EngineBlockFunctionalTestingBundle\Service\EngineBlock;
use SAML2\HTTPPost;
use SAML2\HTTPRedirect;
use SAML2\Response as SAMLResponse;
use SAML2\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @package OpenConext\EngineBlockFunctionalTestingBundle\Controllers
 * @SuppressWarnings("PMD")
 */
class ServiceProviderController extends AbstractController
{
    /**
     * @var EntityRegistry
     */
    private $mockSpRegistry;

    /**
     * @var EngineBlock
     */
    private $engineBlock;

    public function __construct(EntityRegistry $spRegistry, EngineBlock $engineBlock)
    {
        $this->mockSpRegistry = $spRegistry;
        $this->engineBlock = $engineBlock;
    }

    /**
     * @param $spName
     * @return RedirectResponse
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    public function triggerLoginRedirectAction($spName)
    {
        if (!$this->mockSpRegistry->has($spName)) {
            throw new BadRequestHttpException(sprintf('No SP found for "%s"', $spName));
        }

        /** @var MockServiceProvider $serviceProvider */
        $serviceProvider = $this->mockSpRegistry->get($spName);

        $factory = new AuthnRequestFactory();
        $authenticationRequest = $factory->createForRequestFromTo(
            $serviceProvider,
            $this->engineBlock
        );

        $redirectUrl = (new HTTPRedirect())->getRedirectURL($authenticationRequest);

        if ($this->shouldUseMalformedRequestParameter($serviceProvider)) {
            $redirectUrl = str_replace('SAMLRequest', 'AuthNRequest', $redirectUrl);
        }

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param MockServiceProvider $serviceProvider
     * @return bool
     */
    private function shouldUseMalformedRequestParameter(MockServiceProvider $serviceProvider)
    {
        return isset($serviceProvider->getEntityDescriptor()->getExtensions()['Malformed']);
    }

    /**
     * @param $spName
     * @return Response
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    public function triggerLoginPostAction($spName)
    {
        if (!$this->mockSpRegistry->has($spName)) {
            throw new BadRequestHttpException(sprintf('No SP found for "%s"', $spName));
        }

        $serviceProvider = $this->mockSpRegistry->get($spName);
        $factory = new AuthnRequestFactory();
        $authnRequest = $factory->createForRequestFromTo($serviceProvider, $this->engineBlock);

        $httpPost = new HTTPPost();
        $httpPost->send($authnRequest);

        $response = Utils::getContainer()->getPostResponse();

        if ($this->shouldUseMalformedRequestParameter($serviceProvider)) {
            $response->setContent(str_replace('SAMLRequest', 'AuthNRequest', $response->getContent()));
        }

        return $response;
    }

    /**
     * @param Request $request
     * @return Response
     * @throws \RuntimeException
     */
    public function assertionConsumerAction(Request $request)
    {
        $bindings = [new HTTPPost(), new HTTPRedirect()];
        $message = null;
        $lastException = null;

        foreach ($bindings as $binding) {
            try {
                $message = $binding->receive();
                break;
            } catch (\Exception $e) {
                $lastException = $e;
            }
        }

        if ($message === null) {
            throw new \RuntimeException('Unable to retrieve SAML message?', 1, $lastException);
        }

        if (!$message instanceof SAMLResponse) {
            throw new \RuntimeException(sprintf('Unrecognized message type received: "%s"', get_class($message)));
        }

        $xml = base64_decode($request->get('SAMLResponse'));

        // Format the XML
        $doc = new DomDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadXML($xml);
        $xml = $doc->saveXML();

        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * @param $spName
     * @return Response
     */
    public function metadataAction($spName)
    {
        /** @var MockServiceProvider $mockSp */
        $mockSp = $this->mockSpRegistry->get($spName);

        return new Response(
            $mockSp->getEntityDescriptor()->toXML()->ownerDocument->saveXML(),
            200,
            ['Content-Type' => 'application/xml']
        );
    }
}
