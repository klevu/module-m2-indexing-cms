<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Service\EntityDiscoveryOrchestratorService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\TestFixtures\Cms\PageFixture;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\DiscoveryOrchestratorService::class
 * @method EntityDiscoveryOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityDiscoveryOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityDiscoveryOrchestratorServiceTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PageTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;
    use WebsiteTrait;

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

        $this->implementationFqcn = EntityDiscoveryOrchestratorService::class;
        $this->interfaceFqcn = EntityDiscoveryOrchestratorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->pageFixturesPool = $this->objectManager->get(PageFixturesPool::class);

        $this->cleanIndexingEntities('klevu-js-api-key');
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pageFixturesPool->rollback();
        $this->storeFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();

        $this->cleanIndexingEntities('klevu-js-api-key');
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_cms 0
     */
    public function testExecute_AddsNewCategories_AsIndexable_WhenExcludeChecksDisabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $pagesCollectionCount = count($this->getPages($store));

        $this->createPage([
            'key' => 'test_page_1',
            'identifier' => 'test-page-1',
            'is_active' => false,
        ]);
        $this->createPage([
            'key' => 'test_page_2',
            'identifier' => 'test-page-2',
            'store_id' => $storeFixture->getId(),
            'is_active' => false,
        ]);
        $page2 = $this->pageFixturesPool->get('test_page_2');

        $this->cleanIndexingEntities($apiKey);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_CMS', apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCmsIndexingEntities($apiKey);
        $this->assertCount(
            expectedCount: 2 + $pagesCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );
        $this->assertAddIndexingEntity($indexingEntities, $page2, $apiKey, true);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-2
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-2
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_cms 1
     */
    public function testExecute_AddsNewCategories_AsIndexable_WhenExcludeChecksEnabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture1->get();

        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');

        $pagesCollectionCount = count($this->getPages($store1));

        $this->createPage([
            'key' => 'test_page_1',
            'identifier' => 'test-page-1',
            'store_id' => $storeFixture2->getId(),
            'is_active' => true,
        ]);
        $this->createPage([
            'key' => 'test_page_2',
            'identifier' => 'test-page-2',
            'store_id' => $storeFixture1->getId(),
            'is_active' => false,
        ]);
        $page2 = $this->pageFixturesPool->get('test_page_2');
        $this->createPage([
            'key' => 'test_page_3',
            'identifier' => 'test-page-3',
            'store_id' => $storeFixture1->getId(),
            'is_active' => true,
        ]);
        $page3 = $this->pageFixturesPool->get('test_page_3');

        $this->cleanIndexingEntities($apiKey);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_CMS', apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCmsIndexingEntities($apiKey);
        $this->assertCount(
            expectedCount: 2 + $pagesCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );

        $this->assertAddIndexingEntity($indexingEntities, $page2, $apiKey, false);
        $this->assertAddIndexingEntity($indexingEntities, $page3, $apiKey, true);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_cms 1
     */
    public function testExecute_HandlesMultipleStores_SameKeys(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture1->get();

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture2->get();

        $pagesCollectionCount1 = count($this->getPages($store1));
        $pagesCollectionCount2 = count($this->getPages($store2));

        $pagesCollectionCount = max($pagesCollectionCount1, $pagesCollectionCount2);

        $this->createPage([
            'key' => 'test_page_1',
            'identifier' => 'test-page-1',
            'stores' => [
                $storeFixture1->getId(),
                $storeFixture2->getId(),
            ],
            'is_active' => true,
        ]);
        $page1 = $this->pageFixturesPool->get('test_page_1');
        $this->createPage([
            'key' => 'test_page_2',
            'identifier' => 'test-page-2',
            'stores' => [
                $storeFixture1->getId(),
                $storeFixture2->getId(),
            ],
            'is_active' => false,
        ]);
        $page2 = $this->pageFixturesPool->get('test_page_2');
        $this->createPage([
            'key' => 'test_page_3',
            'identifier' => 'test-page-3',
            'stores' => [
                $storeFixture1->getId(),
                $storeFixture2->getId(),
            ],
            'is_active' => true,
        ]);
        $page3 = $this->pageFixturesPool->get('test_page_3');

        $this->cleanIndexingEntities($apiKey);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_CMS', apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCmsIndexingEntities($apiKey);
        $this->assertCount(
            expectedCount: 3 + $pagesCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );

        $this->assertAddIndexingEntity($indexingEntities, $page1, $apiKey, true);
        $this->assertAddIndexingEntity($indexingEntities, $page2, $apiKey, false);
        $this->assertAddIndexingEntity($indexingEntities, $page3, $apiKey, true);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_cms 1
     */
    public function testExecute_SetsExistingIndexableCategoryForDeletion(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture1->get();

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture2->get();

        $pagesCollectionCount1 = count($this->getPages($store1));
        $pagesCollectionCount2 = count($this->getPages($store2));

        $pagesCollectionCount = max($pagesCollectionCount1, $pagesCollectionCount2);

        $this->createPage([
            'key' => 'test_page_1',
            'identifier' => 'test-page-1',
            'stores' => [
                $storeFixture1->getId(),
                $storeFixture2->getId(),
            ],
            'is_active' => false,
        ]);
        $page1 = $this->pageFixturesPool->get('test_page_1');
        $this->createPage([
            'key' => 'test_page_2',
            'identifier' => 'test-page-2',
            'stores' => [
                $storeFixture1->getId(),
                $storeFixture2->getId(),
            ],
            'is_active' => true,
        ]);
        $page2 = $this->pageFixturesPool->get('test_page_2');
        $this->createPage([
            'key' => 'test_page_3',
            'identifier' => 'test-page-3',
            'stores' => [
                $storeFixture2->getId(),
            ],
            'is_active' => true,
        ]);
        $page3 = $this->pageFixturesPool->get('test_page_3');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => (int)$page1->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => (int)$page2->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_CMS', apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCmsIndexingEntities($apiKey);
        $this->assertCount(
            expectedCount: 3 + $pagesCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );

        $this->assertDeleteIndexingEntity($indexingEntities, $page1, $apiKey, Actions::DELETE, true);
        $this->assertAddIndexingEntity($indexingEntities, $page2, $apiKey, true, true);
        $this->assertAddIndexingEntity($indexingEntities, $page3, $apiKey, true);
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param PageFixture $pageFixture
     * @param string $apiKey
     * @param bool $isIndexable
     * @param bool $isIndexableChange
     *
     * @return void
     */
    private function assertAddIndexingEntity(
        array $indexingEntities,
        PageFixture $pageFixture,
        string $apiKey,
        bool $isIndexable,
        bool $isIndexableChange = false,
    ): void {
        $indexingEntityArray = $this->filterIndexEntities($indexingEntities, $pageFixture->getId(), $apiKey);
        $indexingEntity = array_shift($indexingEntityArray);
        $this->assertSame(
            expected: (int)$pageFixture->getId(),
            actual: $indexingEntity->getTargetId(),
            message: 'Target Id',
        );
        $this->assertNull(
            $indexingEntity->getTargetParentId(),
            'Target Patent Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CMS',
            actual: $indexingEntity->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $isIndexable
                ? Actions::ADD
                : Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message: 'Next Action',
        );
        if (!$isIndexableChange) {
            $this->assertSame(
                expected: Actions::NO_ACTION,
                actual: $indexingEntity->getLastAction(),
                message: 'Last Action',
            );
            $this->assertNull(
                actual: $indexingEntity->getLastActionTimestamp(),
                message: 'Last Action Timestamp',
            );
        }
        $this->assertNull(
            actual: $indexingEntity->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertSame(
            expected: $isIndexable,
            actual: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param PageFixture $pageFixture
     * @param string $apiKey
     * @param Actions $nextAction
     * @param bool $isIndexable
     *
     * @return void
     */
    private function assertDeleteIndexingEntity(
        array $indexingEntities,
        PageFixture $pageFixture,
        string $apiKey,
        Actions $nextAction = Actions::NO_ACTION,
        bool $isIndexable = true,
    ): void {
        $indexingEntityArray = $this->filterIndexEntities($indexingEntities, $pageFixture->getId(), $apiKey);
        $indexingEntity = array_shift($indexingEntityArray);
        $this->assertSame(
            expected: (int)$pageFixture->getId(),
            actual: $indexingEntity->getTargetId(),
            message: 'Target Id',
        );
        $this->assertNull(
            $indexingEntity->getTargetParentId(),
            'Target Patent Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CMS',
            actual: $indexingEntity->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $nextAction,
            actual: $indexingEntity->getNextAction(),
            message: 'Next Action',
        );
        $this->assertNotNull(
            actual: $indexingEntity->getLastAction(),
            message: 'Last Action',
        );
        $this->assertNotNull(
            actual: $indexingEntity->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertSame(
            expected: $isIndexable,
            actual: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param int $entityId
     * @param string $apiKey
     *
     * @return IndexingEntityInterface[]
     */
    private function filterIndexEntities(array $indexingEntities, int $entityId, string $apiKey): array
    {
        return array_filter(
            array: $indexingEntities,
            callback: static function (IndexingEntityInterface $indexingEntity) use ($entityId, $apiKey) {
                return (int)$entityId === (int)$indexingEntity->getTargetId()
                    && $apiKey === $indexingEntity->getApiKey();
            },
        );
    }

    /**
     * @return IndexingEntityInterface[]
     */
    private function getCmsIndexingEntities(?string $apiKey = null): array
    {
        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(
            field: IndexingEntity::TARGET_ENTITY_TYPE,
            condition: ['eq' => 'KLEVU_CMS'],
        );
        if ($apiKey) {
            $collection->addFieldToFilter(
                field: IndexingEntity::API_KEY,
                condition: ['eq' => $apiKey],
            );
        }

        return $collection->getItems();
    }

    /**
     * @param StoreInterface|null $store
     *
     * @return CategoryInterface[]
     */
    private function getPages(?StoreInterface $store = null): array
    {
        $categoryCollectionFactory = $this->objectManager->get(PageCollectionFactory::class);
        $pageCollection = $categoryCollectionFactory->create();
        if ($store) {
            $connection = $pageCollection->getConnection();
            $select = $pageCollection->getSelect();
            $select->joinInner(
                name: ['store' => $pageCollection->getTable('cms_page_store')],
                cond: implode(
                    ' ' . Select::SQL_AND . ' ',
                    [
                        'main_table.page_id = store.row_id',
                        $connection->quoteInto('store.store_id IN (0,?)', $store->getId()),
                    ],
                ),
                cols: ['store.store_id'],
            );
        }

        return $pageCollection->getItems();
    }
}