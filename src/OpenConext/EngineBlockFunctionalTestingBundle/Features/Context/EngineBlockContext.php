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

namespace OpenConext\EngineBlockFunctionalTestingBundle\Features\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use OpenConext\EngineBlockFunctionalTestingBundle\Fixtures\DataStore\AbstractDataStore;
use OpenConext\EngineBlockFunctionalTestingBundle\Fixtures\FunctionalTestingAttributeAggregationClient;
use OpenConext\EngineBlockFunctionalTestingBundle\Fixtures\FunctionalTestingAuthenticationLoopGuard;
use OpenConext\EngineBlockFunctionalTestingBundle\Fixtures\FunctionalTestingFeatureConfiguration;
use OpenConext\EngineBlockFunctionalTestingBundle\Fixtures\FunctionalTestingPdpClient;
use OpenConext\EngineBlockFunctionalTestingBundle\Fixtures\ServiceRegistryFixture;
use OpenConext\EngineBlockFunctionalTestingBundle\Mock\EntityRegistry;
use OpenConext\EngineBlockFunctionalTestingBundle\Mock\MockIdentityProvider;
use OpenConext\EngineBlockFunctionalTestingBundle\Service\EngineBlock;
use RobRichards\XMLSecLibs\XMLSecEnc;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RuntimeException;
use SAML2\Constants;
use SAML2\DOMDocumentFactory;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) Both set up and tasks can be a lot...
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Both set up and tasks can be a lot...
 * @SuppressWarnings(PHPMD.TooManyMethods) Both set up and tasks can be a lot...
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Due to all integration specific features
 * @SuppressWarnings(PHPMD.ExcessivePublicCount) Both set up and tasks can be a lot...
 */
class EngineBlockContext extends AbstractSubContext
{
    /**
     * @var ServiceRegistryFixture
     */
    private $serviceRegistryFixture;

    /**
     * @var EngineBlock
     */
    private $engineBlock;

    /**
     * @var EntityRegistry
     */
    private $mockSpRegistry;

    /**
     * @var EntityRegistry
     */
    private $mockIdpRegistry;

    /**
     * @var FunctionalTestingFeatureConfiguration
     */
    private $features;

    /**
     * @var FunctionalTestingAuthenticationLoopGuard
     */
    private $authenticationLoopGuard;

    /**
     * @var boolean
     */
    private $usingFeatures = false;

    /**
     * @var FunctionalTestingPdpClient
     */
    private $pdpClient;

    /**
     * @var boolean
     */
    private $usingPdp = false;

    /*
     * @var boolean
     */
    private $usingAuthenticationLoopGuard = false;

    /**
     * @var string
     */
    private $engineBlockDomain;

    /**
     * @var FunctionalTestingAttributeAggregationClient
     */
    private $attributeAggregationClient;

    /**
     * @var boolean
     */
    private $usingAttributeAggregationClient = false;

    /**
     * @var string
     */
    private $currentRequestId = '';

    private AbstractDataStore $dataStore;

    /**
     * @param ServiceRegistryFixture $serviceRegistry
     * @param EngineBlock $engineBlock
     * @param EntityRegistry $mockSpRegistry
     * @param EntityRegistry $mockIdpRegistry
     * @param FunctionalTestingFeatureConfiguration $features
     * @param FunctionalTestingPdpClient $pdpClient
     * @param FunctionalTestingAuthenticationLoopGuard $authenticationLoopGuard
     * @param FunctionalTestingAttributeAggregationClient $attributeAggregationClient
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
public function __construct(
    ServiceRegistryFixture $serviceRegistry,
    EngineBlock $engineBlock,
    EntityRegistry $mockSpRegistry,
    EntityRegistry $mockIdpRegistry,
    FunctionalTestingFeatureConfiguration $features,
    FunctionalTestingPdpClient $pdpClient,
    FunctionalTestingAuthenticationLoopGuard $authenticationLoopGuard,
    FunctionalTestingAttributeAggregationClient $attributeAggregationClient,
    AbstractDataStore $authGuardDataStore,
) {
    $this->serviceRegistryFixture = $serviceRegistry;
    $this->engineBlock = $engineBlock;
    $this->mockSpRegistry = $mockSpRegistry;
    $this->mockIdpRegistry = $mockIdpRegistry;
    $this->features = $features;
    $this->pdpClient = $pdpClient;
    $this->authenticationLoopGuard = $authenticationLoopGuard;
    $this->attributeAggregationClient = $attributeAggregationClient;
    $this->dataStore = $authGuardDataStore;
}

    /**
     * @Given /^an EngineBlock instance on "([^"]*)"$/
     */
    public function anEngineBlockInstanceOn($domain)
    {
        // Add all known IdPs
        $this->serviceRegistryFixture
            ->reset()
            ->save();

        $this->engineBlockDomain = 'https://engine.' . $domain;
    }

