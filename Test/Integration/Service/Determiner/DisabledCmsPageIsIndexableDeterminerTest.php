<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Service\Determiner;

use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Klevu\IndexingCms\Service\Determiner\DisabledPagesIsIndexableDeterminer;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Model\Category;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\IndexingCms\Service\Determiner\DisabledPagesIsIndexableDeterminer::class
 * @method IsIndexableDeterminerInterface instantiateTestObject(?array $arguments = null)
 * @method IsIndexableDeterminerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DisabledCmsPageIsIndexableDeterminerTest extends TestCase
{
    use ObjectInstantiationTrait;
    use PageTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = DisabledPagesIsIndexableDeterminer::class;
        $this->interfaceFqcn = IsIndexableDeterminerInterface::class;
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
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_cms 0
     */
    public function testExecute_ReturnsTrue_WhenConfigDisabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createPage([
            'is_active' => false,
        ]);
        $page = $this->pageFixturesPool->get('test_page');

        $determiner = $this->instantiateTestObject();
        $this->assertTrue(
            condition: $determiner->execute(
                entity: $page->getPage(),
                store: $storeFixture->get(),
            ),
            message: 'is indexable',
        );
    }

    /**
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_cms 1
     */
    public function testExecute_ReturnsTrue_WhenConfigEnabled_EntityEnabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createPage([
            'is_active' => true,
        ]);
        $page = $this->pageFixturesPool->get('test_page');

        $determiner = $this->instantiateTestObject();
        $this->assertTrue(
            condition: $determiner->execute(
                entity: $page->getPage(),
                store: $storeFixture->get(),
            ),
            message: 'is indexable',
        );
    }

    /**
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_cms 1
     */
    public function testExecute_ReturnsTrue_WhenConfigEnabled_EntityDisabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createPage([
            'is_active' => false,
        ]);
        $page = $this->pageFixturesPool->get('test_page');

        $determiner = $this->instantiateTestObject();
        $this->assertFalse(
            condition: $determiner->execute(
                entity: $page->getPage(),
                store: $storeFixture->get(),
            ),
            message: 'is indexable',
        );
    }

    public function testExecute_ThrowsInvalidArgumentException(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $invalidEntity = $this->objectManager->create(Category::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid argument provided for "$entity". Expected %s, received %s.',
                PageInterface::class,
                get_debug_type($invalidEntity),
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute($invalidEntity, $storeFixture->get());
    }
}
