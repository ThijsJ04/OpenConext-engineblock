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

use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext as BaseMinkContext;
use DOMDocument;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RuntimeException;
use SAML2\XML\mdui\Common;
use SAML2\XML\shibmd\Scope;
use function count;

/**
 * Mink-enabled context.
 */
class MinkContext extends BaseMinkContext
{
    /**
     * @var array a list of window names identified by the name the tester refers to them in the step definitions.
     * @example ['My tab' => 'WindowNameGivenByBrowser', 'My other tab' => 'WindowNameGivenByBrowser']
     */
    private $windows = [];

    /**
     * @Given /^Xdebug step debugging is enabled in the browser$/
     */
    public function putDebugCookie()
    {
        $driver = $this->getSession();
        $driver->setCookie('XDEBUG_SESSION', 'PHPSTORM');
    }

    /**
     * @Then /^the response should contain \'([^\']*)\'$/
     */
    public function theResponseShouldContain($string)
    {
        $this->assertSession()->responseContains($string);
    }

    /**
     * @Then /^the response should match xpath \'([^\']*)\'$/
     */
    public function theResponseShouldMatchXpath($xpath)
    {
        $document = new DOMDocument();
        $document->loadXML($this->getSession()->getPage()->getContent());

        $xpathObj = new DOMXPath($document);
        $xpathObj->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
        $xpathObj->registerNamespace('mdui', Common::NS);
        $xpathObj->registerNamespace('shibmd', Scope::NS);
        $nodeList = $xpathObj->query($xpath);

        if (!$nodeList || $nodeList->length === 0) {
            $message = sprintf('The xpath "%s" did not result in at least one match.', $xpath);
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @Then /^the internal-collabPersonId is present in the assertion$/
     */
public function theCollabPersonIdIsPresent()
{
    $document = new DOMDocument();
    $document->loadXML($this->getSession()->getPage()->getContent());
    $xpathObj = new DOMXPath($document);
    $xpathObj->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
    $xpathObj->registerNamespace('mdui', Common::NS);
    $xpathObj->registerNamespace('shibmd', Scope::NS);

    $xpathAttribute = '/samlp:Response/saml:Assertion/saml:AttributeStatement/saml:Attribute' .
        '[@Name="urn:mace:surf.nl:attribute-def:internal-collabPersonId"]';
    $nodeListAttribute = $xpathObj->query($xpathAttribute);

    if (!$nodeListAttribute || $nodeListAttribute->length === 0) {
        throw new ExpectationException(
            'The internal-collabPersonId was not in the assertion',
            $this->getSession()
        );
    }

    $xpathAttributeValue = $xpathAttribute . '/saml:AttributeValue';
    $nodeListAttributeValue = $xpathObj->query($xpathAttributeValue);

    if (!$nodeListAttributeValue || $nodeListAttributeValue->length !== 1) {
        throw new ExpectationException(
            'The internal-collabPersonId should only have one value',
            $this->getSession()
        );
    }

    $attributeValue = $nodeListAttributeValue->item(0);
    $mappedAttributes = [];
    foreach ($attributeValue->attributes as $attribute) {
        $mappedAttributes[$attribute->name] = $attribute->value;
    }

    if (!isset($mappedAttributes['type'])) {
        throw new ExpectationException(
            'The internal-collabPersonId does not carry the xsi:type',
            $this->getSession()
        );
    }

    if ($mappedAttributes['type'] !== 'xs:string') {
        throw new ExpectationException(
            'The internal-collabPersonId xsi:type is not of xs:string',
            $this->getSession()
        );
    }

    if (strpos($attributeValue->nodeValue, 'urn:collab:person:') !== 0) {
        throw new ExpectationException(
            'The internal-collabPersonId does not start with urn:collab:person:',
            $this->getSession()
        );
    }
}

    /**
     * @Then /^the internal-collabPersonId is not present in the assertion$/
     */
public function theCollabPersonIdIsNotPresent()
{
    $document = new DOMDocument();
    $document->loadXML($this->getSession()->getPage()->getContent());

    $xpathObj = new DOMXPath($document);
    $xpathObj->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
    $xpathObj->registerNamespace('mdui', Common::NS);
    $xpathObj->registerNamespace('shibmd', Scope::NS);

    if ($xpathObj->query(
        '/samlp:Response/saml:Assertion/saml:AttributeStatement/saml:Attribute' .
        '[@Name="urn:mace:surf.nl:attribute-def:internal-collabPersonId"]'
    )->length > 0) {
        throw new ExpectationException(
            'The internal-collabPersonId should not be present',
            $this->getSession()
        );
    }
}

    /**
     * @Then /^the SessionIndex should match the Assertion ID$/
     */
public function theSessionIndexShouldMatchTheAssertionID()
{
    $document = new DOMDocument();
    $document->loadXML($this->getSession()->getPage()->getContent());
    $xpathObj = new DOMXPath($document);
    $xpathObj->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
    $xpathObj->registerNamespace('mdui', Common::NS);
    $xpathObj->registerNamespace('shibmd', Scope::NS);

    $nodeListAssertion = $xpathObj->query('/samlp:Response/saml:Assertion[@ID]');
    $nodeListAuthStatement = $xpathObj->query('/samlp:Response/saml:Assertion/saml:AuthnStatement[@SessionIndex]');

    if ($nodeListAssertion->count() === 0 || $nodeListAuthStatement->count() === 0) {
        throw new ExpectationException(
            'The assertion ID or SessionIndex was not found',
            $this->getSession()
        );
    }

    $assertionID = $nodeListAssertion->item(0)->getAttribute('ID');
    $sessionIndex = $nodeListAuthStatement->item(0)->getAttribute('SessionIndex');

    if (empty($sessionIndex) || $assertionID !== $sessionIndex) {
        throw new ExpectationException(
            'The SessionIndex was empty or did not match the assertion ID',
            $this->getSession()
        );
    }
}

    /**
     * @Then /^the response should not match xpath \'([^\']*)\'$/
     */
public function theResponseShouldNotMatchXpath($xpath)
{
    $document = new DOMDocument();
    $document->loadXML($this->getSession()->getPage()->getContent());

    $xpathObj = new DOMXPath($document);
    $xpathObj->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
    $xpathObj->registerNamespace('mdui', Common::NS);
    $nodeList = $xpathObj->query($xpath);

    if ($nodeList && $nodeList->length > 0) {
        throw new ExpectationException(
            sprintf('The xpath "%s" resulted in matches where none were expected', $xpath),
            $this->getSession()
        );
    }
}

    /**
     * @Given /^I should see URL "([^"]*)"$/
     */
    public function iShouldSeeUrl($url)
    {
        $this->assertSession()->responseContains($url);
    }

    /**
     * @Given /^I should not see URL "([^"]*)"$/
     */
    public function iShouldNotSeeUrl($url)
    {
        $this->assertSession()->responseNotContains($url);
    }

    /**
     * @Given /^I open (\d+) browser tabs identified by "([^"]*)"$/
     */
public function iOpenTwoBrowserTabsIdentifiedBy($numberOfTabs, $tabNames)
{
    $this->getMink()->getSession()->restart();
    $this->windows = [];

    $tabs = explode(',', $tabNames);
    if (count($tabs) != $numberOfTabs) {
        throw new RuntimeException(
            'Please identify all tabs you are opening in order to refer to them at a later stage'
        );
    }

    $session = $this->getMink()->getSession();
    $initialWindows = $session->getWindowNames();

    foreach ($tabs as $tab) {
        $session->executeScript("window.open('about:blank','_blank');");
        $newWindows = array_diff($session->getWindowNames(), $initialWindows);

        if (count($newWindows) != 1) {
            throw new RuntimeException('The new windows were not opened correctly.');
        }

        $this->windows[trim($tab)] = array_pop($newWindows);
        $initialWindows = $session->getWindowNames();
    }
}

    /**
     * @Given /^I switch to "([^"]*)"$/
     */
    public function iSwitchToWindow($windowName)
    {
        $this->switchToWindow($windowName);
    }

    public function switchToWindow($windowName)
    {
        if (!isset($this->windows[$windowName])) {
            throw new RuntimeException(sprintf('Unknown window/tab name "%s"', $windowName));
        }
        $this->getSession()->switchToWindow($this->windows[$windowName]);
    }

    /**
     * @Then /^I should see (\d+) links on the front page$/
     */
    public function iShouldSeeLinksOnTheFrontPage($expectedNumberOfLinks)
    {
        $anchors = $this->getSession()->getPage()->findAll('css', '#engine-main-page a');
        if (count($anchors) != $expectedNumberOfLinks) {
            throw new ExpectationException(
                sprintf(
                    'The expected amount (%d) of metadata links could not be found on the page, actually found "%d"',
                    $expectedNumberOfLinks,
                    count($anchors)
                ),
                $this->getSession()
            );
        }
    }
}