    /**
     * @Given /^I follow the EB debug screen to the IdP$/
     */
    public function iFollowTheEbDebugScreenToTheIdp()
    {
        // Support for HTTP-Post
        $hasSubmitButton = $this->getMinkContext()->getSession()->getPage()->findButton('Submit');
        if ($hasSubmitButton) {
            $this->getMinkContext()->pressButton('submitbutton');
            return;
        }

        // Default to HTTP-Redirect
        $this->getMinkContext()->clickLink('GO');
    }

    /**
     * @Given /^I pass through EngineBlock$/
     */
    public function iPassThroughEngineblock()
    {
        $mink = $this->getMinkContext();

        $mink->pressButton('Submit');
    }

    /**
     * @Given /^EngineBlock raises an unexpected error$/
     */
    public function engineBlockRaisesARuntimeException()
    {
        $mink = $this->getMinkContext();
        // By setting the throwException cookie, the test stand in of the SsoRequestValidator will throw an exception
        $mink->getSession()->setCookie('throwException', 'EngineBlock in functional testing mode threw a RuntimeException');
    }

    /**
     * @Then /^I should see the following "([^"]*)" attributes listed on the consent page:$/
     */
/**
 * @Then /^I should see the following "([^"]*)" attributes listed on the consent page:$/
 */
public function iSeeTheAttributesFromSourceOnConsentPage($source, TableNode $attributes)
{
    $mink = $this->getMinkContext();
    $page = $mink->getSession()->getPage();
    $listItemsSelector = 'ul#attribute-source-' . strtolower($source) . ' li.consent__attribute';

    $listItems = $page->findAll('css', $listItemsSelector);
    $expectedNumberOfAttributes = count($attributes->getRows()) - 1;

    if ($expectedNumberOfAttributes === 0) {
        throw new RuntimeException(sprintf('Unable to find any attributes from source "%s"', $source));
    }

    $expectedAttributes = [];
    foreach ($attributes->getRows() as $expectedAttribute) {
        $expectedAttributes[$expectedAttribute[0]] = $expectedAttribute[1];
    }

    $matchedNumberOfAttributes = 0;
    foreach ($listItems as $attributeRow) {
        $divs = $attributeRow->findAll('css', 'div');
        $name = $divs[0]->getText();
        $value = $divs[1]->getText();

        if (isset($expectedAttributes[$name]) && $expectedAttributes[$name] === $value) {
            $matchedNumberOfAttributes++;
        }
    }

    if ($matchedNumberOfAttributes !== $expectedNumberOfAttributes) {
        throw new RuntimeException(
            sprintf(
                'The expected attribute values where not (all) found in the specified source list ("%s")'
                . ' generated on the consent page. Expected %d, found %d',
                $source,
                $expectedNumberOfAttributes,
                $matchedNumberOfAttributes
            )
        );
    }
}

    /**
     * @Given /^I give my consent$/
     */
    public function iGiveMyConsent()
    {
        $mink = $this->getMinkContext();

        if (strstr($mink->getSession()->getPage()->getHtml(), 'accept_terms_button')) {
            $mink->pressButton('accept_terms_button');
        }
    }

