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

namespace OpenConext\EngineBlock\Metadata\MetadataRepository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use OpenConext\EngineBlock\Metadata\Entity\AbstractRole;
use OpenConext\EngineBlock\Metadata\Entity\IdentityProvider;
use OpenConext\EngineBlock\Metadata\Entity\ServiceProvider;
use RuntimeException;

class DoctrineMetadataPushRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ClassMetadata
     */
    private $spMetadata;

    /**
     * @var ClassMetadata
     */
    private $idpMetadata;


    const ROLES_TABLE_NAME = 'sso_provider_roles_eb5';

    const FIELD_VALUE = 0;
    const FIELD_TYPE = 1;

    public function __construct(
        EntityManager $entityManager
    ) {
        $this->connection = $entityManager->getConnection();

        $this->spMetadata = $entityManager->getClassMetadata(ServiceProvider::class);
        $this->idpMetadata = $entityManager->getClassMetadata(IdentityProvider::class);
    }

    /**
     * Synchronize the database with the provided roles.
     *
     * Any roles (idp or sp) already existing the database are updated. New
     * roles are created. All identity- or service providers in the database
     * which are NOT in the provided roles are deleted at the end of the
     * synchronization process.
     *
     * This method is optimised in order to prevent running into memory limits.
     * Therefore it doesn't use the ORM layer and only the DBAL layer is used.
     * The entity manager is only used to get the metadata of the entities.
     *
     * @param AbstractRole[] $roles
     * @return SynchronizationResult
     * @throws \Exception
     */
public function synchronize(array $roles)
{
    $result = new SynchronizationResult();

    $this->connection->transactional(function () use ($roles, $result): void {
        $idpsToBeRemoved = $this->findAllRoleEntityIds($this->idpMetadata);
        $spsToBeRemoved = $this->findAllRoleEntityIds($this->spMetadata);

        foreach ($roles as $role) {
            if ($role instanceof IdentityProvider) {
                $index = array_search($role->entityId, $idpsToBeRemoved);
                if ($index === false) {
                    $this->insertRole($role, $this->idpMetadata);
                    $result->createdIdentityProviders[] = $role->entityId;
                } else {
                    unset($idpsToBeRemoved[$index]);
                    $role->id = $index;
                    $this->updateRole($role, $this->idpMetadata);
                    $result->updatedIdentityProviders[] = $role->entityId;
                }
                continue;
            }

            if ($role instanceof ServiceProvider) {
                $index = array_search($role->entityId, $spsToBeRemoved);
                if ($index === false) {
                    $this->insertRole($role, $this->spMetadata);
                    $result->createdServiceProviders[] = $role->entityId;
                } else {
                    unset($spsToBeRemoved[$index]);
                    $role->id = $index;
                    $this->updateRole($role, $this->spMetadata);
                    $result->updatedServiceProviders[] = $role->entityId;
                }
                continue;
            }

            throw new RuntimeException(
                sprintf('Unsupported role provided to synchronization: "%s"', var_export($role, true))
            );
        }

        if (!empty($idpsToBeRemoved)) {
            $this->deleteRolesByIds(array_keys($idpsToBeRemoved), $this->idpMetadata);
            $result->removedIdentityProviders = array_values($idpsToBeRemoved);
        }

        if (!empty($spsToBeRemoved)) {
            $this->deleteRolesByIds(array_keys($spsToBeRemoved), $this->spMetadata);
            $result->removedServiceProviders = array_values($spsToBeRemoved);
        }
    });

    return $result;
}

    private function insertRole(AbstractRole $role, ClassMetadata $metadata)
    {
        $query = $this->connection->createQueryBuilder()
            ->insert(self::ROLES_TABLE_NAME);

        $normalized = $this->addInsertQueryParameters($role, $query, $metadata);

        $stmt = $this->connection->prepare($query->getSQL());
        $this->bindParameters($normalized, $stmt);
        $stmt->executeQuery();
    }

    private function updateRole(AbstractRole $role, ClassMetadata $metadata)
    {
        $query = $this->connection->createQueryBuilder()
            ->update(self::ROLES_TABLE_NAME);

        $normalized = $this->addUpdateQueryParameters($role, $query, $metadata);

        $stmt = $this->connection->prepare($query->getSQL());
        $this->bindParameters($normalized, $stmt);
        $stmt->executeQuery();
    }

    private function deleteRolesByIds(array $roles, ClassMetadata $metadata)
    {
        $query = $this->connection->createQueryBuilder()
            ->delete(self::ROLES_TABLE_NAME)
            ->where('id IN (:ids)')
            ->setParameter('ids', $roles, \Doctrine\DBAL\ArrayParameterType::INTEGER);

        $this->addDiscriminatorQuery($query, $metadata);

        $result = $query->executeStatement();
        return $result;
    }

    private function findAllRoleEntityIds(ClassMetadata $metadata)
    {
        $query = $this->connection->createQueryBuilder()
            ->select('id, entity_id')
            ->from(self::ROLES_TABLE_NAME);

        assert($query instanceof QueryBuilder);
        $this->addDiscriminatorQuery($query, $metadata);

        $result = $query->executeQuery();

        $results = [];
        foreach ($result->fetchAllAssociative() as $record) {
            $results[$record['id']] = $record['entity_id'];
        }

        return $results;
    }

    private function addInsertQueryParameters(AbstractRole $role, QueryBuilder $query, ClassMetadata $metadata)
    {
        $normalized = $this->normalizeData($role, $metadata);
        foreach (array_keys($normalized) as $id) {
            $query->setValue($id, ":$id");
        }
        return $normalized;
    }

    private function addUpdateQueryParameters(AbstractRole $role, QueryBuilder $query, ClassMetadata $metadata)
    {
        $normalized = $this->normalizeData($role, $metadata);
        foreach (array_keys($normalized) as $id) {
            $query->set($id, ":$id");
        }
        $query->where('entity_id = :entity_id');

        $this->addDiscriminatorQuery($query, $metadata);

        return $normalized;
    }

    private function bindParameters($normalized, Statement $statement)
    {
        foreach ($normalized as $id => $value) {
            $statement->bindValue($id, $value[self::FIELD_VALUE], $value[self::FIELD_TYPE]);
        }
    }

    private function addDiscriminatorQuery(QueryBuilder $queryBuilder, ClassMetadata $metadata)
    {
        $queryBuilder->andWhere(sprintf('%s = :%s', $metadata->discriminatorColumn->fieldName, $metadata->discriminatorColumn->name))
            ->setParameter($metadata->discriminatorColumn->name, $metadata->discriminatorValue, $metadata->discriminatorColumn->type);
    }

private function normalizeData(AbstractRole $role, ClassMetadata $metadata)
{
    $result = [];
    $fieldMappings = $metadata->fieldMappings;
    $propertyAccessors = $metadata->propertyAccessors;
    $discriminatorColumn = $metadata->discriminatorColumn;

    foreach ($fieldMappings as $id => $columnInfo) {
        $result[$columnInfo->columnName] = [
            self::FIELD_VALUE => $propertyAccessors[$id]->getValue($role),
            self::FIELD_TYPE => $columnInfo->type,
        ];
    }

    unset($result["id"]);

    $result[$discriminatorColumn->name] = [
        self::FIELD_VALUE => $metadata->discriminatorValue,
        self::FIELD_TYPE => $discriminatorColumn->type,
    ];

    return $result;
}
}
