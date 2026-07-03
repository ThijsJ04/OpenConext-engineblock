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
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength) - See comment in class doc block
     */
public function onKernelException(ExceptionEvent $event)
{
    $exception = $event->getThrowable();
    $redirectParams = [];

    $exceptionMap = [
        EngineBlock_Corto_Module_Bindings_UnableToReceiveMessageException::class =>
            ['authentication_feedback_unable_to_receive_message', 'Unable to receive message'],
        EngineBlock_Corto_Module_Services_SessionLostException::class =>
            ['authentication_feedback_session_lost', 'Session lost'],
        EngineBlock_Corto_Module_Services_SessionNotStartedException::class =>
            ['authentication_feedback_session_not_started', 'Session not started'],
        EngineBlock_Corto_Module_Service_SingleSignOn_NoIdpsException::class =>
            ['authentication_feedback_no_idps', 'No Identity Provider'],
        EngineBlock_Corto_Exception_InvalidAcsLocation::class =>
            ['authentication_feedback_invalid_acs_location', 'Invalid ACS location'],
        EngineBlock_Corto_Exception_MissingRequiredFields::class =>
            ['authentication_feedback_missing_required_fields', 'Missing Required Fields'],
        EngineBlock_Corto_Exception_AuthnContextClassRefBlacklisted::class =>
            ['authentication_authn_context_class_ref_blacklisted', null],
        EngineBlock_Corto_Exception_InvalidMfaAuthnContextClassRef::class =>
            ['authentication_invalid_mfa_authn_context_class_ref', null],
        EngineBlock_Corto_Module_Bindings_UnsupportedBindingException::class =>
            ['authentication_feedback_invalid_acs_binding', 'Unsupported Binding'],
        EngineBlock_Corto_Module_Bindings_UnsupportedSignatureMethodException::class =>
            ['authentication_feedback_unsupported_signature_method', 'Unsupported signature method'],
        EngineBlock_Corto_Module_Bindings_UnsupportedAcsLocationSchemeException::class =>
            ['authentication_feedback_unsupported_acs_location_uri_scheme', 'Unsupported URI scheme in ACS location'],
        EngineBlock_Corto_Exception_ReceivedErrorStatusCode::class =>
            ['authentication_feedback_received_error_status_code', 'Received Error Status Code'],
        EngineBlock_Corto_Module_Bindings_SignatureVerificationException::class =>
            ['authentication_feedback_signature_verification_failed', 'Unable to verify signature, cert wrong?'],
        EngineBlock_Corto_Module_Bindings_VerificationException::class =>
            ['authentication_feedback_verification_failed', 'Unable to verify message'],
        EngineBlock_Exception_UnknownServiceProvider::class =>
            ['authentication_feedback_unknown_service_provider', 'Unknown Service Provider'],
        EngineBlock_Exception_UnknownIdentityProvider::class =>
            ['authentication_feedback_unknown_identity_provider', 'Unknown Identity Provider'],
        EngineBlock_Corto_Exception_UnknownIdentityProviderSigningKey::class =>
            ['authentication_feedback_unknown_signing_key', null],
        EngineBlock_Exception_UnknownRequesterIdInAuthnRequest::class =>
            ['authentication_feedback_unknown_requesterid_in_authnrequest', 'Encountered unknown RequesterID for the Service Provider (transparant proxying)'],
        EngineBlock_Corto_Exception_PEPNoAccess::class =>
            ['authentication_feedback_pep_violation', 'PEP authorization rule violation'],
        UnknownKeyIdException::class =>
            ['authentication_feedback_unknown_keyid', null],
        EngineBlock_Corto_Exception_UnknownPreselectedIdp::class =>
            ['authentication_feedback_unknown_preselected_idp', null],
        EngineBlock_Corto_Exception_InvalidAttributeValue::class =>
            ['authentication_feedback_invalid_attribute_value', null],
        StuckInAuthenticationLoopException::class =>
            ['authentication_feedback_stuck_in_authentication_loop', 'Stuck in authentication loop'],
        AuthenticationSessionLimitExceededException::class =>
            ['authentication_feedback_authentication_limit_exceeded', 'Authentication procedure limit exceeded'],
        \EngineBlock_Corto_Module_Bindings_ClockIssueException::class =>
            ['authentication_feedback_response_clock_issue', null],
        EngineBlock_Corto_Exception_UserCancelledStepupCallout::class =>
            ['authentication_feedback_stepup_callout_user_cancelled', null],
        EngineBlock_Corto_Exception_InvalidStepupLoaLevel::class =>
            ['authentication_feedback_stepup_callout_unmet_loa', null],
        EngineBlock_Corto_Exception_InvalidStepupCalloutResponse::class =>
            ['authentication_feedback_stepup_callout_unknown', null],
        EntityCanNotBeFoundException::class =>
            ['authentication_feedback_metadata_entity_not_found', null],
    ];

    $exceptionClass = get_class($exception);
    if (!isset($exceptionMap[$exceptionClass])) {
        return;
    }

    [$redirectToRoute, $message] = $exceptionMap[$exceptionClass];

    if ($exception instanceof EngineBlock_Attributes_Manipulator_CustomException) {
        $event->getRequest()->getSession()->set('feedback_custom', $exception->getFeedback());
        $message = 'Custom Exception thrown from Attribute Manipulator';
    } elseif ($exception instanceof EngineBlock_Corto_Module_Bindings_UnsupportedSignatureMethodException) {
        $redirectParams = ['signature-method' => $exception->getSignatureMethod()];
    } elseif ($exception instanceof EngineBlock_Exception_UnknownServiceProvider) {
        $redirectParams = ['entity-id' => $exception->getEntityId()];
    } elseif ($exception instanceof EngineBlock_Exception_UnknownIdentityProvider) {
        $redirectParams = [
            'entity-id' => $exception->getEntityId(),
            'destination' => $exception->getDestination()
        ];
    } elseif ($exception instanceof UnknownKeyIdException) {
        $redirectParams = ['keyid' => $exception->getRequestedKeyId()];
    } elseif ($exception instanceof EngineBlock_Corto_Exception_UnknownPreselectedIdp) {
        $redirectParams = ['idp-hash' => $exception->getRemoteIdpMd5Hash()];
    } elseif ($exception instanceof InvalidRequestMethodException ||
              $exception instanceof InvalidBindingException ||
              $exception instanceof MissingParameterException) {
        $event->getRequest()->getSession()->set('feedback_custom', $exception->getMessage());
        $redirectToRoute = 'authentication_feedback_no_authentication_request_received';
    }

    if ($message === null) {
        $message = $exception->getMessage();
    }

    $this->logger->debug(sprintf(
        'Caught Exception "%s":"%s", redirecting to route "%s"',
        $exceptionClass,
        $exception->getMessage(),
        $redirectToRoute
    ));

    $this->logger->notice($message);
    $this->errorReporter->reportError($exception, '-> Redirecting to feedback page');

    $event->setResponse(new RedirectResponse(
        $this->urlGenerator->generate($redirectToRoute, $redirectParams, UrlGeneratorInterface::ABSOLUTE_PATH)
    ));
}
}