    /**
     * @Given /^An IdP initiated Single Sign on for SP "([^"]*)" is triggered by IdP "([^"]*)"$/
     */
    public function anIdpInitiatedSingleSignOnForSpIsTriggeredByIdP($spName, $idpName)
    {
        $mockSp = $this->mockSpRegistry->get($spName);
        $mockIdP = $this->mockIdpRegistry->get($idpName);

        $mink = $this->getMinkContext();
        $mink->visit(
            $this->engineBlock->unsolicitedLocation($mockIdP->entityId(), $mockSp->entityId())
        );
    }

    /**
     * @Given /^An IdP initiated Single Sign on for SP "([^"]*)" is triggered by IdP "([^"]*)" and specifies a valid signing key$/
     */
    public function anIdpInitiatedSingleSignOnForSpIsTriggeredByIdPWithSigningKey($spName, $idpName)
    {
        $mockSp = $this->mockSpRegistry->get($spName);
        $mockIdP = $this->mockIdpRegistry->get($idpName);

        $mink = $this->getMinkContext();
        $mink->visit(
            $this->engineBlock->unsolicitedLocation($mockIdP->entityId(), $mockSp->entityId(), 'default')
        );
    }

    /**
     * @Given /^An IdP initiated Single Sign on for SP "([^"]*)" is triggered by IdP "([^"]*)" and specifies an invalid signing key$/
     */
    public function anIdpInitiatedSingleSignOnForSpIsTriggeredByIdPWithInvalidSigningKey($spName, $idpName)
    {
        $mockSp = $this->mockSpRegistry->get($spName);
        $mockIdP = $this->mockIdpRegistry->get($idpName);

        $mink = $this->getMinkContext();
        $mink->visit(
            $this->engineBlock->unsolicitedLocation($mockIdP->entityId(), $mockSp->entityId(), 'does-not-exist')
        );
    }

    /**
     * @Given /^An IdP initiated Single Sign on for SP "([^"]*)" is incorrectly triggered by IdP "([^"]*)"$/
     */
    public function anIdpInitiatedSingleSignOnForSpIsIncorrectlyTriggeredByIdP($spName, $idpName)
    {
        $mockSp = $this->mockSpRegistry->get($spName);
        $mockIdP = $this->mockIdpRegistry->get($idpName);

        $mink = $this->getMinkContext();
        $mink->visit(
            $this->engineBlock->unsolicitedLocation($mockIdP->entityId() . 'I made a booboo', $mockSp->entityId())
        );
    }

    /**
     * @Given /^An IdP initiated Single Sign on for SP "([^"]*)" with invalid parameter, by IdP "([^"]*)"$/
     */
    public function anIdpInitiatedSingleSignOnForSpIsInvalidParameterByIdP($spName, $idpName)
    {
        $mockSp = $this->mockSpRegistry->get($spName);
        $mockIdP = $this->mockIdpRegistry->get($idpName);

        $mink = $this->getMinkContext();
        $mink->visit(
            $this->engineBlock->unsolicitedLocationInvalidParam($mockIdP->entityId(), $mockSp->entityId())
        );
    }

    /**
     * @Given /^I select "([^"]*)" on the WAYF$/
     */
/**
 * @Given /^I select "([^"]*)" on the WAYF$/
 */
public function iSelectOnTheWAYF($idpName)
{
    $mockIdp = $this->mockIdpRegistry->get($idpName);
    if (!$mockIdp) {
        throw new RuntimeException(sprintf('Unable to find idp with name "%s"', $idpName));
    }

    $selector = '[data-entityid="' . $mockIdp->entityId() . '"] button.idp__submit';
    $page = $this->getMinkContext()->getSession()->getPage();
    $button = $page->find('css', $selector);

    if (!$button) {
        throw new RuntimeException(sprintf('Unable to find button with selector "%s"', $selector));
    }

    $button->click();
}

