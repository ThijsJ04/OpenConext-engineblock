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

namespace OpenConext\EngineBlockBundle\EventListener;

use EngineBlock_ApplicationSingleton;
use EngineBlock_Attributes_Manipulator_CustomException;
use EngineBlock_Corto_Exception_AuthnContextClassRefBlacklisted;
use EngineBlock_Corto_Exception_InvalidAcsLocation;
use EngineBlock_Corto_Exception_InvalidMfaAuthnContextClassRef;
use EngineBlock_Corto_Exception_InvalidStepupCalloutResponse;
use EngineBlock_Corto_Exception_InvalidStepupLoaLevel;
use EngineBlock_Corto_Exception_MissingRequiredFields;
use EngineBlock_Corto_Exception_PEPNoAccess;
use EngineBlock_Corto_Exception_ReceivedErrorStatusCode;
use EngineBlock_Corto_Exception_UnknownIdentityProviderSigningKey;
use EngineBlock_Corto_Exception_UnknownPreselectedIdp;
use EngineBlock_Corto_Exception_InvalidAttributeValue;
use EngineBlock_Corto_Exception_UserCancelledStepupCallout;
use EngineBlock_Corto_Module_Bindings_SignatureVerificationException;
use EngineBlock_Corto_Module_Bindings_UnableToReceiveMessageException;
use EngineBlock_Corto_Module_Bindings_UnsupportedAcsLocationSchemeException;
use EngineBlock_Corto_Module_Bindings_UnsupportedBindingException;
use EngineBlock_Corto_Module_Bindings_UnsupportedSignatureMethodException;
use EngineBlock_Corto_Module_Bindings_VerificationException;
use EngineBlock_Corto_Module_Service_SingleSignOn_NoIdpsException;
use EngineBlock_Corto_Module_Services_SessionLostException;
use EngineBlock_Corto_Module_Services_SessionNotStartedException;
use EngineBlock_Exception_UnknownRequesterIdInAuthnRequest;
use EngineBlock_Exception_UnknownIdentityProvider;
use EngineBlock_Exception_UnknownServiceProvider;
use OpenConext\EngineBlock\Exception\InvalidBindingException;
use OpenConext\EngineBlock\Exception\InvalidRequestMethodException;
use OpenConext\EngineBlock\Exception\MissingParameterException;
use OpenConext\EngineBlockBridge\ErrorReporter;
use OpenConext\EngineBlockBundle\Exception\AuthenticationSessionLimitExceededException;
use OpenConext\EngineBlockBundle\Exception\EntityCanNotBeFoundException;
use OpenConext\EngineBlockBundle\Exception\StuckInAuthenticationLoopException;
use OpenConext\EngineBlockBundle\Exception\UnknownKeyIdException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 *
 * All due to this being a catch all; will be refactored, see https://www.pivotaltracker.com/story/show/107565968
 */
class RedirectToFeedbackPageExceptionListener
{
    /**
     * @var EngineBlock_ApplicationSingleton
     */
    private $engineBlockApplicationSingleton;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var ErrorReporter
     */
    private $errorReporter;

