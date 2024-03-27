<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Configuration\Service\Provider\Sdk\BaseUrlsProvider;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\EntityIndexerService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\IndexingApi\Service\EntityIndexerServiceInterface;
use Klevu\IndexingCms\Service\EntityIndexerService\Add as EntityIndexerServiceVirtualType;
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
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

class EntityIndexerServiceAddTest extends TestCase
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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            'Invalid arguments for pipeline "Klevu\PhpSDKPipelines\Pipeline\Stage\Indexing\SendBatchRequest". '
            . 'JS API Key argument (jsApiKey): Data is not valid',
        );

        $this->mockBatchServicePutApiCall(isCalled: false);

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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            'Invalid arguments for pipeline "Klevu\PhpSDKPipelines\Pipeline\Stage\Indexing\SendBatchRequest". '
            . 'REST AUTH Key argument (restAuthKey): Data is not valid',
        );

        $this->mockBatchServicePutApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $service->execute(apiKey: $apiKey);
    }

    public function testExecute_ReturnsNoop_WhenNoCmsPagesToAdd(): void
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

        $this->mockBatchServicePutApiCall(isCalled: false);

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
            path: 'klevu/indexing/enable_category_sync',
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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
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
    public function testExecute_ReturnsSuccess_WhenCmsPageAdded(): void
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
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: true);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);
        $record = $payload->current();

        $this->assertSame(
            expected: 'pageid_' . $pageFixture->getId(),
            actual: $record->getId(),
            message: 'Record ID: ' . $record->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_CMS',
            actual: $record->getType(),
            message: 'Record Type: ' . $record->getType(),
        );

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Klevu Test Page',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $pattern = '#Heading - Klevu Test Page\s*Content - Klevu Test Page#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $attributes['description']['default'],
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'Description[default]');

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertNotContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/klevu-test-page', haystack: $attributes['url']);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu/indexing/image_width_product 800
     * @magentoConfigFixture klevu_test_store_1_store klevu/indexing/image_height_product 800
     */
    public function testExecute_ForRealApiKeys(): void
    {
        /**
         * This test requires your Klevu API keys
         * These API keys can be set in dev/tests/integration/phpunit.xml
         * <phpunit>
         *     <testsuites>
         *      ...
         *     </testsuites>
         *     <php>
         *         ...
         *         <env name="KLEVU_JS_API_KEY" value="" force="true" />
         *         <env name="KLEVU_REST_API_KEY" value="" force="true" />
         *         <env name="KLEVU_API_REST_URL" value="api.ksearchnet.com" force="true" />
         *         // KLEVU_TIERS_URL only required for none production env
         *         <env name="KLEVU_TIERS_URL" value="tiers.klevu.com" force="true" />
         *     </php>
         */
        $restApiKey = getenv('KLEVU_REST_API_KEY');
        $jsApiKey = getenv('KLEVU_JS_API_KEY');
        $restApiUrl = getenv('KLEVU_REST_API_URL');
        $tiersApiUrl = getenv('KLEVU_TIERS_URL');
        $indexingUrl = getenv('KLEVU_INDEXING_URL');
        if (!$restApiKey || !$jsApiKey || !$restApiUrl || !$tiersApiUrl || !$indexingUrl) {
            $this->markTestSkipped('Klevu API keys are not set in `dev/tests/integration/phpunit.xml`. Test Skipped');
        }

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: $restApiKey,
        );
        $scopeProvider->unsetCurrentScope();

        ConfigFixture::setGlobal(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_INDEXING,
            value: $indexingUrl,
        );
        ConfigFixture::setGlobal(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_API,
            value: $restApiUrl,
        );
        ConfigFixture::setGlobal(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_TIERS,
            value: $tiersApiUrl,
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities(apiKey: $jsApiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $jsApiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $jsApiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);
        $record = $payload->current();

        $this->assertSame(
            expected: 'pageid_' . $pageFixture->getId(),
            actual: $record->getId(),
            message: 'Record ID: ' . $record->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_CMS',
            actual: $record->getType(),
            message: 'Record Type: ' . $record->getType(),
        );

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Klevu Test Page',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $pattern = '#Heading - Klevu Test Page\s*Content - Klevu Test Page#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $attributes['description']['default'],
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'Description[default]');

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertNotContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/klevu-test-page', haystack: $attributes['url']);
    }
}
