<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Service;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Model\Update\EntityInterface;
use Klevu\IndexingApi\Model\Update\EntityInterfaceFactory as EntityUpdateInterfaceFactory;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Framework\Event\ManagerInterface;
use Psr\Log\LoggerInterface;

class EntityUpdateResponderService implements EntityUpdateResponderServiceInterface
{
    /**
     * @var ManagerInterface
     */
    private readonly ManagerInterface $eventManager;
    /**
     * @var EntityUpdateInterfaceFactory
     */
    private readonly EntityUpdateInterfaceFactory $entityUpdateFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param ManagerInterface $eventManager
     * @param EntityUpdateInterfaceFactory $entityUpdateFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ManagerInterface $eventManager,
        EntityUpdateInterfaceFactory $entityUpdateFactory,
        LoggerInterface $logger,
    ) {
        $this->eventManager = $eventManager;
        $this->entityUpdateFactory = $entityUpdateFactory;
        $this->logger = $logger;
    }

    /**
     * @param mixed[] $data
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function execute(array $data): void
    {
        if (empty($data[Entity::ENTITY_IDS])) {
            $this->logger->debug(
                message: 'Method: {method}, Debug: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => 'No entity Ids provided for KLEVU_CMS entity update.',
                ]);
            return;
        }

        try {
            $entityUpdate = $this->createEntityUpdate($data);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );

            return;
        }

        $this->eventManager->dispatch(
            'klevu_indexing_entity_update',
            [
                'entityUpdate' => $entityUpdate,
            ],
        );
    }

    /**
     * @param mixed[] $data
     *
     * @return EntityInterface
     */
    private function createEntityUpdate(array $data): EntityInterface
    {
        return $this->entityUpdateFactory->create([
            'data' => [
                Entity::ENTITY_TYPE => 'KLEVU_CMS',
                Entity::ENTITY_IDS => $data[Entity::ENTITY_IDS],
                Entity::STORE_IDS => $data[Entity::STORE_IDS] ?? [],
                Entity::CUSTOMER_GROUP_IDS => $data[Entity::CUSTOMER_GROUP_IDS] ?? [],
                Entity::ATTRIBUTES => $data[static::CHANGED_ATTRIBUTES] ?? [],
                Entity::ENTITY_SUBTYPES => $data[Entity::ENTITY_SUBTYPES] ?? [],
            ],
        ]);
    }
}