    public function __construct(
        EngineBlock_ApplicationSingleton $engineBlockApplicationSingleton,
        UrlGeneratorInterface $urlGenerator,
        ErrorReporter $errorReporter,
        LoggerInterface $logger
    ) {
        $this->engineBlockApplicationSingleton = $engineBlockApplicationSingleton;
        $this->logger = $logger;
        $this->urlGenerator = $urlGenerator;
        $this->errorReporter = $errorReporter;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        $exceptionHandlers = $this->getExceptionHandlers();
        
        if (!isset($exceptionHandlers[get_class($exception)])) {
            return;
        }

        $handler = $exceptionHandlers[get_class($exception)];
        
        // Handle special cases that need session manipulation
        if ($exception instanceof EngineBlock_Attributes_Manipulator_CustomException) {
            $event->getRequest()->getSession()->set('feedback_custom', $exception->getFeedback());
        } elseif ($exception instanceof InvalidRequestMethodException ||
            $exception instanceof InvalidBindingException ||
            $exception instanceof MissingParameterException ||
            $exception instanceof EntityCanNotBeFoundException
        ) {
            $event->getRequest()->getSession()->set('feedback_custom', $exception->getMessage());
        }

        $message = $handler['message'];
        $redirectToRoute = $handler['route'];
        $redirectParams = $handler['params'] ?? [];

        // Handle dynamic parameters
        if (isset($handler['param_callback'])) {
            $redirectParams = $handler['param_callback']($exception);
        }

        $this->logger->debug(sprintf(
            'Caught Exception "%s":"%s", redirecting to route "%s"',
            get_class($exception),
            $exception->getMessage(),
            $redirectToRoute
        ));

        if (isset($message)) {
            $this->logger->notice($message);
        }

        $this->errorReporter->reportError($exception, '-> Redirecting to feedback page');

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate($redirectToRoute, $redirectParams, UrlGeneratorInterface::ABSOLUTE_PATH)
        ));
    }

    /**
     * @return array
     */
    private function getExceptionHandlers(): array
    {
        return [
            EngineBlock_Corto_Module_Bindings_UnableToReceiveMessageException::class => [
                'message' => 'Unable to receive message',
                'route' => 'authentication_feedback_unable_to_receive_message',
            ],
            EngineBlock_Corto_Module_Services_SessionLostException::class => [
                'message' => 'Session lost',
                'route' => 'authentication_feedback_session_lost',
            ],
            EngineBlock_Corto_Module_Services_SessionNotStartedException::class => [
                'message' => 'Session not started',
                'route' => 'authentication_feedback_session_not_started',
            ],
            EngineBlock_Corto_Module_Service_SingleSignOn_NoIdpsException::class => [
                'message' => 'No Identity Provider',
                'route' => 'authentication_feedback_no_idps',
            ],
            EngineBlock_Corto_Exception_InvalidAcsLocation::class => [
                'message' => 'Invalid ACS location',
                'route' => 'authentication_feedback_invalid_acs_location',
            ],
            EngineBlock_Corto_Exception_MissingRequiredFields::class => [
                'message' => 'Missing Required Fields',
                'route' => 'authentication_feedback_missing_required_fields',
            ],
            EngineBlock_Corto_Exception_AuthnContextClassRefBlacklisted::class => [
                'message' => null,
                'route' => 'authentication_authn_context_class_ref_blacklisted',
                'param_callback' => function ($exception) {
                    return ['message' => $exception->getMessage()];
                }
            ],
            EngineBlock_Corto_Exception_InvalidMfaAuthnContextClassRef::class => [
                'message' => null,
                'route' => 'authentication_invalid_mfa_authn_context_class_ref',
                'param_callback' => function ($exception) {
                    return ['message' => $exception->getMessage()];
                }
            ],
            EngineBlock_Attributes_Manipulator_CustomException::class => [
                'message' => 'Custom Exception thrown from Attribute Manipulator',
                'route' => 'authentication_feedback_custom',
            ],
            EngineBlock_Corto_Module_Bindings_UnsupportedBindingException::class => [
                'message' => 'Unsupported Binding',
                'route' => 'authentication_feedback_invalid_acs_binding',
            ],
            EngineBlock_Corto_Module_Bindings_UnsupportedSignatureMethodException::class => [
                'message' => 'Unsupported signature method',
                'route' => 'authentication_feedback_unsupported_signature_method',
                'param_callback' => function ($exception) {
                    return ['signature-method' => $exception->getSignatureMethod()];
                }
            ],
            EngineBlock_Corto_Module_Bindings_UnsupportedAcsLocationSchemeException::class => [
                'message' => 'Unsupported URI scheme in ACS location',
                'route' => 'authentication_feedback_unsupported_acs_location_uri_scheme',
            ],
            EngineBlock_Corto_Exception_ReceivedErrorStatusCode::class => [
                'message' => 'Received Error Status Code',
                'route' => 'authentication_feedback_received_error_status_code',
            ],
            EngineBlock_Corto_Module_Bindings_SignatureVerificationException::class => [
                'message' => 'Unable to verify signature, cert wrong?',
                'route' => 'authentication_feedback_signature_verification_failed',
            ],
            EngineBlock_Corto_Module_Bindings_VerificationException::class => [
                'message' => 'Unable to verify message',
                'route' => 'authentication_feedback_verification_failed',
            ],
            EngineBlock_Exception_UnknownServiceProvider::class => [
                'message' => 'Unknown Service Provider',
                'route' => 'authentication_feedback_unknown_service_provider',
                'param_callback' => function ($exception) {
                    return ['entity-id' => $exception->getEntityId()];
                }
            ],
            EngineBlock_Exception_UnknownIdentityProvider::class => [
                'message' => 'Unknown Identity Provider',
                'route' => 'authentication_feedback_unknown_identity_provider',
                'param_callback' => function ($exception) {
                    return [
                        'entity-id' => $exception->getEntityId(),
                        'destination' => $exception->getDestination()
                    ];
                }
            ],
            EngineBlock_Corto_Exception_UnknownIdentityProviderSigningKey::class => [
                'message' => null,
                'route' => 'authentication_feedback_unknown_signing_key',
                'param_callback' => function ($exception) {
                    return ['message' => $exception->getMessage()];
                }
            ],
            EngineBlock_Exception_UnknownRequesterIdInAuthnRequest::class => [
                'message' => 'Encountered unknown RequesterID for the Service Provider (transparant proxying)',
                'route' => 'authentication_feedback_unknown_requesterid_in_authnrequest',
            ],
            EngineBlock_Corto_Exception_PEPNoAccess::class => [
                'message' => 'PEP authorization rule violation',
                'route' => 'authentication_feedback_pep_violation',
            ],
            UnknownKeyIdException::class => [
                'message' => null,
                'route' => 'authentication_feedback_unknown_keyid',
                'param_callback' => function ($exception) {
                    return ['keyid' => $exception->getRequestedKeyId()];
                }
            ],
            EngineBlock_Corto_Exception_UnknownPreselectedIdp::class => [
                'message' => null,
                'route' => 'authentication_feedback_unknown_preselected_idp',
                'param_callback' => function ($exception) {
                    return ['idp-hash' => $exception->getRemoteIdpMd5Hash()];
                }
            ],
            EngineBlock_Corto_Exception_InvalidAttributeValue::class => [
                'message' => null,
                'route' => 'authentication_feedback_invalid_attribute_value',
                'param_callback' => function ($exception) {
                    return ['message' => $exception->getMessage()];
                }
            ],
            StuckInAuthenticationLoopException::class => [
                'message' => 'Stuck in authentication loop',
                'route' => 'authentication_feedback_stuck_in_authentication_loop',
            ],
            AuthenticationSessionLimitExceededException::class => [
                'message' => 'Authentication procedure limit exceeded',
                'route' => 'authentication_feedback_authentication_limit_exceeded',
            ],
            InvalidRequestMethodException::class => [
                'message' => null,
                'route' => 'authentication_feedback_no_authentication_request_received',
            ],
            InvalidBindingException::class => [
                'message' => null,
                'route' => 'authentication_feedback_no_authentication_request_received',
            ],
            MissingParameterException::class => [
                'message' => null,
                'route' => 'authentication_feedback_no_authentication_request_received',
            ],
            \EngineBlock_Corto_Module_Bindings_ClockIssueException::class => [
                'message' => null,
                'route' => 'authentication_feedback_response_clock_issue',
                'param_callback' => function ($exception) {
                    return ['message' => $exception->getMessage()];
                }
            ],
            EngineBlock_Corto_Exception_UserCancelledStepupCallout::class => [
                'message' => null,
                'route' => 'authentication_feedback_stepup_callout_user_cancelled',
                'param_callback' => function ($exception) {
                    return ['message' => $exception->getMessage()];
                }
            ],
            EngineBlock_Corto_Exception_InvalidStepupLoaLevel::class => [
                'message' => null,
                'route' => 'authentication_feedback_stepup_callout_unmet_loa',
                'param_callback' => function ($exception) {
                    return ['message' => $exception->getMessage()];
                }
            ],
            EngineBlock_Corto_Exception_InvalidStepupCalloutResponse::class => [
                'message' => null,
                'route' => 'authentication_feedback_stepup_callout_unknown',
                'param_callback' => function ($exception) {
                    return ['message' => $exception->getMessage()];
                }
            ],
            EntityCanNotBeFoundException::class => [
                'message' => null,
                'route' => 'authentication_feedback_metadata_entity_not_found',
            ],
        ];
    }
}
