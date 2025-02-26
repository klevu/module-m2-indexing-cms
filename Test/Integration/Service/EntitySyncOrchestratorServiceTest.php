<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Exception\InvalidEntityIndexerServiceException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\EntitySyncOrchestratorService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\PipelineEntityApiCallTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers EntitySyncOrchestratorService
 * @method EntitySyncOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntitySyncOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntitySyncOrchestratorServiceTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PageTrait;
    use PipelineEntityApiCallTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

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

        $this->implementationFqcn = EntitySyncOrchestratorService::class;
        $this->interfaceFqcn = EntitySyncOrchestratorServiceInterface::class;
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

    public function testConstruct_ThrowsException_ForInvalidAttributeIndexerService(): void
    {
        $this->expectException(InvalidEntityIndexerServiceException::class);

        $this->instantiateTestObject([
            'entityIndexerServices' => [
                'KLEVU_CMS' => [
                    'add' => new DataObject(),
                ],
            ],
        ]);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_LogsError_ForInvalidAccountCredentials(): void
    {
        $apiKey = 'invalid-js-api-key';
        $authKey = 'invalid-rest-auth-key';

        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Method: {method}, Warning: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\EntitySyncOrchestratorService::getCredentialsArray',
                    'message' => 'No Account found for provided API Keys. '
                        . 'Check the JS API Keys (incorrect-key) provided.',
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'entityIndexerServices' => [],
        ]);
        $results = $service->execute(apiKeys: ['incorrect-key']);
        $results->current();

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_SyncsNewEntity(): void
    {
        $apiKey = 'klevu-123456789';
        $authKey = 'SomeValidRestKey123';

        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'cms_page',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: true);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $results = $service->execute(
            entityTypes: ['KLEVU_CMS'],
            apiKeys: [$apiKey],
            via: 'CLI::klevu:indexing:entity-sync',
        );
        $this->assertSame(expected: $apiKey . '~~KLEVU_CMS::add', actual: $results->key());
        $result = $results->current();

        $addPipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $addPipelineResults);
        $pipelineResults = array_shift($addPipelineResults);
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

        $updatedIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $pageFixture->getPage(),
            type: 'KLEVU_CMS',
        );
        $this->assertSame(expected: Actions::ADD, actual: $updatedIndexingEntity->getLastAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $updatedIndexingEntity->getNextAction());
        $this->assertTrue(condition: $updatedIndexingEntity->getIsIndexable());
        $this->assertNotNull(actual: $updatedIndexingEntity->getLastActionTimestamp());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_SyncsEntityUpdate(): void
    {
        $apiKey = 'klevu-123456789';
        $authKey = 'SomeValidRestKey123';

        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'cms_page',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: true);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $results = $service->execute(
            entityTypes: ['KLEVU_CMS'],
            apiKeys: [$apiKey],
            via: 'CLI::klevu:indexing:entity-sync',
        );

        $this->assertSame(expected: $apiKey . '~~KLEVU_CMS::update', actual: $results->key());
        $result = $results->current();

        $addPipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $addPipelineResults);
        $pipelineResults = array_shift($addPipelineResults);
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

        $updatedIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $pageFixture->getPage(),
            type: 'KLEVU_CMS',
        );
        $this->assertSame(expected: Actions::UPDATE, actual: $updatedIndexingEntity->getLastAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $updatedIndexingEntity->getNextAction());
        $this->assertTrue(condition: $updatedIndexingEntity->getIsIndexable());
        $this->assertNotNull(actual: $updatedIndexingEntity->getLastActionTimestamp());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_DeletesEntity(): void
    {
        $apiKey = 'klevu-123456789';
        $authKey = 'SomeValidRestKey123';

        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'cms_page',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $service = $this->instantiateTestObject();
        $results = $service->execute(
            entityTypes: ['KLEVU_CMS'],
            apiKeys: [$apiKey],
            via: 'CLI::klevu:indexing:entity-sync',
        );

        $this->assertSame(expected: $apiKey . '~~KLEVU_CMS::delete', actual: $results->key());
        $result = $results->current();

        $addPipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $addPipelineResults);
        $pipelineResults = array_shift($addPipelineResults);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertContains(
            needle: 'pageid_' . $pageFixture->getId(),
            haystack: $payload,
        );

        $updatedIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $pageFixture->getPage(),
            type: 'KLEVU_CMS',
        );
        $this->assertSame(expected: Actions::DELETE, actual: $updatedIndexingEntity->getLastAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $updatedIndexingEntity->getNextAction());
        $this->assertFalse(condition: $updatedIndexingEntity->getIsIndexable());
        $this->assertNotNull(actual: $updatedIndexingEntity->getLastActionTimestamp());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }
}
