<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Service;

use Klevu\Indexing\Exception\InvalidIndexingRecordDataTypeException;
use Klevu\IndexingApi\Service\EntityIndexingRecordCreatorServiceInterface;
use Klevu\IndexingCms\Service\EntityIndexingRecordCreatorService;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers EntityIndexingRecordCreatorService::class
 * @method EntityIndexingRecordCreatorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexingRecordCreatorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityIndexingRecordCreatorServiceTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
    use PageTrait;
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

        $this->implementationFqcn = EntityIndexingRecordCreatorService::class;
        $this->interfaceFqcn = EntityIndexingRecordCreatorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
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
        $this->categoryFixturePool->rollback();
    }

    public function testExecute_ThrowsException_WhenIncorrectEntityTypeProvided(): void
    {
        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $this->expectException(InvalidIndexingRecordDataTypeException::class);
        $this->expectExceptionMessage(
            sprintf(
                '"entity" provided to %s, must be instance of %s',
                EntityIndexingRecordCreatorService::class,
                PageInterface::class,
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute(
            recordId: 1,
            entity: $category,
        );
    }

    public function testExecute_ThrowsException_WhenIncorrectParentEntityTypeProvided(): void
    {
        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');
        $page = $pageFixture->getPage();

        $this->expectException(InvalidIndexingRecordDataTypeException::class);
        $this->expectExceptionMessage(
            sprintf(
                '"parent" provided to %s, must be instance of %s or null',
                EntityIndexingRecordCreatorService::class,
                PageInterface::class,
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute(
            recordId: 1,
            entity: $page,
            parent: $category,
        );
    }

    public function testExecute_ReturnsIndexingRecord_WithEntity(): void
    {
        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');
        $page = $pageFixture->getPage();

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            recordId: 1,
            entity: $page,
        );

        $this->assertSame(
            expected: (int)$page->getId(),
            actual: (int)$result->getEntity()->getId(),
        );
        $this->assertNull(actual: $result->getParent());
    }

    public function testExecute_ReturnsIndexingRecord_WithAllData(): void
    {
        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');
        $page = $pageFixture->getPage();

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            recordId: 1,
            entity: $page,
            parent: null,
        );

        $this->assertSame(
            expected: (int)$page->getId(),
            actual: (int)$result->getEntity()->getId(),
        );
        $this->assertNull(
            actual: $result->getParent(),
        );
    }
}
