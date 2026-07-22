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

        // Define exception handlers in a map for better efficiency and maintainability
        $exceptionHandlers = [
            EngineBlock_Corto_Module_Bindings_UnableToReceiveMessageException::class => [
                'message' => 'Unable to receive message',
                'route' => 'authentication_feedback_unable_to_receive_message',
                'params' => []
            ],
            EngineBlock_Corto_Module_Services_SessionLostException::class => [
                'message' => 'Session lost',
                'route' => 'authentication_feedback_session_lost',
                'params' => []
            ],
            EngineBlock_Corto_Module_Services_SessionNotStartedException::class => [
                'message' => 'Session not started',
                'route' => 'authentication_feedback_session_not_started',
                'params' => []
            ],
            EngineBlock_Corto_Module_Service_SingleSignOn_NoIdpsException::class => [
                'message' => 'No Identity Provider',
                'route' => 'authentication_feedback_no_idps',
                'params' => []
            ],
            EngineBlock_Corto_Exception_InvalidAcsLocation::class => [
                'message' => 'Invalid ACS location',
                'route' => 'authentication_feedback_invalid_acs_location',
                'params' => []
            ],
            EngineBlock_Corto_Exception_MissingRequiredFields::class => [
                'message' => 'Missing Required Fields',
                'route' => 'authentication_feedback_missing_required_fields',
                'params' => []
            ],
            EngineBlock_Corto_Exception_AuthnContextClassRefBlacklisted::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_authn_context_class_ref_blacklisted',
                'params' => []
            ],
            EngineBlock_Corto_Exception_InvalidMfaAuthnContextClassRef::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_invalid_mfa_authn_context_class_ref',
                'params' => []
            ],
            EngineBlock_Attributes_Manipulator_CustomException::class => [
                'message' => 'Custom Exception thrown from Attribute Manipulator',
                'route' => 'authentication_feedback_custom',
                'params' => [],
                'session_key' => 'feedback_custom',
                'session_value' => 'feedback'
            ],
            EngineBlock_Corto_Module_Bindings_UnsupportedBindingException::class => [
                'message' => 'Unsupported Binding',
                'route' => 'authentication_feedback_invalid_acs_binding',
                'params' => []
            ],
            EngineBlock_Corto_Module_Bindings_UnsupportedSignatureMethodException::class => [
                'message' => 'Unsupported signature method',
                'route' => 'authentication_feedback_unsupported_signature_method',
                'params' => ['signature-method']
            ],
            EngineBlock_Corto_Module_Bindings_UnsupportedAcsLocationSchemeException::class => [
                'message' => 'Unsupported URI scheme in ACS location',
                'route' => 'authentication_feedback_unsupported_acs_location_uri_scheme',
                'params' => []
            ],
            EngineBlock_Corto_Exception_ReceivedErrorStatusCode::class => [
                'message' => 'Received Error Status Code',
                'route' => 'authentication_feedback_received_error_status_code',
                'params' => []
            ],
            EngineBlock_Corto_Module_Bindings_SignatureVerificationException::class => [
                'message' => 'Unable to verify signature, cert wrong?',
                'route' => 'authentication_feedback_signature_verification_failed',
                'params' => []
            ],
            EngineBlock_Corto_Module_Bindings_VerificationException::class => [
                'message' => 'Unable to verify message',
                'route' => 'authentication_feedback_verification_failed',
                'params' => []
            ],
            EngineBlock_Exception_UnknownServiceProvider::class => [
                'message' => 'Unknown Service Provider',
                'route' => 'authentication_feedback_unknown_service_provider',
                'params' => ['entity-id']
            ],
            EngineBlock_Exception_UnknownIdentityProvider::class => [
                'message' => 'Unknown Identity Provider',
                'route' => 'authentication_feedback_unknown_identity_provider',
                'params' => ['entity-id', 'destination']
            ],
            EngineBlock_Corto_Exception_UnknownIdentityProviderSigningKey::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_unknown_signing_key',
                'params' => []
            ],
            EngineBlock_Exception_UnknownRequesterIdInAuthnRequest::class => [
                'message' => 'Encountered unknown RequesterID for the Service Provider (transparant proxying)',
                'route' => 'authentication_feedback_unknown_requesterid_in_authnrequest',
                'params' => []
            ],
            EngineBlock_Corto_Exception_PEPNoAccess::class => [
                'message' => 'PEP authorization rule violation',
                'route' => 'authentication_feedback_pep_violation',
                'params' => []
            ],
            UnknownKeyIdException::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_unknown_keyid',
                'params' => ['keyid']
            ],
            EngineBlock_Corto_Exception_UnknownPreselectedIdp::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_unknown_preselected_idp',
                'params' => ['idp-hash']
            ],
            EngineBlock_Corto_Exception_InvalidAttributeValue::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_invalid_attribute_value',
                'params' => []
            ],
            StuckInAuthenticationLoopException::class => [
                'message' => 'Stuck in authentication loop',
                'route' => 'authentication_feedback_stuck_in_authentication_loop',
                'params' => []
            ],
            AuthenticationSessionLimitExceededException::class => [
                'message' => 'Authentication procedure limit exceeded',
                'route' => 'authentication_feedback_authentication_limit_exceeded',
                'params' => []
            ],
            InvalidRequestMethodException::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_no_authentication_request_received',
                'params' => [],
                'session_key' => 'feedback_custom',
                'session_value' => 'message'
            ],
            InvalidBindingException::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_no_authentication_request_received',
                'params' => [],
                'session_key' => 'feedback_custom',
                'session_value' => 'message'
            ],
            MissingParameterException::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_no_authentication_request_received',
                'params' => [],
                'session_key' => 'feedback_custom',
                'session_value' => 'message'
            ],
            \EngineBlock_Corto_Module_Bindings_ClockIssueException::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_response_clock_issue',
                'params' => []
            ],
            EngineBlock_Corto_Exception_UserCancelledStepupCallout::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_stepup_callout_user_cancelled',
                'params' => []
            ],
            EngineBlock_Corto_Exception_InvalidStepupLoaLevel::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_stepup_callout_unmet_loa',
                'params' => []
            ],
            EngineBlock_Corto_Exception_InvalidStepupCalloutResponse::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_stepup_callout_unknown',
                'params' => []
            ],
            EntityCanNotBeFoundException::class => [
                'message' => null, // Use exception message
                'route' => 'authentication_feedback_metadata_entity_not_found',
                'params' => [],
                'session_key' => 'feedback_custom',
                'session_value' => 'message'
            ]
        ];

        // Find the handler for this exception
        $handler = null;
        foreach ($exceptionHandlers as $exceptionClass => $config) {
            if ($exception instanceof $exceptionClass) {
                $handler = $config;
                break;
            }
        }

        // If no handler found, return early
        if ($handler === null) {
            return;
        }

        // Extract handler configuration
        $message = $handler['message'] ?? null;
        $redirectToRoute = $handler['route'];
        $redirectParams = [];

        // Handle session data if specified
        if (isset($handler['session_key'])) {
            $sessionValue = $handler['session_value'];
            $event->getRequest()->getSession()->set($handler['session_key'], $exception->$sessionValue());
        }

        // Build redirect parameters
        foreach ($handler['params'] as $paramName) {
            $methodName = 'get' . $this->camelize($paramName);
            if (method_exists($exception, $methodName)) {
                $redirectParams[$paramName] = $exception->$methodName();
            }
        }

        // Use exception message if no specific message provided
        if ($message === null) {
            $message = $exception->getMessage();
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
     * Convert snake_case to camelCase for method names
     *
     * @param string $string
     * @return string
     */
    private function camelize($string)
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }
}
