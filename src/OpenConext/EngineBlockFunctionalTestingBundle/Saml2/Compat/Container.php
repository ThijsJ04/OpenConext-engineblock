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

namespace OpenConext\EngineBlockFunctionalTestingBundle\Saml2\Compat;

use DOMDocument;
use Psr;
use Psr\Log\LoggerInterface;
use SAML2\Compat\AbstractContainer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Exception\NotImplementedException;

class Container extends AbstractContainer
{
    const ID_PREFIX = 'OPENCONEXT_ETS_';

    const DEBUG_TYPE_IN = 'in';
    const DEBUG_TYPE_OUT = 'in';
    const DEBUG_TYPE_ENCRYPT = 'encrypt';
    const DEBUG_TYPE_DECRYPT = 'decrypt';

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var array
     */
    protected $lastDebugMessage = [];

    /**
     * @param string $type
     * @return mixed
     */
    public function getLastDebugMessageOfType($type = self::DEBUG_TYPE_IN)
    {
        return $this->lastDebugMessage[$type];
    }

    /**
     * Get a PSR-3 compatible logger.
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return new SyslogLogger();
    }

    /**
     * Generate a random identifier for identifying SAML2 documents.
     */
    public function generateId(): string
    {
        return self::ID_PREFIX . random_int(0, 100000000);
    }

    /**
     * Log an incoming message to the debug log.
     *
     * Type can be either:
     * - **in** XML received from third party
     * - **out** XML that will be sent to third party
     * - **encrypt** XML that is about to be encrypted
     * - **decrypt** XML that was just decrypted
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    public function debugMessage($message, $type): void
    {
        if ($message instanceof \DOMElement) {
            $message = $message->ownerDocument->saveXML();
        }
        $this->lastDebugMessage[$type] = $message;
        $this->getLogger()->debug($type . ': ' . $message);
    }

    /**
     * Trigger the user to perform a GET to the given URL with the given data.
     *
     * @param string $url
     * @param array $data
     * @return void
     */
    public function redirect($url, $data = []): void
    {
        throw new NotImplementedException(sprintf('SSP/SAML2 Redirect not implemented! URL: "%s', $url));
    }

    /**
     * Trigger the user to perform a POST to the given URL with the given data.
     *
     * @param string $url
     * @param array $data
     */
    public function postRedirect($url, $data = []): void
    {
        $formData = $this->buildFormData($data);
        $authnRequestXml = $this->processAuthnRequest($data);
        $responseDebug = $this->processResponseDebug($data);

        $this->response = new Response($this->buildHtmlResponse($url, $formData, $authnRequestXml, $responseDebug));
    }

    /**
     * Build form input HTML from data array.
     *
     * @param array $data
     * @return string
     */
    private function buildFormData(array $data): string
    {
        $formData = '';
        foreach ($data as $name => $value) {
            $value = htmlentities($value, ENT_COMPAT, 'utf-8');
            $formData .= '            <input name="' . $name . '" type="text" value="' . $value . '" />' . PHP_EOL;
        }
        return $formData;
    }

    /**
     * Process SAMLRequest data and return formatted XML or 'N/A'.
     *
     * @param array $data
     * @return string
     */
    private function processAuthnRequest(array $data): string
    {
        if (isset($data['SAMLRequest'])) {
            $requestXml = base64_decode($data['SAMLRequest']);
            return self::formatXml($requestXml);
        }
        return 'N/A';
    }

    /**
     * Process SAMLResponse data and return formatted debug HTML.
     *
     * @param array $data
     * @return string
     */
    private function processResponseDebug(array $data): string
    {
        if (isset($data['SAMLResponse'])) {
            $responseXml = base64_decode($data['SAMLResponse']);
            $responseXml = self::formatXml($responseXml);
            return '<pre id="responseDebug">' . htmlentities($responseXml, ENT_QUOTES, 'utf-8') . '</pre>';
        }
        return '';
    }

    /**
     * Build the complete HTML response.
     *
     * @param string $url
     * @param string $formData
     * @param string $authnRequestXml
     * @param string $responseDebug
     * @return string
     */
    private function buildHtmlResponse(string $url, string $formData, string $authnRequestXml, string $responseDebug): string
    {
        return '<html>
    <head>
        <title>Redirecting...</title>
    </head>
    <body>
        <pre id="authnRequestXml">' . $authnRequestXml . '</pre>
        ' . $responseDebug . '
        <form id="postform" action="' . $url . '" method="post">
            ' . $formData . '
            <input type="submit" value="GO" />
        </form>
        <script>setTimeout(function() {document.getElementById("postform").submit();}, 1500);</script>
    </body>
</html>';
    }

    public function getPostResponse()
    {
        return $this->response;
    }

    /**
     * @param $xml
     * @return string
     */
    public static function formatXml($xml)
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $dom->formatOutput = true;
        $xml = $dom->saveXml();
        return $xml;
    }

    public function getTempDir(): string
    {
        throw new NotImplementedException(sprintf('getTempDir not implemented!'));
    }

    public function writeFile(string $filename, string $data, int $mode = null): void
    {
        throw new NotImplementedException(sprintf('writeFile not implemented!'));
    }
}
