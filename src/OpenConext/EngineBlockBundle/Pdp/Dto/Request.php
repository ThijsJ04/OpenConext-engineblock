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

namespace OpenConext\EngineBlockBundle\Pdp\Dto;

use JsonSerializable;
use OpenConext\EngineBlock\Assert\Assertion;
use OpenConext\EngineBlockBundle\Pdp\Dto\Request\AccessSubject;
use OpenConext\EngineBlockBundle\Pdp\Dto\Request\Resource;
use OpenConext\Value\Saml\NameIdFormat;

final class Request implements JsonSerializable
{
    /**
     * @var bool
     */
    public $returnPolicyIdList = true;

    /**
     * @var bool
     */
    public $combinedDecision = false;

    /**
     * @var AccessSubject
     */
    public $accessSubject;

    /**
     * @var \OpenConext\EngineBlockBundle\Pdp\Dto\Request\Resource
     */
    public $resource;

    public static function from(
        string $clientId,
        string $subjectId,
        string $idpEntityId,
        string $spEntityId,
        array $responseAttributes,
        string $remoteIp
    ) : Request {
        Assertion::allString(
            array_keys($responseAttributes),
            'The keys of the Response attributes must be strings'
        );
        Assertion::allIsArray($responseAttributes, 'The values of the Response attributes must be arrays');

        $request = new self;
        $request->accessSubject = new AccessSubject;
        $request->resource = new Resource;

        // Initialize attributes arrays
        $accessSubjectAttributes = [];
        $resourceAttributes = [];

        // Create subject ID attribute
        $subjectIdAttribute = new Attribute;
        $subjectIdAttribute->attributeId = NameIdFormat::UNSPECIFIED;
        $subjectIdAttribute->value = $subjectId;
        $accessSubjectAttributes[] = $subjectIdAttribute;

        // Create resource attributes
        $clientIdAttribute = new Attribute;
        $clientIdAttribute->attributeId = 'ClientID';
        $clientIdAttribute->value = $clientId;
        $resourceAttributes[] = $clientIdAttribute;

        $spEntityIdAttribute = new Attribute;
        $spEntityIdAttribute->attributeId = 'SPentityID';
        $spEntityIdAttribute->value = $spEntityId;
        $resourceAttributes[] = $spEntityIdAttribute;

        $idpEntityIdAttribute = new Attribute;
        $idpEntityIdAttribute->attributeId = 'IDPentityID';
        $idpEntityIdAttribute->value = $idpEntityId;
        $resourceAttributes[] = $idpEntityIdAttribute;

        // Process response attributes in a single loop
        foreach ($responseAttributes as $id => $values) {
            foreach ($values as $value) {
                $attribute = new Attribute;
                $attribute->attributeId = $id;
                $attribute->value = $value;
                $accessSubjectAttributes[] = $attribute;
            }
        }

        // Add IP address attribute
        $ipAttribute = new Attribute;
        $ipAttribute->attributeId = 'urn:mace:surfnet.nl:collab:xacml-attribute:ip-address';
        $ipAttribute->value = $remoteIp;
        $accessSubjectAttributes[] = $ipAttribute;

        // Assign attributes to request objects
        $request->accessSubject->attributes = $accessSubjectAttributes;
        $request->resource->attributes = $resourceAttributes;

        return $request;
    }

    public function jsonSerialize() : array
    {
        return [
            'Request' => [
                'ReturnPolicyIdList' => $this->returnPolicyIdList,
                'CombinedDecision'   => $this->combinedDecision,
                'AccessSubject'      => $this->accessSubject,
                'Resource'           => $this->resource,
            ]
        ];
    }
}
