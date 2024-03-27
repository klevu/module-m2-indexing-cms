<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Service\Provider\Sync;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Provider\Sync\EntityIndexingRecordProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\EntityIndexingRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\Sync\EntityIndexingRecordProviderInterface;
use Klevu\IndexingCms\Service\Provider\Sync\EntityIndexingRecordProvider\Add as AddEntityIndexingRecordProviderVirtualType; //phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntityIndexingRecordProvider
 * @method EntityIndexingRecordProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexingRecordProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityIndexingRecordProviderTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PageTrait;
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

        $this->implementationFqcn = AddEntityIndexingRecordProviderVirtualType::class; //@phpstan-ignore-line
        $this->interfaceFqcn = EntityIndexingRecordProviderInterface::class;
        $this->implementationForVirtualType = EntityIndexingRecordProvider::class;
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
     */
    public function testGet_ReturnsEntitiesToAdd_ForCategory_InOneStore(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-test-auth-key',
        );

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $indexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $provider = $this->instantiateTestObject();
        $generator = $provider->get(apiKey: $apiKey);

        /** @var EntityIndexingRecordInterface[] $result */
        $result = [];
        foreach ($generator as $indexingRecord) {
            $result[] = $indexingRecord;
        }
        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertSame(
            expected: $indexingEntity->getId(),
            actual: $result[0]->getRecordId(),
        );
        $this->assertSame(
            expected: (int)$pageFixture->getId(),
            actual: (int)$result[0]->getEntity()->getId(),
        );
        $this->assertNull(actual: $result[0]->getParent());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }
}
