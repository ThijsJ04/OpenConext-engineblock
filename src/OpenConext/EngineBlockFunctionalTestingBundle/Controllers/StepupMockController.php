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

use Exception;
use OpenConext\EngineBlockFunctionalTestingBundle\Mock\MockStepupGateway;
use SAML2\Constants;
use SAML2\HTTPRedirect;
use SAML2\Response as SamlResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twig\Environment;

class StepupMockController extends AbstractController
{
    /**
     * @var MockStepupGateway
     */
    private $mockStepupGateway;
    /**
     * @var Environment
     */
    private $twig;

    public function __construct(MockStepupGateway $mockStepupGateway, Environment $twig)
    {
        $this->mockStepupGateway = $mockStepupGateway;
        $this->twig = $twig;
    }

    /**
     * @param Request $request
     * @return string|Response
     */
    public function ssoAction(Request $request)
    {
        // Validate request method
        if (!$request->isMethod(Request::METHOD_GET)) {
            return new Response(sprintf(
                'Could not receive AuthnRequest from HTTP Request: expected a GET method, got %s',
                $request->getMethod()
            ), Response::HTTP_BAD_REQUEST);
        }

        try {
            // Cache the full request URI to avoid repeated calculations
            $fullRequestUri = $this->getFullRequestUri($request);
            
            // Parse available responses
            $responses = $this->getAvailableResponses($request, $fullRequestUri);

            $redirectBinding = new HTTPRedirect();
            $message = $redirectBinding->receive();

            // Present response
            return new Response($this->twig->render(
                '@OpenConextEngineBlockFunctionalTesting/Sso/consumeAssertion.html.twig',
                [
                    'receivedAuthnRequest' => $message->toUnsignedXML()->ownerDocument->saveXml(),
                    'responses' => $responses,
                ]
            ));
        } catch (Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param Request $request
     * @param string $fullRequestUri
     * @return array
     * @throws Exception
     */
    private function getAvailableResponses(Request $request, string $fullRequestUri)
    {
        $results = [];

        // Parse successful loa3
        $results['success'] = $this->getResponseData(
            $request,
            $this->mockStepupGateway->handleSsoSuccess($request, $fullRequestUri)
        );

        // Parse successful loa3 with changed audience
        $results['success-audience'] = $this->getResponseData(
            $request,
            $this->mockStepupGateway->handleSsoSuccess($request, $fullRequestUri, true)
        );

        // Parse successful loa2
        $results['loa2'] = $this->getResponseData(
            $request,
            $this->mockStepupGateway->handleSsoSuccessLoa2($request, $fullRequestUri)
        );

        // Parse user cancelled
        $results['user-cancelled'] = $this->getResponseData(
            $request,
            $this->mockStepupGateway->handleSsoFailure(
                $request,
                $fullRequestUri,
                Constants::STATUS_RESPONDER,
                Constants::STATUS_AUTHN_FAILED,
                'Authentication cancelled by user'
            )
        );

        // Parse unmet Loa
        $results['unmet-loa'] = $this->getResponseData(
            $request,
            $this->mockStepupGateway->handleSsoFailure(
                $request,
                $fullRequestUri,
                Constants::STATUS_RESPONDER,
                Constants::STATUS_NO_AUTHN_CONTEXT
            )
        );

        // Parse unknown
        $results['unknown'] = $this->getResponseData(
            $request,
            $this->mockStepupGateway->handleSsoFailure(
                $request,
                $fullRequestUri,
                Constants::STATUS_RESPONDER,
                Constants::STATUS_AUTHN_FAILED
            )
        );

        return $results;
    }

    /**
     * @param Request $request
     * @param SamlResponse $samlResponse
     * @return array
     */
    private function getResponseData(Request $request, SamlResponse $samlResponse): array
    {
        $rawResponse = $this->mockStepupGateway->parsePostResponse($samlResponse);
        $encodedResponse = base64_encode($rawResponse);

        return [
            'acu' => $samlResponse->getDestination(),
            'rawResponse' => $rawResponse,
            'encodedResponse' => $encodedResponse,
            'relayState' => $request->request->get(MockStepupGateway::PARAMETER_RELAY_STATE),
        ];
    }

    /**
     * @param Request $request
     * @return string
     */
    private function getFullRequestUri(Request $request)
    {
        return $request->getSchemeAndHttpHost() . $request->getBasePath() . $request->getRequestUri();
    }
}
