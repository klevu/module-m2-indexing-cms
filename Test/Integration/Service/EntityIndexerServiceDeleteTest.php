<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\EntityIndexerService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\IndexingApi\Service\EntityIndexerServiceInterface;
use Klevu\IndexingCategories\Constants;
use Klevu\IndexingCms\Service\EntityIndexerService\Delete as EntityIndexerServiceVirtualType;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelineArgumentsException;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\PipelineEntityApiCallTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

class EntityIndexerServiceDeleteTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PageTrait;
    use PipelineEntityApiCallTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
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

        $this->implementationFqcn = EntityIndexerServiceVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = EntityIndexerServiceInterface::class;
        $this->implementationForVirtualType = EntityIndexerService::class;
        $this->objectManager = Bootstrap::getObjectManager();

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
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ThrowsException_ForInvalidJsApiKey(): void
    {
        $apiKey = 'invalid-js-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'KlevuRestAuthKey123',
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            'Invalid arguments for pipeline "Klevu\PhpSDKPipelines\Pipeline\Stage\Indexing\SendBatchDeleteRequest". '
            . 'JS API Key argument (jsApiKey): Data is not valid',
        );

        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $service->execute(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ThrowsException_ForInvalidRestAuthKey(): void
    {
        $apiKey = 'klevu-123456789';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'invalid-auth-key',
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            'Invalid arguments for pipeline "Klevu\PhpSDKPipelines\Pipeline\Stage\Indexing\SendBatchDeleteRequest". '
            . 'REST AUTH Key argument (restAuthKey): Data is not valid',
        );

        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $service->execute(apiKey: $apiKey);
    }

    public function testExecute_ReturnsNoop_WhenNoCmsPageToDelete(): void
    {
        $apiKey = 'klevu-js-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::NOOP,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
    }

    public function testExecute_ReturnsNoop_WhenCmsSyncDisabled(): void
    {
        $apiKey = 'klevu-js-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        ConfigFixture::setForStore(
            path: Constants::XML_PATH_CATEGORY_SYNC_ENABLED,
            value: 0,
            storeCode: $storeFixture->getCode(),
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::NOOP,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenCmsPageDeleted(): void
    {
        $apiKey = 'klevu-123456789';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'SomeValidRestKey123',
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'cms_page',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
        ]);

        $repository = $this->objectManager->get(PageRepositoryInterface::class);
        $repository->delete($pageFixture->getPage());

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResultsArray = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResultsArray);
        $pipelineResults = array_shift($pipelineResultsArray);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);
        $this->assertContains(
            needle: 'pageid_' . $pageFixture->getId(),
            haystack: $payload,
        );
    }
}
