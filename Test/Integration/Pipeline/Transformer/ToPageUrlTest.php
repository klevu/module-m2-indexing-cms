<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Pipeline\Transformer;

use Klevu\IndexingCms\Pipeline\Transformer\ToPageUrl;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Cms\Helper\Page;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\Store;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers ToPageUrl::class
 * @method TransformerInterface instantiateTestObject(?array $arguments = null)
 * @method TransformerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ToPageUrlTest extends TestCase
{
    use PageTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = ToPageUrl::class;
        $this->interfaceFqcn = TransformerInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

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
    }

    /**
     * @dataProvider testTransform_ThrowsException_WhenInvalidDataType_dataProvider
     */
    public function testTransform_ThrowsException_WhenInvalidDataType(mixed $invalidData): void
    {
        $this->expectException(InvalidInputDataException::class);
        $transformer = $this->instantiateTestObject();
        $transformer->transform(data: $invalidData);
    }

    /**
     * @return mixed[]
     */
    public function testTransform_ThrowsException_WhenInvalidDataType_dataProvider(): array
    {
        return [
            [null],
            ['string'],
            [1],
            [1.23],
            [true],
            [new DataObject()],
        ];
    }

    public function testTransform_ReturnsPageUrl(): void
    {
        ConfigFixture::setGlobal(
            path: Store::XML_PATH_UNSECURE_BASE_LINK_URL,
            value: 'http://magento.test/',
        );
        ConfigFixture::setGlobal(
            path: Store::XML_PATH_UNSECURE_BASE_URL,
            value: 'http://magento.test/',
        );
        ConfigFixture::setGlobal(
            path: Store::XML_PATH_SECURE_BASE_LINK_URL,
            value: 'https://magento.test/',
        );
        ConfigFixture::setGlobal(
            path: Store::XML_PATH_SECURE_BASE_URL,
            value: 'https://magento.test/',
        );

        $this->createPage([
            'identifier' => 'klevu-cms-page-test-url',
        ]);
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $pageHelper = $this->objectManager->get(Page::class);

        $transformer = $this->instantiateTestObject();
        $result = $transformer->transform(data: $pageFixture->getPage());

        $this->assertSame(
            expected: $pageHelper->getPageUrl($pageFixture->getId()),
            actual: $result,
        );
    }
}
