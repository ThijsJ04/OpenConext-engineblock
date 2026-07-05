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

namespace OpenConext\EngineBlockBundle\Twig\Extensions\Extension;

use EngineBlock_ApplicationSingleton;
use EngineBlock_Saml2_ResponseAnnotationDecorator;
use OpenConext\EngineBlock\Metadata\Entity\IdentityProvider;
use OpenConext\EngineBlock\Metadata\MetadataRepository\MetadataRepositoryInterface;
use OpenConext\EngineBlockBundle\Authentication\Service\SamlResponseHelper;
use OpenConext\EngineBlockBundle\Configuration\ErrorFeedbackConfigurationInterface;
use OpenConext\EngineBlockBundle\Configuration\WikiLink;
use OpenConext\EngineBlockBundle\Value\FeedbackInformation;
use OpenConext\EngineBlockBundle\Value\FeedbackInformationMap;
use SAML2\XML\saml\Issuer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Feedback extends AbstractExtension
{
    /**
     * @var EngineBlock_ApplicationSingleton
     */
    private $application;

    /**
     * @var ErrorFeedbackConfigurationInterface
     */
    private $errorFeedbackConfiguration;

    /**
     * @var MetadataRepositoryInterface
     */
    private $metadataRepository;

    /**
     * @var SamlResponseHelper
     */
    private $samlResponseHelper;

    public function __construct(
        EngineBlock_ApplicationSingleton $application,
        ErrorFeedbackConfigurationInterface $errorFeedbackConfiguration,
        MetadataRepositoryInterface $metadataRepository,
        SamlResponseHelper $samlResponseHelper
    ) {
        $this->application = $application;
        $this->errorFeedbackConfiguration = $errorFeedbackConfiguration;
        $this->metadataRepository = $metadataRepository;
        $this->samlResponseHelper = $samlResponseHelper;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('feedbackInfo', [$this, 'getFeedbackInfo']),
            new TwigFunction('flushLog', [$this, 'flushLog']),
            new TwigFunction('hasBackToSpLink', [$this, 'hasBackToSpLink']),
            new TwigFunction('hasWikiLink', [$this, 'hasWikiLink']),
            new TwigFunction('getWikiLink', [$this, 'getWikiLink']),
            new TwigFunction('hasIdPContactMailLink', [$this, 'hasIdPContactMailLink']),
            new TwigFunction('getIdPContactMailLink', [$this, 'getIdPContactMailLink']),
            new TwigFunction('getIdpContactShortLabel', [$this, 'getIdpContactShortLabel']),
            new TwigFunction('getSpName', [$this, 'getSpName']),
            new TwigFunction('getAcu', [$this, 'getAcu']),
            new TwigFunction('getSamlFailedResponse', [$this, 'getSamlFailedResponse']),
        ];
    }

    public function flushLog($message)
    {
        // For now use the EngineBlock_ApplicationSingleton to flush the log
        $this->application->flushLog($message);
    }

    /**
     * @return FeedbackInformationMap
     */
    public function getFeedbackInfo()
    {
        return $this->retrieveFeedbackInfo();
    }

    /**
     * @param string $templateName
     * @return bool
     */
    public function hasWikiLink($templateName)
    {
        return $this->errorFeedbackConfiguration->hasWikiLink($templateName);
    }

    /**
     * @param string $templateName
     * @return string
     */
    public function getWikiLink($templateName)
    {
        return $this->errorFeedbackConfiguration->getWikiLink($templateName);
    }

    /**
     * @param string $templateName
     * @return bool
     */
    public function hasIdPContactMailLink($templateName)
    {
        return $this->errorFeedbackConfiguration->isIdPContactPage($templateName) && $this->getIdPContactMailLink();
    }

    /**
     * @param string $templateName
     * @return string
     */
    public function getIdpContactShortLabel($templateName)
    {
        return $this->errorFeedbackConfiguration->getIdpContactShortLabel($templateName);
    }

    /**
     * @return string
     */
    public function getIdPContactMailLink()
    {
        $session = $this->application->getSession();
        $feedbackInfo = $session->get('feedbackInfo');
        
        // Early return if no identity provider in feedback info
        if (empty($feedbackInfo['identityProvider'])) {
            return '';
        }
        
        /** @var IdentityProvider $idp */
        $idp = $this->metadataRepository->findIdentityProviderByEntityId($feedbackInfo['identityProvider']);
        
        if (!$idp || empty($idp->contactPersons)) {
            return '';
        }
        
        // Find support contact with email address
        foreach ($idp->contactPersons as $contactPerson) {
            if ($contactPerson->contactType === 'support' && !empty($contactPerson->emailAddress)) {
                return $contactPerson->emailAddress;
            }
        }
        
        return '';
    }

    public function hasBackToSpLink(): bool
    {
        $info = $this->retrieveFeedbackInfo();
        if (!$info->has('serviceProvider') ||
            !$info->has('identityProvider') ||
            !$info->has('requestId')) {
            return false;
        }
        $response = $this->getSamlFailedResponse();
        return $response !== '';
    }

    public function getSpName(): ?string
    {
        return $this->getFeedbackInfo()->get('serviceProviderName') ?? $this->getFeedbackInfo()->get('serviceProvider');
    }

    public function getAcu(): string
    {
        $info = $this->retrieveFeedbackInfo();
        return $this->samlResponseHelper->getAcu($info->get('serviceProvider'));
    }

    public function getSamlFailedResponse(): string
    {
        $session = $this->application->getSession();
        $feedbackInfo = $session->get('feedbackInfo');
        
        // Early return if AuthnFailedResponse is not set
        if (empty($feedbackInfo['AuthnFailedResponse'])) {
            return '';
        }
        
        // Compose the Saml error response that can be used to travel back to the SP
        return $this->samlResponseHelper->createAuthnFailedResponse(
            $feedbackInfo['serviceProvider'],
            $feedbackInfo['identityProvider'],
            $feedbackInfo['requestId'],
            $feedbackInfo['statusMessage'] ?? '',
            $feedbackInfo['AuthnFailedResponse']
        );
    }

    /**
     * Loads the feedbackInfo from the session and filters out empty valued entries.
     *
     * @return FeedbackInformationMap
     */
    private function retrieveFeedbackInfo()
    {
        $session = $this->application->getSession();
        $feedbackInfo = $session->get('feedbackInfo');
        
        // Early return if no feedback info exists
        if (empty($feedbackInfo)) {
            return new FeedbackInformationMap();
        }
        
        $feedbackInfoMap = new FeedbackInformationMap();
        
        foreach ($feedbackInfo as $key => $value) {
            // Skip AuthnFailedResponse as it should not be shown in feedback info table
            if ($key === 'AuthnFailedResponse') {
                continue;
            }
            
            // Skip empty values
            if (empty($value)) {
                continue;
            }
            
            // Convert Issuer objects to their string value
            if ($value instanceof Issuer) {
                $value = $value->getValue();
            }
            
            $feedbackInfoMap->add(new FeedbackInformation($key, $value));
        }
        
        // Only sort if there are items in the map
        if (!$feedbackInfoMap->isEmpty()) {
            $feedbackInfoMap->sort();
        }
        
        return $feedbackInfoMap;
    }
}
