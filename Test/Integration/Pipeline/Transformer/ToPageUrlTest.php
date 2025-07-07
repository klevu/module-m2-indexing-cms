<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Pipeline\Transformer;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingCms\Pipeline\Transformer\ToPageUrl;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\Argument;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
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
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

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

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->websiteFixturesPool = $this->objectManager->create(WebsiteFixturesPool::class);
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

    /**
     * @magentoAppIsolation enabled
     */
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

        $transformer = $this->instantiateTestObject();
        $argument0 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => 'https://magento.test/',
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [
                    $argument0,
                ],
            ],
        );
        $result = $transformer->transform(
            data: $pageFixture->getPage(),
            arguments: $argumentIterator,
        );

        $this->assertSame(
            expected: 'https://magento.test/' . $pageFixture->getIdentifier(),
            actual: $result,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testTransform_ReturnsPageUrlForMultipleStores_WhenDifferentDomains(): void
    {
        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');
        $keyForFirstStore = 'test_store_1';
        $keyForSecondStore = 'test_store_2';

        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'code' => 'klevu_' . $keyForFirstStore,
            'key' => $keyForFirstStore,
        ]);
        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'code' => 'klevu_' . $keyForSecondStore,
            'key' => $keyForSecondStore,
        ]);
        $store1 = $this->storeFixturesPool->get($keyForFirstStore);
        $store2 = $this->storeFixturesPool->get($keyForSecondStore);
        $stores = [$store1, $store2];

        foreach ($stores as $store) {
            $storeCode = $store->getCode();
            $storeUrl = 'http://mage-' . $storeCode . '.loc/';
            ConfigFixture::setForStore(
                path: Store::XML_PATH_UNSECURE_BASE_LINK_URL,
                value: $storeUrl,
                storeCode: $storeCode,
            );
            ConfigFixture::setForStore(
                path: Store::XML_PATH_UNSECURE_BASE_URL,
                value: $storeUrl,
                storeCode: $storeCode,
            );
            ConfigFixture::setForStore(
                path: Store::XML_PATH_SECURE_BASE_LINK_URL,
                value: $storeUrl,
                storeCode: $storeCode,
            );
            ConfigFixture::setForStore(
                path: Store::XML_PATH_SECURE_BASE_URL,
                value: $storeUrl,
                storeCode: $storeCode,
            );

            $key = 'klevu-cms-page-test-url';
            $this->createPage([
                'key' => $key,
                'identifier' => $key,
                'stores' => [$store->getId()],
                'store_id' => $store->getId(),
            ]);

            $pageFixture = $this->pageFixturesPool->get($key);
            $storeManager = $this->objectManager->get(StoreManagerInterface::class);
            $storeManager->setCurrentStore($store->getId());

            $transformer = $this->instantiateTestObject();

            $argument0 = $this->objectManager->create(
                type: Argument::class,
                arguments: [
                    'value' => $storeManager->getStore()->getBaseUrl(
                        type: UrlInterface::URL_TYPE_LINK,
                    ),
                    'key' => 0,
                ],
            );
            $argumentIterator = $this->objectManager->create(
                type: ArgumentIterator::class,
                arguments: [
                    'data' => [
                        $argument0,
                    ],
                ],
            );
            $pageObject = $pageFixture->getPage();
            $expectedValue = 'http://mage-' . $storeCode . '.loc/index.php/' . $key;

            $result = $transformer->transform(
                data: $pageObject,
                arguments: $argumentIterator,
            );

            $this->assertSame(
                expected: $expectedValue,
                actual: $result,
            );
        }
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testTransform_ReturnsPageUrlWithStoreCode_WhenAddStoreCodeToUrlsEnabled(): void
    {
        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $storeKey = 'french';
        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'code' => $storeKey,
            'key' => $storeKey,
        ]);
        $storeFixture = $this->storeFixturesPool->get($storeKey);
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $storeManager->setCurrentStore($storeFixture->get());
        $storeId = $storeFixture->getId();
        $storeCode = $storeFixture->getCode();

        $storeModel = $storeManager->getStore($storeId);
        $storeModel->setCode($storeKey);
        $storeUrl = 'http://magento-' . $storeCode . '.loc/';

        ConfigFixture::setGlobal(
            path: Store::XML_PATH_STORE_IN_URL,
            value: 1,
        );

        ConfigFixture::setForStore(
            path: Store::XML_PATH_USE_REWRITES,
            value: 1,
            storeCode: $storeCode,
        );
        ConfigFixture::setForStore(
            path: Store::XML_PATH_UNSECURE_BASE_LINK_URL,
            value: $storeUrl,
            storeCode: $storeCode,
        );
        ConfigFixture::setForStore(
            path: Store::XML_PATH_UNSECURE_BASE_URL,
            value: $storeUrl,
            storeCode: $storeCode,
        );
        ConfigFixture::setForStore(
            path: Store::XML_PATH_SECURE_BASE_LINK_URL,
            value: $storeUrl,
            storeCode: $storeCode,
        );
        ConfigFixture::setForStore(
            path: Store::XML_PATH_SECURE_BASE_URL,
            value: $storeUrl,
            storeCode: $storeCode,
        );

        $pageKey = 'klevu-cms-page-test';
        $this->createPage([
            'key' => $pageKey,
            'identifier' => $pageKey,
            'stores' => [$storeId],
            'store_id' => $storeId,
        ]);
        $pageFixture = $this->pageFixturesPool->get($pageKey);
        $transformer = $this->instantiateTestObject();

        $argument0 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $storeModel->getBaseUrl(
                    type: UrlInterface::URL_TYPE_LINK,
                ),
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument0],
            ],
        );

        $pageObject = $pageFixture->getPage();
        $expectedValue = $storeUrl . $storeKey . '/' . $pageKey;

        $result = $transformer->transform(
            data: $pageObject,
            arguments: $argumentIterator,
        );

        $this->assertSame(
            expected: $expectedValue,
            actual: $result,
        );
    }
}
