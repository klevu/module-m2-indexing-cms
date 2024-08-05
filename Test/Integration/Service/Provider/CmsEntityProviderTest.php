<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingCms\Service\Provider\CmsEntityProvider;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\IndexingCms\Service\Provider\CmsEntityProvider::class
 * @method EntityProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CmsEntityProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use PageTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-lines

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = CmsEntityProvider::class;
        $this->interfaceFqcn = EntityProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->pageFixturesPool = $this->objectManager->get(PageFixturesPool::class);
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
    }

    public function testGet_ReturnsPageData(): void
    {
        $this->createWebsite([
            'key' => 'test_website',
        ]);
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'key' => 'test_store',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->createPage([
            'key' => 'test_page_1',
            'identifier' => 'test-page-1',
            'store_id' => $storeFixture->getId(),
        ]);
        $page1 = $this->pageFixturesPool->get('test_page_1');
        $this->createPage([
            'key' => 'test_page_2',
            'identifier' => 'test-page-2',
        ]);
        $page2 = $this->pageFixturesPool->get('test_page_2');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get();

        $pages = [];
        foreach ($searchResults as $searchResult) {
            $pages[] = $searchResult;
        }

        $filteredPages1 = array_filter($pages, static function (PageInterface $page) use ($page1) {
            return $page->getIdentifier() === $page1->getIdentifier();
        });
        $this->assertCount(1, $filteredPages1);

        $filteredPages2 = array_filter($pages, static function (PageInterface $page) use ($page2) {
            return $page->getIdentifier() === $page2->getIdentifier();
        });
        $this->assertCount(1, $filteredPages2);
    }

    public function testGet_ReturnsPageData_AtStoreScope(): void
    {
        $this->createWebsite([
            'key' => 'test_website',
        ]);
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture2->get());

        $this->createPage([
            'key' => 'test_page_1',
            'identifier' => 'test-page-1',
            'store_id' => $storeFixture1->getId(),
            'stores' => [
                $storeFixture1->getId(),
            ],
        ]);
        $page1 = $this->pageFixturesPool->get('test_page_1');
        $this->createPage([
            'key' => 'test_page_2',
            'identifier' => 'test-page-2',
            'store_id' => $storeFixture2->getId(),
            'stores' => [
                $storeFixture2->getId(),
            ],
        ]);
        $page2 = $this->pageFixturesPool->get('test_page_2');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get(store: $storeFixture1->get());
        $pages = [];
        foreach ($searchResults as $searchResult) {
            $pages[] = $searchResult;
        }

        $filteredPages1 = array_filter($pages, static function (PageInterface $page) use ($page1) {
            return $page->getIdentifier() === $page1->getIdentifier();
        });
        $this->assertCount(1, $filteredPages1);

        $filteredPages2 = array_filter($pages, static function (PageInterface $page) use ($page2) {
            return $page->getIdentifier() === $page2->getIdentifier();
        });
        $this->assertCount(0, $filteredPages2);
    }

    public function testGet_ReturnsPageData_ForFilteredEntities(): void
    {
        $this->createWebsite([
            'key' => 'test_website',
        ]);
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'key' => 'test_store',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->createPage([
            'key' => 'test_page_1',
            'identifier' => 'test-page-1',
            'store_id' => $storeFixture->getId(),
        ]);
        $page1 = $this->pageFixturesPool->get('test_page_1');
        $this->createPage([
            'key' => 'test_page_2',
            'identifier' => 'test-page-2',
        ]);
        $page2 = $this->pageFixturesPool->get('test_page_2');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get(
            entityIds: [(int)$page1->getId()],
        );

        $pages = [];
        foreach ($searchResults as $searchResult) {
            $pages[] = $searchResult;
        }

        $filteredPages1 = array_filter($pages, static function (PageInterface $page) use ($page1) {
            return $page->getIdentifier() === $page1->getIdentifier();
        });
        $this->assertCount(1, $filteredPages1);

        $filteredPages2 = array_filter($pages, static function (PageInterface $page) use ($page2) {
            return $page->getIdentifier() === $page2->getIdentifier();
        });
        $this->assertCount(0, $filteredPages2);
    }

    public function testGet_ReturnsPageData_AtStoreScopeAndForFilteredEntities(): void
    {
        $this->createWebsite([
            'key' => 'test_website',
        ]);
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture1->get());

        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');

        $this->createPage([
            'key' => 'test_page_1',
            'identifier' => 'test-page-1',
            'store_id' => $storeFixture1->getId(),
            'stores' => [
                $storeFixture1->getId(),
            ],
        ]);
        $page1 = $this->pageFixturesPool->get('test_page_1');
        $this->createPage([
            'key' => 'test_page_2',
            'identifier' => 'test-page-2',
            'store_id' => $storeFixture2->getId(),
            'stores' => [
                $storeFixture2->getId(),
            ],
        ]);
        $page2 = $this->pageFixturesPool->get('test_page_2');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get(
            store: $storeFixture1->get(),
            entityIds: [(int)$page2->getId()],
        );

        $pages = [];
        foreach ($searchResults as $searchResult) {
            $pages[] = $searchResult;
        }

        $filteredPages1 = array_filter($pages, static function (PageInterface $page) use ($page1) {
            return $page->getIdentifier() === $page1->getIdentifier();
        });
        $this->assertCount(0, $filteredPages1);

        $filteredPages2 = array_filter($pages, static function (PageInterface $page) use ($page2) {
            return $page->getIdentifier() === $page2->getIdentifier();
        });
        $this->assertCount(0, $filteredPages2);
    }

    public function testGet_ThrowsLogicException_WhenMetadataPoolThrowsException(): void
    {
        $this->markTestSkipped('Test does not enter get method, no idea why');
        $this->createStore(); // @phpstan-ignore-line Remove if test no longer marked skipped
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        ConfigFixture::setForStore(
            path: 'klevu/indexing/enable_cms_sync',
            value: 1,
            storeCode: $storeFixture->getCode(),
        );

        $exceptionMessage = 'Unknown entity type: UNKNOWN_TYPE requested';
        $mockMetadataPool = $this->getMockBuilder(MetadataPool::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockMetadataPool->expects($this->once())
            ->method('getMetadata')
            ->willThrowException(
                new \Exception($exceptionMessage),
            );
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('CMS page link field could not be retrieved.');

        $provider = $this->instantiateTestObject([
            'metadataPool' => $mockMetadataPool,
        ]);
        $provider->get(store: $storeFixture->get());
    }

    public function testGet_ReturnsNoData_WhenSyncDisabled_AtStoreScope(): void
    {
        $this->createWebsite([
            'key' => 'test_website',
        ]);
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');

        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture1->get());

        ConfigFixture::setForStore(
            path: 'klevu/indexing/enable_cms_sync',
            value: 0,
            storeCode: $storeFixture1->getCode(),
        );

        $this->createPage([
            'key' => 'test_page_1',
            'identifier' => 'test-page-1',
            'store_id' => $storeFixture1->getId(),
            'stores' => [
                $storeFixture1->getId(),
            ],
        ]);
        $this->createPage([
            'key' => 'test_page_2',
            'identifier' => 'test-page-2',
            'store_id' => $storeFixture2->getId(),
            'stores' => [
                $storeFixture2->getId(),
            ],
        ]);

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get(store: $storeFixture1->get());
        $pages = [];
        foreach ($searchResults as $searchResult) {
            $pages[] = $searchResult;
        }

        $this->assertCount(expectedCount: 0, haystack: $pages);
    }
}