    /**
     * @Given /^I select IdP by label "([^"]*)" on the WAYF$/
     */
    public function iSelectByLabelOnTheWAYF($idpLabel)
    {
        $selector = '[data-title="' . $idpLabel . '"] button.idp__submit';
        $mink = $this->getMinkContext()->getSession()->getPage();
        $button = $mink->find('css', $selector);
        if (!$button) {
            throw new RuntimeException(sprintf('Unable to find button with selector "%s"', $selector));
        }

        $button->click();
    }

    /**
     * @Then /^The process form should have the "([^"]*)" field$/
     */
    public function iSeeACertainFormFieldOnTheProcessForm($formFieldName)
    {
        $selector = 'input[name="' . $formFieldName . '"]';
        $mink = $this->getMinkContext()->getSession()->getPage();
        $formField = $mink->find('css', $selector);

        if (!$formField) {
            throw new RuntimeException(sprintf('The "%s" form field should have been on the form.', $formFieldName));
        }
    }

    /**
     * @Then /^The process form should not have the "([^"]*)" field$/
     */
    public function iDoNotSeeACertainFormFieldOnTheProcessForm($formFieldName)
    {
        $selector = 'input[name="' . $formFieldName . '"]';
        $mink = $this->getMinkContext()->getSession()->getPage();
        $formField = $mink->find('css', $selector);

        if (!is_null($formField)) {
            throw new RuntimeException(sprintf('The "%s" form field should not have been on the form.', $formFieldName));
        }
    }

    /**
     * @Then /^I should see the "Request access" button$/
     */
    public function iSeeTheRequestAccessButton()
    {
        $selector = '.wayf__idp--noAccess';

        $mink = $this->getMinkContext()->getSession()->getPage();
        $button = $mink->find('css', $selector);

        if (!$button) {
            throw new RuntimeException(sprintf('Unable to find Request access button "%s"', $selector));
        }
    }

    /**
     * @Then /^I should not see the "Request access" button$/
     */
    public function iDoNotSeeTheRequestAccessButton()
    {
        try {
            $this->iSeeTheRequestAccessButton();
        } catch (RuntimeException $e) {
            return;
        }

        throw new RuntimeException('Request access button found on page');
    }

    /**
     * @Then /^I click the return to SP button$/
     */
    public function iClickTheAuthnFailedButton()
    {
        $page = $this->minkContext->getSession()->getPage();
        $element = $page->find('css', '.footer-button__button');
        $element->click();
    }

    /**
     * @Given /^I log out at EngineBlock$/
     */
    public function iLogoutAtEngineBlock()
    {
        $this->getMinkContext()->visit($this->engineBlock->logoutLocation());
    }

    /**
     * @Given /^feature "([^"]*)" is enabled$/
     */
    public function featureIsEnabled($feature)
    {
        $this->usingFeatures = true;
        $this->features->save($feature, true);
    }

    /**
     * @Given /^feature "([^"]*)" is disabled$/
     */
    public function featureIsDisabled($feature)
    {
        $this->usingFeatures = true;
        $this->features->save($feature, false);
    }

    /**
     * @Given /^I lose my session$/
     */
    public function iLoseMySession()
    {
        $session = $this->getMinkContext()->getSession();
        $session->restart();
        // set unknown session id to prevent session not found exception
        $session->setCookie(session_name(), '000000');
    }
    /**
     * @Given /^I lose my session and reload$/
     */
    public function iLoseMySessionAndReload()
    {
        $session = $this->getMinkContext()->getSession();
        $currentUrl = $session->getCurrentUrl();
        $session->restart();
        // set unknown session id to prevent session not found exception
        $session->setCookie(session_name(), '000000');
        $session->visit($currentUrl);
    }

    /**
     * @Given /^pdp gives a deny response$/
     */
    public function pdpGivesADenyResponse()
    {
        $this->usingPdp = true;
        $this->pdpClient->receiveDenyResponse();
    }

