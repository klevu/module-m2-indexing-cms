<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Ui\Component\Listing;

use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Test\Integration\Traits\SyncHistoryEntitiesTrait;
use Klevu\Indexing\Ui\Component\Listing\EntitySyncHistoryDataProvider;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingCms\Ui\Component\Listing\EntitySyncHistoryDataProvider as EntitySyncHistoryDataProviderVitrualType; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntitySyncHistoryDataProvider
 * @method DataProviderInterface instantiateTestObject(?array $arguments = null)
 * @method DataProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea adminhtml
 */
class EntitySyncHistoryDataProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use SyncHistoryEntitiesTrait;
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

        $this->implementationFqcn = EntitySyncHistoryDataProviderVitrualType::class; // @phpstan-ignore-line
        $this->implementationForVirtualType = EntitySyncHistoryDataProvider::class;
        $this->interfaceFqcn = DataProviderInterface::class;
        $this->constructorArgumentDefaults = [
            'name' => 'klevu_entity_sync_history',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGetData_ReturnsArray_WhenTargetIdNotSetInRequest(): void
    {
        $dataProvider = $this->instantiateTestObject();
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key: 'totalRecords', array: $result);
        $this->assertSame(expected: 0, actual: $result['totalRecords']);
        $this->assertArrayHasKey(key:'items', array: $result);
        $this->assertCount(expectedCount: 0, haystack: $result['items']);
    }

    public function testGetData_ReturnsArray_WhenNoHistoryRecords(): void
    {
        $request = $this->objectManager->get(RequestInterface::class);
        $request->setParams(['target_id' => 1]);

        $dataProvider = $this->instantiateTestObject([
            'name' => 'klevu_entity_sync_history',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'request' => $request,
        ]);
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key: 'totalRecords', array: $result);
        $this->assertSame(expected: 0, actual: $result['totalRecords']);
        $this->assertArrayHasKey(key:'items', array: $result);
        $this->assertCount(expectedCount: 0, haystack: $result['items']);
    }

    public function testGetData_ReturnsOnlyProductHistoryForTargetId(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-14 09:54:00',
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => 2,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-14 09:55:00',
            SyncHistoryEntityRecord::IS_SUCCESS => false,
            SyncHistoryEntityRecord::MESSAGE => 'Batch rejected',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-14 09:56:00',
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::DELETE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-14 09:57:00',
            SyncHistoryEntityRecord::IS_SUCCESS => false,
            SyncHistoryEntityRecord::MESSAGE => 'Batch rejected',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityRecord::TARGET_ID => 3,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-14 09:58:00',
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);

        $request = $this->objectManager->get(RequestInterface::class);
        $request->setParams(['target_id' => 1]);

        $dataProvider = $this->instantiateTestObject([
            'name' => 'klevu_entity_sync_history',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'request' => $request,
        ]);
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key: 'totalRecords', array: $result);
        $this->assertSame(expected: 2, actual: $result['totalRecords']);

        $this->assertArrayHasKey(key:'items', array: $result);
        $this->assertCount(expectedCount: 2, haystack: $result['items']);

        $record1Array = array_filter(
            array: $result['items'],
            callback: static fn (array $record): bool => (
                $record[SyncHistoryEntityRecord::TARGET_ID] === 1
                && $record[SyncHistoryEntityRecord::TARGET_PARENT_ID] === null
            ),
        );
        $record1 = array_shift($record1Array);
        $this->assertSame(
            expected: 'KLEVU_CMS',
            actual: $record1[SyncHistoryEntityRecord::TARGET_ENTITY_TYPE] ?? null,
        );
        $this->assertSame(
            expected: 1,
            actual: $record1[SyncHistoryEntityRecord::TARGET_ID] ?? null,
        );
        $this->assertNull(
            actual: $record1[SyncHistoryEntityRecord::TARGET_PARENT_ID],
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $record1[SyncHistoryEntityRecord::ACTION] ?? null,
        );
        $this->assertSame(
            expected: '14/05/2024 09:54:00',
            actual: $record1[SyncHistoryEntityRecord::ACTION_TIMESTAMP] ?? null,
        );
        $this->assertSame(
            expected: 1,
            actual: $record1[SyncHistoryEntityRecord::IS_SUCCESS] ?? null,
        );
        $this->assertSame(
            expected: 'Batch accepted successfully',
            actual: $record1[SyncHistoryEntityRecord::MESSAGE] ?? null,
        );

        $record2Array = array_filter(
            array: $result['items'],
            callback: static fn (array $record): bool => (
                $record[SyncHistoryEntityRecord::TARGET_ID] === 1
                && $record[SyncHistoryEntityRecord::TARGET_PARENT_ID] === 2
            ),
        );
        $record2 = array_shift($record2Array);
        $this->assertSame(
            expected: 'KLEVU_CMS',
            actual: $record2[SyncHistoryEntityRecord::TARGET_ENTITY_TYPE] ?? null,
        );
        $this->assertSame(
            expected: 1,
            actual: $record2[SyncHistoryEntityRecord::TARGET_ID] ?? null,
        );
        $this->assertSame(
            expected: 2,
            actual: $record2[SyncHistoryEntityRecord::TARGET_PARENT_ID] ?? null,
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $record2[SyncHistoryEntityRecord::ACTION] ?? null,
        );
        $this->assertSame(
            expected: '14/05/2024 09:55:00',
            actual: $record2[SyncHistoryEntityRecord::ACTION_TIMESTAMP] ?? null,
        );
        $this->assertSame(
            expected: 0,
            actual: $record2[SyncHistoryEntityRecord::IS_SUCCESS] ?? null,
        );
        $this->assertSame(
            expected: 'Batch rejected',
            actual: $record2[SyncHistoryEntityRecord::MESSAGE] ?? null,
        );

        $this->clearSyncHistoryEntities(apiKey: $apiKey);
    }

    public function testGetData_ReturnsInjectedDateFormat(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-14 09:54:00',
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);

        $request = $this->objectManager->get(RequestInterface::class);
        $request->setParams(['target_id' => 1]);

        $dataProvider = $this->instantiateTestObject([
            'name' => 'klevu_entity_sync_history',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'request' => $request,
            'dateFormat' => 'm/d/Y h:i:s',
        ]);
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key: 'totalRecords', array: $result);
        $this->assertSame(expected: 1, actual: $result['totalRecords']);

        $this->assertArrayHasKey(key:'items', array: $result);
        $this->assertCount(expectedCount: 1, haystack: $result['items']);

        $record = array_shift($result['items']);
        $this->assertSame(
            expected: '05/14/2024 09:54:00',
            actual: $record[SyncHistoryEntityRecord::ACTION_TIMESTAMP] ?? null,
        );

        $this->clearSyncHistoryEntities(apiKey: $apiKey);
    }
}
