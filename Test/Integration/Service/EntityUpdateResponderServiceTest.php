<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Service;

use Klevu\Indexing\Model\Update\Entity as EntityUpdate;
use Klevu\IndexingApi\Model\Update\EntityInterfaceFactory as EntityUpdateInterfaceFactory;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingCms\Service\EntityUpdateResponderService;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\IndexingCms\Service\EntityUpdateResponderService::class
 * @method EntityUpdateResponderServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityUpdateResponderServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityUpdateResponderServiceTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = EntityUpdateResponderService::class;
        $this->interfaceFqcn = EntityUpdateResponderServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_WithEmptyEntityIds_DoesNotDispatchEvent(): void
    {
        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->never())
            ->method('dispatch');

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('debug')
            ->with(
                'Method: {method}, Debug: {message}',
                [
                    'method' => 'Klevu\IndexingCms\Service\EntityUpdateResponderService::execute',
                    'message' => 'No entity Ids provided for KLEVU_CMS entity update.',
                ],
            );

        $service = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
            'logger' => $mockLogger,
        ]);
        $service->execute([]);
    }

    public function testExecute_WithData_TriggersDispatchEvent(): void
    {
        $expectedData = [
            EntityUpdate::ENTITY_IDS => [1, 2, 3],
            EntityUpdate::STORE_IDS => [1, 2],
            EntityUpdate::CUSTOMER_GROUP_IDS => [10, 11],
            EntityUpdate::ATTRIBUTES => ['price', 'stock'],
        ];

        $entityUpdateFactory = $this->objectManager->get(EntityUpdateInterfaceFactory::class);
        $entityUpdate = $entityUpdateFactory->create([
            'data' => array_merge(
                [EntityUpdate::ENTITY_TYPE => 'KLEVU_CMS'],
                $expectedData,
            ),
        ]);

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'klevu_indexing_entity_update',
                [
                    'entityUpdate' => $entityUpdate,
                ],
            );

        $data = [
            EntityUpdate::ENTITY_IDS => [1, 2, 3],
            EntityUpdate::STORE_IDS => [1, 2],
            EntityUpdate::CUSTOMER_GROUP_IDS => [10, 11],
            EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => ['price', 'stock'],
        ];

        $service = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
        ]);
        $service->execute($data);
    }

    public function testExecute_WithOnlyStoreIDs_TriggersDispatchEvent(): void
    {
        $data = [
            EntityUpdate::ENTITY_IDS => [1, 2, 3],
        ];

        $entityUpdateFactory = $this->objectManager->get(EntityUpdateInterfaceFactory::class);
        $entityUpdate = $entityUpdateFactory->create([
            'data' => array_merge(
                [
                    EntityUpdate::ENTITY_TYPE => 'KLEVU_CMS',
                    EntityUpdate::STORE_IDS => [],
                    EntityUpdate::CUSTOMER_GROUP_IDS => [],
                    EntityUpdate::ATTRIBUTES => [],
                ],
                $data,
            ),
        ]);

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'klevu_indexing_entity_update',
                [
                    'entityUpdate' => $entityUpdate,
                ],
            );

        $service = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
        ]);
        $service->execute($data);
    }

    /**
     * @testWith ["entityType", "entity_type"]
     *           ["storeIds", "stores"]
     *           ["customerGroupIds", "cusGroups"]
     *           ["attributes", "attribute_list"]
     */
    public function testExecute_HandlesException(string $key, string $invalidKey): void
    {
        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->never())
            ->method('dispatch');

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\IndexingCms\Service\EntityUpdateResponderService::execute',
                    'message' => sprintf(
                        'Invalid key provided in creation of %s. Key %s',
                        EntityUpdate::class,
                        $invalidKey,
                    ),
                ],
            );

        $exception = new \InvalidArgumentException(
            sprintf(
                'Invalid key provided in creation of %s. Key %s',
                EntityUpdate::class,
                $invalidKey,
            ),
        );
        $mockEntityUpdateFactory = $this->getMockBuilder(EntityUpdateInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEntityUpdateFactory->expects($this->once())
            ->method('create')
            ->willThrowException($exception);

        $data = [
            'entityType' => 'KLEVU_CMS',
            'entityIds' => [1, 2, 3],
            'storeIds' => [1, 2],
            'customerGroupIds' => [1, 2],
            'attributes' => ["price", "stock", "categories"],
        ];
        $data[$invalidKey] = $data[$key];
        unset($data[$key]);

        $service = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
            'entityUpdateFactory' => $mockEntityUpdateFactory,
            'logger' => $mockLogger,
        ]);
        $service->execute($data);
    }
}
