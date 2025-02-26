<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Observer;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingCms\Observer\CmsPageDeleteObserver;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Page as PageResourceModel;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class CmsPageDeleteObserverTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PageTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_IndexingCms_CmsPageDelete';
    private const EVENT_NAME = 'magento_cms_api_data_pageinterface_delete_after';

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

        $this->implementationFqcn = CmsPageDeleteObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
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

        $this->cleanIndexingEntities('klevu-js-api-key');
        
        $this->pageFixturesPool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testInvalidateCustomerDataObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: CmsPageDeleteObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testDeletedCmsPage_NewIndexingEntityIsSetToNotIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createPage([
            'store_id' => $storeFixture->getId(),
        ]);
        $pageFixture = $this->pageFixturesPool->get('test_page');

        /** @var Page $page */
        $page = $pageFixture->getPage();
        $resourceModel = $this->objectManager->get(PageResourceModel::class);
        $resourceModel->delete($page);

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ID, ['eq' => $pageFixture->getId()]);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_CMS']);
        $collection->addFieldToFilter(IndexingEntity::TARGET_PARENT_ID, ['null' => null]);
        $indexingEntities = $collection->getItems();

        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = array_shift($indexingEntities);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message:'Next Action: No Action',
        );
        $this->assertFalse(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testDeletedCmsPage_IndexingEntityIsSetToDelete(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->createPage([
            'store_id' => $storeFixture->getId(),
        ]);
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'cms_page',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        /** @var Page $page */
        $page = $pageFixture->getPage();
        $resourceModel = $this->objectManager->get(PageResourceModel::class);
        $resourceModel->delete($page);

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ID, ['eq' => $pageFixture->getId()]);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_CMS']);
        $collection->addFieldToFilter(IndexingEntity::TARGET_PARENT_ID, ['null' => null]);
        $indexingEntities = $collection->getItems();

        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = array_shift($indexingEntities);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::DELETE,
            actual: $indexingEntity->getNextAction(),
            message:'Next Action: Delete',
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testResponderServiceNotCalled_ForNonPageInterface(): void
    {
        $mockResponderService = $this->getMockBuilder(EntityUpdateResponderServiceInterface::class)
            ->getMock();
        $mockResponderService->expects($this->never())
            ->method('execute');

        $cmsPageDeleteObserver = $this->objectManager->create(CmsPageDeleteObserver::class, [
            'responderService' => $mockResponderService,
        ]);
        $this->objectManager->addSharedInstance(
            instance: $cmsPageDeleteObserver,
            className: CmsPageDeleteObserver::class,
        );

        $block = $this->objectManager->get(BlockInterface::class);
        $this->dispatchEvent(
            event: self::EVENT_NAME,
            entity: $block,
        );
    }

    /**
     * @param string $event
     * @param mixed $entity
     *
     * @return void
     */
    private function dispatchEvent(
        string $event,
        mixed $entity,
    ): void {
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            $event,
            [
                'entity' => $entity,
            ],
        );
    }
}
