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

namespace OpenConext\EngineBlockFunctionalTestingBundle\Fixtures;

use OpenConext\EngineBlockBundle\Pdp\Dto\Request;
use OpenConext\EngineBlockBundle\Pdp\Dto\Response;
use OpenConext\EngineBlockBundle\Pdp\Dto\Response\AssociatedAdvice;
use OpenConext\EngineBlockBundle\Pdp\Dto\Response\AttributeAssignment;
use OpenConext\EngineBlockBundle\Pdp\Dto\Response\Obligation;
use OpenConext\EngineBlockBundle\Pdp\Dto\Response\Status;
use OpenConext\EngineBlockBundle\Pdp\Dto\Response\StatusCode;
use OpenConext\EngineBlockBundle\Pdp\PdpClientInterface;
use OpenConext\EngineBlockBundle\Pdp\PolicyDecision;
use OpenConext\EngineBlockFunctionalTestingBundle\Exception\RuntimeException;
use OpenConext\EngineBlockFunctionalTestingBundle\Fixtures\DataStore\AbstractDataStore;

final class FunctionalTestingPdpClient implements PdpClientInterface
{
    /**
     * @var AbstractDataStore
     */
    private $dataStore;

    /**
     * @var string
     */
    private $policyDecisionFixture;

    public function __construct(AbstractDataStore $dataStore)
    {
        $this->dataStore = $dataStore;
        $this->policyDecisionFixture = $dataStore->load();
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
public function requestDecisionFor(Request $request) : PolicyDecision
{
    $pdpResponse = new Response();
    $decision = $this->policyDecisionFixture;
    $additionalData = [];

    if (is_array($this->policyDecisionFixture)) {
        $decision = $this->policyDecisionFixture[0];
        $additionalData = $this->policyDecisionFixture;
    }

    switch ($decision) {
        case PolicyDecision::DECISION_DENY:
            $pdpResponse->decision = PolicyDecision::DECISION_DENY;
            $idp = $additionalData['idpName'] ?? '';

            $associatedAdvice = new AssociatedAdvice();
            $associatedAdvice->attributeAssignments = [
                $this->createAttributeAssignment('DenyMessage:en', sprintf('Students of %s do not have access to this resource', $idp)),
                $this->createAttributeAssignment('DenyMessage:nl', sprintf('Studenten van %s hebben geen toegang tot deze dienst', $idp)),
                $this->createAttributeAssignment('IdPOnly', true)
            ];
            $pdpResponse->associatedAdvices = [$associatedAdvice];
            break;
        case PolicyDecision::DECISION_INDETERMINATE:
            $pdpResponse->decision = PolicyDecision::DECISION_INDETERMINATE;
            $pdpResponse->status = $this->createIndeterminateStatus();
            break;
        case PolicyDecision::DECISION_NOT_APPLICABLE:
            $pdpResponse->decision = PolicyDecision::DECISION_NOT_APPLICABLE;
            break;
        case PolicyDecision::DECISION_PERMIT:
            $pdpResponse->decision = PolicyDecision::DECISION_PERMIT;
            $loaId = $additionalData['loaId'] ?? '';
            if ($loaId) {
                $pdpResponse->obligations = [$this->createLoaObligation($loaId)];
            }
            break;
        default:
            $invalidData = is_string($this->policyDecisionFixture)
                ? $this->policyDecisionFixture
                : get_debug_type($this->policyDecisionFixture);

            throw new RuntimeException(
                sprintf(
                    'Invalid Policy Decision fixture given: expected one of "%s", got: "%s"',
                    implode(', ', [
                        PolicyDecision::DECISION_DENY,
                        PolicyDecision::DECISION_INDETERMINATE,
                        PolicyDecision::DECISION_NOT_APPLICABLE,
                        PolicyDecision::DECISION_PERMIT,
                    ]),
                    $invalidData
                )
            );
    }

    return PolicyDecision::fromResponse($pdpResponse);
}

private function createAttributeAssignment(string $attributeId, $value): AttributeAssignment
{
    $assignment = new AttributeAssignment();
    $assignment->attributeId = $attributeId;
    $assignment->value = $value;
    return $assignment;
}

private function createIndeterminateStatus(): Status
{
    $status = new Status();
    $status->statusDetail = <<<XML_WRAP
    <MissingAttributeDetail
        Category="urn:oasis:names:tc:xacml:1.0:subject-category:access-subject"
        AttributeId="urn:mace:dir:attribute-def:eduPersonAffiliation"
        DataType="http://www.w3.org/2001/XMLSchema#string"/>
XML_WRAP;
    $status->statusCode = new StatusCode();
    $status->statusCode->value = 'urn:oasis:names:tc:xacml:1.0:status:missing-attribute';
    $status->statusMessage = 'Missing required attribute';
    return $status;
}

private function createLoaObligation(string $loaId): Obligation
{
    $obligation = new Obligation();
    $obligation->id = 'urn:openconext:stepup:loa';
    $attributeAssignment = new AttributeAssignment();
    $attributeAssignment->category = 'urn:oasis:names:tc:xacml:1.0:subject-category:access-subject';
    $attributeAssignment->attributeId = 'urn:loa:level';
    $attributeAssignment->value = $loaId;
    $attributeAssignment->dataType = 'http://www.w3.org/2001/XMLSchema#string';
    $obligation->attributeAssignments[] = $attributeAssignment;
    return $obligation;
}

    public function receiveDenyResponse()
    {
        $this->dataStore->save(PolicyDecision::DECISION_DENY);
    }

    /**
     * Stores a deny message with additional information about the idp
     */
    public function receiveSpecificDenyResponse(string $idpName)
    {
        $data = [
            PolicyDecision::DECISION_DENY,
            'idpName' => $idpName,
        ];
        $this->dataStore->save($data);
    }

    public function receiveIndeterminateResponse()
    {
        $this->dataStore->save(PolicyDecision::DECISION_INDETERMINATE);
    }

    public function receivePermitResponse()
    {
        $this->dataStore->save(PolicyDecision::DECISION_PERMIT);
    }

    public function receiveObligationResponse(string $loaId)
    {
        $data = [
            PolicyDecision::DECISION_PERMIT,
            'loaId' => $loaId,
        ];
        $this->dataStore->save($data);
    }

    public function receiveNotApplicableResponse()
    {
        $this->dataStore->save(PolicyDecision::DECISION_NOT_APPLICABLE);
    }

    public function clear()
    {
        $this->dataStore->save(null);
    }

    private function getIdpFromAdditionalData(array $additionalData) : string
    {
        $idp = '';
        if (array_key_exists('idpName', $additionalData)) {
            $idp = $additionalData['idpName'];
        }

        return $idp;
    }

    private function getLoaIdFromAdditionalData(array $additionalData) : string
    {
        $loaId = '';
        if (array_key_exists('loaId', $additionalData)) {
            $loaId = $additionalData['loaId'];
        }

        return $loaId;
    }
}