    /**
     * @Given /^pdp gives an IdP specific deny response for "([^"]*)"$/
     */
    public function pdpGivesAnIdpSpecificDenyResponse($idpName)
    {
        $this->usingPdp = true;
        $this->pdpClient->receiveSpecificDenyResponse($idpName);
    }

    /**
     * @Given /^pdp gives a stepup obligation response for "([^"]*)"/
     */
    public function pdpGivesStepupObligationResponse($loaId)
    {
        $this->usingPdp = true;
        $this->pdpClient->receiveObligationResponse($loaId);
    }

    /**
     * @Given /^pdp gives an indeterminate response$/
     */
    public function pdpGivesAnIndeterminateResponse()
    {
        $this->usingPdp = true;
        $this->pdpClient->receiveIndeterminateResponse();
    }

    /**
     * @Given /^pdp gives a permit response$/
     */
    public function pdpGivesAnPermitResponse()
    {
        $this->usingPdp = true;
        $this->pdpClient->receivePermitResponse();
    }

    /**
     * @Given /^pdp gives a not applicable response$/
     */
    public function pdpGivesANotApplicableResponse()
    {
        $this->usingPdp = true;
        $this->pdpClient->receiveNotApplicableResponse();
    }

    /**
     * @Given /^EngineBlock is configured to allow a maximum of (\d+) per SP within a timeframe of (\d+) seconds and with (\d+) authentications$/
     * @param int $maximumAuthenticationProceduresAllowed
     * @param int $timeFrameForAuthenticationLoopInSeconds
     * @param int $maxiumumAuth
     */
    public function engineblockIsConfiguredToAllowAMaximumOfAuthenticationProcedures(
        $maximumAuthenticationProceduresAllowed,
        $timeFrameForAuthenticationLoopInSeconds,
        $maximumAuthenticationsPerSession
    ) {
        $this->authenticationLoopGuard->saveAuthenticationLoopGuardConfiguration(
            (int) $maximumAuthenticationProceduresAllowed,
            (int) $timeFrameForAuthenticationLoopInSeconds,
            (int) $maximumAuthenticationsPerSession,
            $this->dataStore,
        );
        $this->usingAuthenticationLoopGuard = true;
    }

    /**
     * @AfterScenario
     */
    public function cleanAttributeAggregator()
    {
        if ($this->usingAttributeAggregationClient) {
            $this->attributeAggregationClient->returnsNothing();
        }
    }

    /**
     * @AfterScenario
     */
    public function cleanUpPdp()
    {
        if ($this->usingPdp) {
            $this->pdpClient->clear();
        }
    }

    /**
     * @AfterScenario
     */
    public function cleanUpFeatures()
    {
        if ($this->usingFeatures) {
            $this->features->clean();
        }
    }

    /**
     * @AfterScenario
     */
    public function cleanUpAuthenticationLoopGuard()
    {
        if ($this->usingAuthenticationLoopGuard) {
            $this->authenticationLoopGuard->cleanUp($this->dataStore);
        }
        $this->usingAuthenticationLoopGuard = false;
    }

    /**
     * @Then /^the received AuthnRequest should not match xpath '([^']*)'$/
     */
/**
 * @Then /^the received AuthnRequest should not match xpath '([^']*)'$/
 */
public function theReceivedAuthnRequestShouldNotMatchXpath($xpath)
{
    $session = $this->getMinkContext()->getSession();
    $mink = $session->getPage();
    $authnRequestElement = $mink->find('css', 'input[name="authnRequestXml"]');
    if ($authnRequestElement === null) {
        throw new ExpectationException('Element with the name "authnRequestXml" could not be found', $session);
    }

    $authnRequestXml = html_entity_decode($authnRequestElement->getValue());
    $authnRequest = new DOMDocument();
    $authnRequest->loadXML($authnRequestXml);

    $xpathObject = new DOMXPath($authnRequest);
    $xpathObject->registerNamespace('gssp', 'urn:mace:surf.nl:stepup:gssp-extensions');
    $nodeList = $xpathObject->query($xpath);

    if ($nodeList && $nodeList->length > 0) {
        throw new RuntimeException('The xpath was found in the AuthnRequest, it should not');
    }
}

