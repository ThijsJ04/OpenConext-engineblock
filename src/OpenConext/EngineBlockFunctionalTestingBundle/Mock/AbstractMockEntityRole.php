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

namespace OpenConext\EngineBlockFunctionalTestingBundle\Mock;

use RuntimeException;
use SAML2\XML\Chunk;
use SAML2\XML\ds\X509Certificate;
use SAML2\XML\ds\X509Data;
use SAML2\XML\md\EntityDescriptor;
use SAML2\XML\md\SPSSODescriptor;
use SAML2\XML\md\SSODescriptorType;

/**
 * Class AbstractMockEntityRole
 * @package OpenConext\EngineBlockFunctionalTestingBundle\Mock
 */
abstract class AbstractMockEntityRole
{
    protected $name;
    protected $descriptor;

    public function __construct(
        $name,
        EntityDescriptor $descriptor
    ) {
        $this->name = $name;
        $this->descriptor = $descriptor;
    }

    public function entityId()
    {
        return $this->descriptor->getEntityID();
    }

    public function getEntityDescriptor()
    {
        return $this->descriptor;
    }

    public function setEntityId($entityId)
    {
        $this->descriptor->setEntityID($entityId);
        return $this;
    }

public function publicKeyCertData()
{
    $role = $this->getSsoRole();
    $keyDescriptors = $role->getKeyDescriptor();
    if (empty($keyDescriptors)) {
        throw new RuntimeException("MockIdp does not have KeyInfo with an X509Certificate");
    }

    $keyInfo = $keyDescriptors[0]->getKeyInfo();
    if ($keyInfo === null) {
        throw new RuntimeException("MockIdp does not have KeyInfo with an X509Certificate");
    }

    $infos = $keyInfo->getInfo();
    foreach ($infos as $info) {
        if ($info instanceof X509Data) {
            $dataList = $info->getData();
            foreach ($dataList as $data) {
                if ($data instanceof X509Certificate) {
                    return $data->getCertificate();
                }
            }
        }
    }

    throw new RuntimeException("MockIdp does not have KeyInfo with an X509Certificate");
}

public function setCertificate($certificateFile)
{
    $certData = trim(str_replace(
        ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\n", "\r", " "],
        '',
        $this->readFile($certificateFile)
    ));

    $role = $this->getSsoRole();
    $keyDescriptors = $role->getKeyDescriptor();
    if (empty($keyDescriptors)) {
        throw new RuntimeException("MockIdp does not have KeyInfo with an X509Certificate");
    }

    $keyInfo = $keyDescriptors[0]->getKeyInfo();
    if ($keyInfo === null) {
        throw new RuntimeException("MockIdp does not have KeyInfo with an X509Certificate");
    }

    foreach ($keyInfo->getInfo() as $info) {
        if ($info instanceof X509Data) {
            foreach ($info->getData() as $data) {
                if ($data instanceof X509Certificate) {
                    $data->setCertificate($certData);
                    return;
                }
            }
        }
    }

    throw new RuntimeException("MockIdp does not have KeyInfo with an X509Certificate");
}

public function setPrivateKey($privateKeyFile)
{
    $role = $this->getSsoRole();
    $keyDescriptors = $role->getKeyDescriptor();
    if (empty($keyDescriptors)) {
        throw new RuntimeException("Unable to set private key, no KeyInfo with PrivateKey element set");
    }

    $keyInfo = $keyDescriptors[0]->getKeyInfo();
    if ($keyInfo === null) {
        throw new RuntimeException("Unable to set private key, no KeyInfo with PrivateKey element set");
    }

    foreach ($keyInfo->getInfo() as $info) {
        if ($info instanceof Chunk && $info->getLocalName() === 'PrivateKey') {
            $info->getXML()->nodeValue = $this->readFile($privateKeyFile);
            return;
        }
    }

    throw new RuntimeException("Unable to set private key, no KeyInfo with PrivateKey element set");
}

    public function getPrivateKeyPem()
    {
        /** @var SPSSODescriptor $spssoRole */
        $idpSsoRole = $this->getSsoRole();

        /** @var Chunk $certificate */
        $certificate = array_reduce(
            $idpSsoRole->getKeyDescriptor()[0]->getKeyInfo()->getInfo(),
            function ($carry, $info) {
                return $carry ?: ($info instanceof Chunk ? $info : false);
            }
        );

        return $certificate->getXML()->textContent;
    }

    /**
     * @return SSODescriptorType
     * @throws RuntimeException
     */
    protected function getSsoRole()
    {
        $roleClass = $this->getRoleClass();
        foreach ($this->descriptor->getRoleDescriptor() as $role) {
            if (!$role instanceof $roleClass) {
                continue;
            }

            return $role;
        }
        throw new RuntimeException('No IDPSSODescriptor for MockIdentityProvider?');
    }

    abstract protected function getRoleClass();

    protected function readFile($filePath)
    {
        return file_get_contents($this->findFile($filePath));
    }

protected function findFile($filePath)
{
    if (file_exists($filePath)) {
        return $filePath;
    }

    $componentPath = __DIR__ . '/../../../../';
    $fullFilePath = $componentPath . $filePath;
    if (file_exists($fullFilePath)) {
        return realpath($fullFilePath);
    }

    $pathFromRoot = ENGINEBLOCK_FOLDER_ROOT . $filePath;
    if (file_exists($pathFromRoot)) {
        return $pathFromRoot;
    }

    throw new RuntimeException(sprintf('Unable to find file: "%s"', $filePath));
}
}
