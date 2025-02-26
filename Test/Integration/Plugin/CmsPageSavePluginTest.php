<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Plugin;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingCms\Plugin\CmsPageResourceModelPlugin;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Page as PageResourceModel;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\IndexingCms\Plugin\CmsPageResourceModelPlugin
 * @method CmsPageResourceModelPlugin instantiateTestObject(?array $arguments = null)
 * @method CmsPageResourceModelPlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CmsPageSavePluginTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PageTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;
    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingCms::CmsPageResourceModelPlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = CmsPageResourceModelPlugin::class;
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

        $this->pageFixturesPool->rollback();
        $this->storeFixturesPool->rollback();

        $this->cleanIndexingEntities('klevu-js-api-key');
    }

    /**
     * @magentoAppArea global
     * @magentoAppIsolation enabled
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(CmsPageResourceModelPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoAppArea adminhtml
     */
    public function testPlugin_InterceptsCallsToTheField_InAdminScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(CmsPageResourceModelPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForNewPages_setsNextActonAdd(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $pageFactory = $this->objectManager->get(PageInterfaceFactory::class);
        /** @var Page&PageInterface $page */
        $page = $pageFactory->create();
        $page->setIdentifier('page-test-url');
        $page->setTitle('Page Test');
        $page->setIsActive(true);
        $page->setTitle('Page Test - Title');
        $page->setContentHeading('Page Test - Content Heading');
        $page->setContent('Page Test - Content');
        $page->setStoreId($store->getId());

        $pageResourceModel = $this->objectManager->get(PageResourceModel::class);
        $pageResourceModel->save($page);

        $cmsIndexingEntity = $this->getIndexingEntityForPage($apiKey, $page);

        $this->assertNotNull($cmsIndexingEntity);
        $this->assertSame(expected: Actions::ADD, actual: $cmsIndexingEntity->getNextAction());
    }

    /**
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForExistingPages_WhichHasNotYetBeenSynced_DoesNotChangeNextAction(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $pageFactory = $this->objectManager->get(PageInterfaceFactory::class);
        /** @var Page&PageInterface $page */
        $page = $pageFactory->create();
        $page->setIdentifier('page-test-url');
        $page->setTitle('Page Test');
        $page->setIsActive(true);
        $page->setTitle('Page Test - Title');
        $page->setContentHeading('Page Test - Content Heading');
        $page->setContent('Page Test - Content');
        $page->setStoreId($store->getId());

        $pageResourceModel = $this->objectManager->get(PageResourceModel::class);
        $pageResourceModel->save($page);

        $cmsIndexingEntity = $this->getIndexingEntityForPage($apiKey, $page);
        $this->assertNotNull($cmsIndexingEntity);
        $this->assertSame(expected: Actions::ADD, actual: $cmsIndexingEntity->getNextAction());

        $page->setTitle('Page Test: New Title');
        $pageResourceModel->save($page);

        $cmsIndexingEntity = $this->getIndexingEntityForPage($apiKey, $page);
        $this->assertNotNull($cmsIndexingEntity);
        $this->assertSame(expected: Actions::ADD, actual: $cmsIndexingEntity->getNextAction());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForExistingPages_UpdateNextAction(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $pageFactory = $this->objectManager->get(PageInterfaceFactory::class);
        /** @var Page&PageInterface $page */
        $page = $pageFactory->create();
        $page->setIdentifier('page-test-url');
        $page->setTitle('Page Test');
        $page->setIsActive(true);
        $page->setTitle('Page Test - Title');
        $page->setContentHeading('Page Test - Content Heading');
        $page->setContent('Page Test - Content');
        $page->setStoreId($store->getId());

        $pageResourceModel = $this->objectManager->get(PageResourceModel::class);
        $pageResourceModel->save($page);
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $page->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'cms_page',
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $page->setTitle('Page Test: New Title');
        $pageResourceModel->save($page);

        $cmsIndexingEntity = $this->getIndexingEntityForPage($apiKey, $page);
        $this->assertNotNull($cmsIndexingEntity);
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $cmsIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $cmsIndexingEntity->getNextAction()->value,
        );
    }

    /**
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_cms 1
     */
    public function testAroundSave_ForExistingPages_NotIndexable_DoesNotChangeNextAction(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $pageFactory = $this->objectManager->get(PageInterfaceFactory::class);
        /** @var Page&PageInterface $page */
        $page = $pageFactory->create();
        $page->setIdentifier('page-test-url');
        $page->setTitle('Page Test');
        $page->setIsActive(false);
        $page->setTitle('Page Test - Title');
        $page->setContentHeading('Page Test - Content Heading');
        $page->setContent('Page Test - Content');
        $page->setStoreId($store->getId());

        $pageResourceModel = $this->objectManager->get(PageResourceModel::class);
        $pageResourceModel->save($page);

        $cmsIndexingEntity = $this->getIndexingEntityForPage($apiKey, $page);
        $this->assertNotNull($cmsIndexingEntity);
        $this->assertFalse(condition: $cmsIndexingEntity->getIsIndexable());
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $cmsIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value
                . ', received ' . $cmsIndexingEntity->getNextAction()->value,
        );

        $page->setTitle('Page Test: New Title');
        $pageResourceModel->save($page);

        $cmsIndexingEntity = $this->getIndexingEntityForPage($apiKey, $page);
        $this->assertNotNull($cmsIndexingEntity);
        $this->assertFalse(condition: $cmsIndexingEntity->getIsIndexable());
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $cmsIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value
                . ', received ' . $cmsIndexingEntity->getNextAction()->value,
        );
    }

    /**
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForExistingPages_NextActionDeleteChangedToUpdate(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $pageFactory = $this->objectManager->get(PageInterfaceFactory::class);
        /** @var Page&PageInterface $page */
        $page = $pageFactory->create();
        $page->setIdentifier('page-test-url');
        $page->setTitle('Page Test');
        $page->setIsActive(true);
        $page->setTitle('Page Test - Title');
        $page->setContentHeading('Page Test - Content Heading');
        $page->setContent('Page Test - Content');
        $page->setStoreId($store->getId());

        $pageResourceModel = $this->objectManager->get(PageResourceModel::class);
        $pageResourceModel->save($page);
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $page->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'cms_page',
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $page->setTitle('Page Test: New Title');
        $pageResourceModel->save($page);

        $cmsIndexingEntity = $this->getIndexingEntityForPage($apiKey, $page);
        $this->assertNotNull($cmsIndexingEntity);
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $cmsIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $cmsIndexingEntity->getNextAction()->value,
        );
    }

    /**
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_cms 1
     */
    public function testAroundSave_MakesIndexableEntityIndexableAgain(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $pageFactory = $this->objectManager->get(PageInterfaceFactory::class);
        /** @var Page&PageInterface $page */
        $page = $pageFactory->create();
        $page->setIdentifier('page-test-url');
        $page->setTitle('Page Test');
        $page->setIsActive(false);
        $page->setTitle('Page Test - Title');
        $page->setContentHeading('Page Test - Content Heading');
        $page->setContent('Page Test - Content');
        $page->setStoreId($store->getId());

        $pageResourceModel = $this->objectManager->get(PageResourceModel::class);
        $pageResourceModel->save($page);

        $cmsIndexingEntity = $this->getIndexingEntityForPage($apiKey, $page);
        $this->assertNotNull($cmsIndexingEntity);
        $this->assertFalse(condition: $cmsIndexingEntity->getIsIndexable());
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $cmsIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value
                . ', received ' . $cmsIndexingEntity->getNextAction()->value,
        );

        $page->setIsActive(true);
        $pageResourceModel->save($page);

        $cmsIndexingEntity = $this->getIndexingEntityForPage($apiKey, $page);
        $this->assertNotNull($cmsIndexingEntity);
        $this->assertTrue(condition: $cmsIndexingEntity->getIsIndexable());
        $this->assertSame(
            expected: Actions::ADD,
            actual: $cmsIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::ADD->value . ', received ' . $cmsIndexingEntity->getNextAction()->value,
        );
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(PageResourceModel::class, []);
    }

    /**
     * @return IndexingEntityInterface[]
     * @throws \Exception
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
     * @param string $apiKey
     * @param Page&PageInterface $page
     *
     * @return IndexingEntityInterface
     * @throws \Exception
     */
    private function getIndexingEntityForPage(string $apiKey, Page&PageInterface $page): IndexingEntityInterface
    {
        $cmsIndexingEntities = $this->getCmsIndexingEntities($apiKey);
        $cmsIndexingEntityArray = array_filter(
            array: $cmsIndexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$page->getId()
            )
        );

        return array_shift($cmsIndexingEntityArray);
    }
}