    /**
     * @Then /^the received AuthnRequest should match xpath '([^']*)'$/
     */
    public function theReceivedAuthnRequestShouldMatchXpath($xpath)
    {
        return $this->theAuthnRequestToSubmitShouldMatchXpath($xpath);
    }

    /**
     * @Then /^the AuthnRequest to submit should match xpath '([^']*)'$/
     */
/**
 * @Then /^the AuthnRequest to submit should match xpath '([^']*)'$/
 */
public function theAuthnRequestToSubmitShouldMatchXpath($xpath)
{
    $session = $this->getMinkContext()->getSession();
    $page = $session->getPage();
    $authnRequestElement = $page->find('css', 'input[name="authnRequestXml"]');

    if ($authnRequestElement === null) {
        throw new ExpectationException('Element with the name "authnRequestXml" could not be found', $session);
    }

    $authnRequestXml = html_entity_decode($authnRequestElement->getValue());
    $authnRequest = new DOMDocument();
    $authnRequest->loadXML($authnRequestXml);

    $xpathObject = new DOMXPath($authnRequest);
    $xpathObject->registerNamespace('gssp', 'urn:mace:surf.nl:stepup:gssp-extensions');
    $nodeList = $xpathObject->query($xpath);

    if (!$nodeList || $nodeList->length === 0) {
        throw new ExpectationException(
            sprintf('The xpath "%s" did not result in at least one match.', $xpath),
            $session
        );
    }
}

    /**
     * @Given /^my browser is configured to accept language "([^"]*)"$/
     */
    public function myBrowserIsConfiguredToAcceptLanguage($language)
    {
        $this->getMinkContext()->getSession()->setRequestHeader('Accept-Language', $language);
    }

    /**
     * @Then /^a lang cookie should be set with value "([^"]*)"$/
     */
/**
 * @Then /^a lang cookie should be set with value "([^"]*)"$/
 */
public function aLangCookieShouldBeSetWithValue($locale)
{
    $cookie = $this->getMinkContext()->getSession()->getCookie('lang');

    if ($cookie !== $locale) {
        throw new ExpectationException(
            sprintf('The lang cookie should contain "%s", but contains "%s"', $locale, $cookie),
            $this->getMinkContext()->getSession()->getDriver()
        );
    }
}

    /**
     * @Given /^I have a locale cookie containing "([^"]*)"$/
     */
    public function iHaveALocaleCookieContaining($locale)
    {
        $this->getMinkContext()->getSession()->setCookie('lang', $locale);
    }

    /**
     * @When /^I go to Engineblock URL "([^"]*)"$/
     */
    public function iGoToEngineblockURL($path)
    {
        $this->getMinkContext()->visit($this->engineBlockDomain . $path);
    }

    /**
     * @When /^I post data "([^"]*)" to Engineblock URL "([^"]*)"$/
     */
    public function iPostDataToEngineBlockUrl($data, $path)
    {
        $postdata = json_decode($data, true);
        $url = $this->engineBlockDomain . $path;

        $this->getMinkContext()->getSession()->getDriver()->getClient()->request('POST', $url, $postdata);
    }

    /**
     * @Given /^the attribute aggregator returns no attributes$/
     */
    public function aaReturnsNoAttributes()
    {
        $this->usingAttributeAggregationClient = true;

        $this->attributeAggregationClient->returnsNothing();
    }

    /**
     * @Given /^the attribute aggregator returns the attributes:$/
     */
    public function aaReturnsAttributes(TableNode $attributes)
    {
        $this->usingAttributeAggregationClient = true;

        foreach ($attributes->getHash() as $attribute) {
            $this->attributeAggregationClient->returnsAttribute(
                $attribute['Name'],
                explode(',', $attribute['Value']),
                $attribute['Source']
            );
        }
    }

