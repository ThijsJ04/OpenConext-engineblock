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

namespace OpenConext\EngineBlockBundle\Controller\Api;

use OpenConext\EngineBlock\Exception\RuntimeException;
use OpenConext\EngineBlock\Http\Exception\AccessDeniedException;
use OpenConext\EngineBlock\Service\ConsentServiceInterface;
use OpenConext\EngineBlockBundle\Configuration\FeatureConfigurationInterface;
use OpenConext\EngineBlockBundle\Factory\CollabPersonIdFactory;
use OpenConext\EngineBlockBundle\Http\Exception\ApiAccessDeniedHttpException;
use OpenConext\EngineBlockBundle\Http\Exception\ApiInternalServerErrorHttpException;
use OpenConext\EngineBlockBundle\Http\Exception\ApiMethodNotAllowedHttpException;
use OpenConext\EngineBlockBundle\Http\Exception\ApiNotFoundHttpException;
use OpenConext\EngineBlockBundle\Http\Exception\BadApiRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

use function array_key_exists;
use function sprintf;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class ConsentController
{
    /**
     * @var ConsentServiceInterface
     */
    private $consentService;

    /**
     * @var FeatureConfigurationInterface
     */
    private $featureConfiguration;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var AccessDecisionManagerInterface
     */
    private $accessDecisionManager;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AccessDecisionManagerInterface $accessDecisionManager,
        FeatureConfigurationInterface $featureConfiguration,
        ConsentServiceInterface $consentService
    ) {
        $this->tokenStorage                    = $tokenStorage;
        $this->accessDecisionManager           = $accessDecisionManager;
        $this->featureConfiguration = $featureConfiguration;
        $this->consentService       = $consentService;
    }

    /**
     * @Route("/consent/{userId}", name="api_consent_user", defaults={"_format"="json"})
     */
    public function userAction($userId, Request $request)
    {
        if ($request->getMethod() !== Request::METHOD_GET) {
            throw ApiMethodNotAllowedHttpException::methodNotAllowed($request->getMethod(), [Request::METHOD_GET]);
        }

        if (!$this->featureConfiguration->isEnabled('eb.feature_enable_consent') || 
            !$this->featureConfiguration->isEnabled('api.consent_listing')) {
            throw new ApiNotFoundHttpException('Consent feature or API is disabled');
        }

        $this->assertAuthorized();

        try {
            $consentList = $this->consentService->findAllFor($userId)->jsonSerialize();
        } catch (RuntimeException $e) {
            throw new ApiInternalServerErrorHttpException(
                'An unknown error occurred while fetching consent list: ' . $e->getMessage(),
                $e
            );
        }

        return new JsonResponse($consentList, Response::HTTP_OK);
    }

    /**
     * @Route("/remove-consent", name="api_remove_consent_user", defaults={"_format"="json"})
     */
    public function removeAction(Request $request): JsonResponse
    {
        if ($request->getMethod() !== Request::METHOD_POST) {
            throw ApiMethodNotAllowedHttpException::methodNotAllowed($request->getMethod(), [Request::METHOD_POST]);
        }

        if (!$this->featureConfiguration->isEnabled('eb.feature_enable_consent') ||
            !$this->featureConfiguration->isEnabled('api.consent_remove')) {
            throw new ApiNotFoundHttpException('Consent feature or remove API is disabled');
        }

        $this->assertAuthorized();

        // Validate and decode JSON data
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['collabPersonId'], $data['serviceProviderEntityId'])) {
            return new JsonResponse('Required consent removal data is missing or invalid', Response::HTTP_FOUND);
        }

        $userId = $data['collabPersonId'];
        $serviceProviderEntityId = $data['serviceProviderEntityId'];

        try {
            $user = CollabPersonIdFactory::create($userId);
            $removed = $this->consentService->deleteOneConsentFor($user, $serviceProviderEntityId);
        } catch (RuntimeException $e) {
            throw new ApiInternalServerErrorHttpException(
                'Failed to remove consent: ' . $e->getMessage(),
                $e
            );
        }

        return new JsonResponse($removed, Response::HTTP_OK);
    }

    private function assertAuthorized(): void
    {
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            throw new AuthenticationCredentialsNotFoundException('The token storage contains no authentication token.');
        }

        if (!$this->accessDecisionManager->decide($token, ['ROLE_API_USER_PROFILE'], null)) {
            throw new ApiAccessDeniedHttpException(
                'Access to the content API requires the role ROLE_API_USER_PROFILE'
            );
        }
    }
}
