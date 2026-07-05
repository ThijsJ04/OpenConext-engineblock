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
        $xpathObj = $this->createXPathObject();
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
        $xpathObj = $this->createXPathObject();
        
        $attributeXPath = '/samlp:Response/saml:Assertion/saml:AttributeStatement/saml:Attribute' .
                         '[@Name="urn:mace:surf.nl:attribute-def:internal-collabPersonId"]';
        $attributeValueXPath = $attributeXPath . '/saml:AttributeValue';
        
        $nodeListAttribute = $xpathObj->query($attributeXPath);
        $nodeListAttributeValue = $xpathObj->query($attributeValueXPath);
        
        if (!$nodeListAttribute || $nodeListAttribute->length === 0) {
            throw new ExpectationException(
                'The internal-collabPersonId was not in the assertion',
                $this->getSession()
            );
        }
        
        if (!$nodeListAttributeValue || $nodeListAttributeValue->length !== 1) {
            throw new ExpectationException(
                'The internal-collabPersonId should only have one value',
                $this->getSession()
            );
        }
        
        $attributeValueNode = $nodeListAttributeValue->item(0);
        $typeAttribute = $attributeValueNode->attributes->getNamedItem('type');
        
        if (!$typeAttribute) {
            throw new ExpectationException(
                'The internal-collabPersonId does not carry the xsi:type',
                $this->getSession()
            );
        }
        
        if ($typeAttribute->value !== 'xs:string') {
            throw new ExpectationException(
                'The internal-collabPersonId xsi:type is not of xs:string',
                $this->getSession()
            );
        }
        
        $attributeValue = $attributeValueNode->nodeValue;
        if (substr($attributeValue, 0, 18) !== 'urn:collab:person:') {
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
        $xpathObj = $this->createXPathObject();
        
        $nodeList = $xpathObj->query(
            '/samlp:Response/saml:Assertion/saml:AttributeStatement/saml:Attribute' .
            '[@Name="urn:mace:surf.nl:attribute-def:internal-collabPersonId"]'
        );

        if (!$nodeList || $nodeList->length > 0) {
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
        $xpathObj = $this->createXPathObject();
        
        $nodeListAssertion = $xpathObj->query('/samlp:Response/saml:Assertion[@ID]');
        $nodeListAuthStatement = $xpathObj->query('/samlp:Response/saml:Assertion/saml:AuthnStatement[@SessionIndex]');

        if ($nodeListAssertion->length === 0) {
            throw new ExpectationException('The assertion ID was not found', $this->getSession());
        }

        if ($nodeListAuthStatement->length === 0) {
            throw new ExpectationException('The SessionIndex was not found', $this->getSession());
        }

        $assertionID = $nodeListAssertion->item(0)->attributes->getNamedItem('ID')->value;
        $sessionIndex = $nodeListAuthStatement->item(0)->attributes->getNamedItem('SessionIndex')->value;
        
        if ($assertionID !== $sessionIndex) {
            throw new ExpectationException('The SessionIndex was not the same as the assertion ID', $this->getSession());
        }
    }

    /**
     * @Then /^the response should not match xpath \'([^\']*)\'$/
     */
    public function theResponseShouldNotMatchXpath($xpath)
    {
        $xpathObj = $this->createXPathObject();
        $nodeList = $xpathObj->query($xpath);

        if ($nodeList && $nodeList->length > 0) {
            $message = sprintf(
                'The xpath "%s" resulted in "%d" matches, where it should result in no matches',
                $xpath,
                $nodeList->length
            );
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * Creates and configures a DOMXPath object with the response content and required namespaces.
     *
     * @return DOMXPath
     */
    private function createXPathObject()
    {
        $document = new DOMDocument();
        $document->loadXML($this->getSession()->getPage()->getContent());

        $xpathObj = new DOMXPath($document);
        $xpathObj->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
        $xpathObj->registerNamespace('mdui', Common::NS);
        $xpathObj->registerNamespace('shibmd', Scope::NS);
        $xpathObj->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $xpathObj->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');

        return $xpathObj;
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
        $this->windows = []; // Reset the windows array to avoid confusion with previous tests

        $tabs = explode(',', $tabNames);
        if (count($tabs) != $numberOfTabs) {
            throw new RuntimeException(
                'Please identify all tabs you are opening in order to refer to them at a later stage'
            );
        }

        $session = $this->getMink()->getSession();
        $initialWindows = $session->getWindowNames();
        
        // Open all tabs at once using a single JavaScript execution
        $script = 'for (var i = 0; i < ' . $numberOfTabs . '; i++) { window.open("about:blank", "_blank"); }';
        $session->executeScript($script);
        
        $finalWindows = $session->getWindowNames();
        $newWindows = array_diff($finalWindows, $initialWindows);
        
        if (count($newWindows) != $numberOfTabs) {
            throw new RuntimeException('The new windows were not opened correctly.');
        }
        
        // Assign window names to tab identifiers
        $newWindowsArray = array_values($newWindows);
        foreach ($tabs as $index => $tab) {
            $this->windows[trim($tab)] = $newWindowsArray[$index];
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