    /**
     * @Given /^I should see ART code "([^"]*)"$/
     */
/**
 * @Then /^I should see ART code "([^"]*)"$/
 */
public function iShouldSeeARTCode($artCode)
{
    $session = $this->getMinkContext()->getSession();
    $page = $session->getPage();
    $result = $page->find('xpath', '//span[text()="EC:"]/../span[2]');

    if (!$result || $result->getText() !== $artCode) {
        throw new RuntimeException(
            sprintf('Expected Error Code "%s" did not match the Error Code on the page "%s"', $artCode, $result ? $result->getText() : 'not found')
        );
    }
}

    /**
     * @Then /^I write down the request id as seen on the error page$/
     */
    public function iWriteDownTimestampAndRequestId()
    {
        $this->currentRequestId = $this->getRequestIdFromFeedbackInformation();
    }

    /**
     * @Then /^I should see the same request id on the error page$/
     */
    public function iShouldSeeTheSameRequestId()
    {
        $actualRequestId = $this->getRequestIdFromFeedbackInformation();
        if ($actualRequestId !== $this->currentRequestId) {
            throw new RuntimeException(
                sprintf(
                    'The request id changed between requests: "%s" versus "%s"',
                    $actualRequestId,
                    $this->currentRequestId
                )
            );
        }
        return;
    }
    /**
     * @Then /^I should not see the same request id on the error page$/
     */
    public function iShouldNotSeeTheSameRequestId()
    {
        try {
            // Not being able to find the request id yields a runtime exception
            $this->getRequestIdFromFeedbackInformation();
        } catch (RuntimeException $e) {
            return;
        }

        throw new RuntimeException('The request was found on the page, and we expected it not to be.');
    }

    /**
     * Reads the request id from the error feedback page and returns it as a string
     */
    private function getRequestIdFromFeedbackInformation()
    {
        $session = $this->getMinkContext()->getSession();
        $mink = $session->getPage();
        // Grab the request id from the page with an xpath expression.
        $result = $mink->find('xpath', '//span[text()="UR ID:"]/../span[2]');
        if ($result) {
            $requestIdOnPage = $result->getText();
            if ($requestIdOnPage && $requestIdOnPage !== '') {
                return $requestIdOnPage;
            }
        }
        throw new RuntimeException('Unable to find the request id on the page');
    }

    /**
     * @Then /^I exploit the XML signature bypass vulnerability after passing through the IdP$/
     */
public function iExploitTheXMLSignatureBypass()
{
    $mink = $this->getMinkContext();
    $page = $mink->getSession()->getPage();

    // Get SAMLResponse from form
    $samlResponseField = $page->find('xpath', '//form/input[@name="SAMLResponse"]');
    if ($samlResponseField === null) {
        throw new RuntimeException('SAMLResponse field not found');
    }

    $samlResponse = $samlResponseField->getAttribute('value');
    $samlResponseXml = base64_decode($samlResponse);
    $xmlDom = DOMDocumentFactory::fromString($samlResponseXml);

    // Initialize XPath with all required namespaces
    $xpath = new DOMXPath($xmlDom);
    $namespaces = [
        'soap-env' => Constants::NS_SOAP,
        'saml_protocol' => Constants::NS_SAMLP,
        'saml_assertion' => Constants::NS_SAML,
        'saml_metadata' => Constants::NS_MD,
        'ds' => XMLSecurityDSig::XMLDSIGNS,
        'xenc' => XMLSecEnc::XMLENCNS
    ];

    foreach ($namespaces as $prefix => $namespace) {
        $xpath->registerNamespace($prefix, $namespace);
    }

    // Remove response signature if exists
    $responseSignatures = $xpath->query("/saml_protocol:Response/ds:Signature");
    foreach ($responseSignatures as $signature) {
        $signature->parentNode->removeChild($signature);
    }

    // Get required elements
    $response = $xpath->query("/saml_protocol:Response")->item(0);
    $assertion = $xpath->query("/saml_protocol:Response/saml_assertion:Assertion")->item(0);
    $signature = $xpath->query("/saml_protocol:Response/saml_assertion:Assertion/ds:Signature")->item(0);

    // Clone original assertion and wrap it
    $originalAssertion = $assertion->cloneNode(true);
    $wrapper = $xmlDom->createElement('wrapper');
    $wrapper->appendChild($originalAssertion);
    $response->appendChild($wrapper);

    // Remove signatures from both assertions
    $assertion->removeChild($signature);
    $originalSignature = $xpath->query("./ds:Signature", $originalAssertion)->item(0);
    $originalAssertion->removeChild($originalSignature);

    // Modify assertion content
    $nameId = $xpath->query(
        "/saml_protocol:Response/saml_assertion:Assertion/saml_assertion:Subject/saml_assertion:NameID"
    )->item(0);
    $nameId->textContent = "admin";

    $attributeValue = $xpath->query(
        '//saml_protocol:Response/saml_assertion:Assertion/saml_assertion:AttributeStatement/'.
        'saml_assertion:Attribute[@Name="urn:mace:dir:attribute-def:uid"]/saml_assertion:AttributeValue'
    )->item(0);
    $attributeValue->textContent = "ADMIN!";

    // Change assertion ID and compute new digest
    $assertion->setAttribute('ID', 'attack');
    $digestValue = $this->calculateDigest($assertion);

    // Update signature reference
    $signedInfo = $xpath->query(
        "/saml_protocol:Response/saml_assertion:Assertion/ds:Signature/ds:SignedInfo"
    )->item(0);
    $newSignedInfo = $signedInfo->cloneNode(true);
    $reference = $xpath->query("./ds:Reference", $newSignedInfo)->item(0);
    $reference->setAttribute('URI', '#attack');

    $digest = $xpath->query("./ds:DigestValue", $reference)->item(0);
    $digest->nodeValue = $digestValue;

    // Reconstruct signature
    $signature->appendChild($newSignedInfo);
    $assertion->insertBefore($signature, $assertion->firstChild);

    // Update form with mutated response
    $samlResponseField->setValue(base64_encode($xmlDom->saveXML()));
    $mink->pressButton('GO');
}

    /**
     * @Given /^the RelayState should be "([^"]*)"/
     */
/**
 * @Given /^the RelayState should be "([^"]*)"/
 */
public function theRelayStateShouldBeSetInTheForm($expectedRelayState)
{
    $page = $this->getMinkContext()->getSession()->getPage();
    $relayStateField = $page->find('css', 'input[name="RelayState"]');

    if ($relayStateField === null) {
        throw new ExpectationException(
            'The RelayState field should be present, but it is not',
            $this->getMinkContext()->getSession()->getDriver()
        );
    }

    $relayStateValue = $relayStateField->getValue();
    if ($expectedRelayState !== $relayStateValue) {
        throw new ExpectationException(
            sprintf(
                'The RelayState field should contain "%s", but contains "%s"',
                $expectedRelayState,
                $relayStateValue
            ),
            $this->getMinkContext()->getSession()->getDriver()
        );
    }
}

    /**
     * @Then /^no RelayState should be present/
     */
    public function noRelaystateShouldBePresent(): void
    {
        $mink = $this->getMinkContext();
        $page = $mink->getSession()->getPage();
        $relayStateField = $page->find('css', 'input[name="RelayState"]');

        if ($relayStateField !== null) {
            throw new ExpectationException(
                'The RelayState field should not be present, but it is',
                $mink->getSession()->getDriver()
            );
        }
    }

    /**
     * @param DOMElement $element
     * @return string
     */
    private function calculateDigest(DOMElement $element)
    {
        $xml = $element->C14N(true, false);
        return $this->digest($xml);
    }

    /**
     * @param $data string
     * @return string
     */
    private function digest($data)
    {
        $digest = hash('sha256', $data, true);
        return base64_encode($digest);
    }
}
