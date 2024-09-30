<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Setup\Patch\Data;

use Klevu\IndexingCms\Constants;
use Klevu\IndexingCms\Setup\Patch\Data\MigrateLegacyConfigurationSettings;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\Writer as ConfigWriter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers MigrateLegacyConfigurationSettings
 * @method MigrateLegacyConfigurationSettings instantiateTestObject(?array $arguments = null)
 * @method MigrateLegacyConfigurationSettings instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MigrateLegacyConfigurationSettingsTest extends TestCase
{
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;
    /**
     * @var ScopeConfigInterface|null
     */
    private ?ScopeConfigInterface $scopeConfig = null;
    /**
     * @var ConfigResource|null
     */
    private ?ConfigResource $configResource = null;
    /**
     * @var ConfigWriter|null
     */
    private ?ConfigWriter $configWriter = null;

    /**
     * @return void
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();

        $this->implementationFqcn = MigrateLegacyConfigurationSettings::class;
        $this->interfaceFqcn = DataPatchInterface::class;

        $this->scopeConfig = $this->objectManager->get(ScopeConfigInterface::class);
        $this->configResource = $this->objectManager->get(ConfigResource::class);
        $this->configWriter = $this->objectManager->get(ConfigWriter::class);

        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);

        $this->createStore([
            'code' => 'klevu_analytics_test_store_1',
            'key' => 'test_store_1',
        ]);
        $this->createWebsite([
            'code' => 'klevu_analytics_test_website_1',
            'key' => 'test_website_1',
        ]);
        $testWebsite = $this->websiteFixturesPool->get('test_website_1');
        $this->createStore([
            'code' => 'klevu_analytics_test_store_2',
            'key' => 'test_store_2',
            'website_id' => $testWebsite->getId(),
        ]);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
    }

    public function testGetDependencies(): void
    {
        $dependencies = MigrateLegacyConfigurationSettings::getDependencies();

        $this->assertSame([], $dependencies);
    }

    public function testGetAliases(): void
    {
        $migrateLegacyConfigurationSettingsPatch = $this->instantiateTestObject();
        $aliases = $migrateLegacyConfigurationSettingsPatch->getAliases();

        $this->assertSame([], $aliases);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testApply_MigrateCmsSyncEnabled_EnabledGlobal(): void
    {
        $this->deleteExistingKlevuConfig();

        $testStore1 = $this->storeFixturesPool->get('test_store_1');
        $testStore2 = $this->storeFixturesPool->get('test_store_2');
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '1',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );
        $this->cleanConfig();

        $this->assertInitialConfig(enabled: true);

        $migrateLegacyConfigurationSettingsPatch = $this->instantiateTestObject();
        $migrateLegacyConfigurationSettingsPatch->apply();

        $this->cleanConfig();

        $this->assertTrue(
            condition: $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            ),
        );
        $this->assertTrue(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore1->getId(),
            ),
        );
        $this->assertTrue(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore2->getId(),
            ),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testApply_MigrateCmsSyncEnabled_DisabledGlobal(): void
    {
        $this->deleteExistingKlevuConfig();

        $testStore1 = $this->storeFixturesPool->get('test_store_1');
        $testStore2 = $this->storeFixturesPool->get('test_store_2');
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '0',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );
        $this->cleanConfig();

        $this->assertInitialConfig(enabled: false);

        $migrateLegacyConfigurationSettingsPatch = $this->instantiateTestObject();
        $migrateLegacyConfigurationSettingsPatch->apply();

        $this->cleanConfig();

        $this->assertFalse(
            condition: $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            ),
        );
        $this->assertFalse(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore1->getId(),
            ),
        );
        $this->assertFalse(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore2->getId(),
            ),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testApply_MigrateCmsSyncEnabled_EnabledWebsite(): void
    {
        $this->deleteExistingKlevuConfig();

        $testStore1 = $this->storeFixturesPool->get('test_store_1');
        $testStore2 = $this->storeFixturesPool->get('test_store_2');
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '0',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '1',
            scope: ScopeInterface::SCOPE_WEBSITES,
            scopeId: $testStore2->getWebsiteId(),
        );

        $migrateLegacyConfigurationSettingsPatch = $this->instantiateTestObject();
        $migrateLegacyConfigurationSettingsPatch->apply();

        $this->cleanConfig();

        $this->assertFalse(
            condition: $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            ),
        );
        $this->assertFalse(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore1->getId(),
            ),
        );
        $this->assertTrue(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore2->getId(),
            ),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testApply_MigrateCmsSyncEnabled_DisabledWebsite(): void
    {
        $this->deleteExistingKlevuConfig();

        $testStore1 = $this->storeFixturesPool->get('test_store_1');
        $testStore2 = $this->storeFixturesPool->get('test_store_2');
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '1',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '0',
            scope: ScopeInterface::SCOPE_WEBSITES,
            scopeId: $testStore2->getWebsiteId(),
        );

        $migrateLegacyConfigurationSettingsPatch = $this->instantiateTestObject();
        $migrateLegacyConfigurationSettingsPatch->apply();

        $this->cleanConfig();

        $this->assertTrue(
            condition: $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            ),
        );
        $this->assertTrue(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore1->getId(),
            ),
        );
        $this->assertFalse(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore2->getId(),
            ),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testApply_MigrateCmsSyncEnabled_EnabledStore(): void
    {
        $this->deleteExistingKlevuConfig();

        $testStore1 = $this->storeFixturesPool->get('test_store_1');
        $testStore2 = $this->storeFixturesPool->get('test_store_2');
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '0',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '1',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $testStore2->getId(),
        );

        $migrateLegacyConfigurationSettingsPatch = $this->instantiateTestObject();
        $migrateLegacyConfigurationSettingsPatch->apply();

        $this->cleanConfig();

        $this->assertFalse(
            condition: $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            ),
        );
        $this->assertFalse(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore1->getId(),
            ),
        );
        $this->assertTrue(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore2->getId(),
            ),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testApply_MigrateCmsSyncEnabled_DisabledStore(): void
    {
        $this->deleteExistingKlevuConfig();

        $testStore1 = $this->storeFixturesPool->get('test_store_1');
        $testStore2 = $this->storeFixturesPool->get('test_store_2');
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '1',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );
        $this->configWriter->save(
            path: MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            value: '0',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $testStore2->getId(),
        );

        $migrateLegacyConfigurationSettingsPatch = $this->instantiateTestObject();
        $migrateLegacyConfigurationSettingsPatch->apply();

        $this->cleanConfig();

        $this->assertTrue(
            condition: $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            ),
        );
        $this->assertTrue(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore1->getId(),
            ),
        );
        $this->assertFalse(
            $this->scopeConfig->isSetFlag(
                Constants::XML_PATH_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $testStore2->getId(),
            ),
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    private function deleteExistingKlevuConfig(): void
    {
        $connection = $this->configResource->getConnection();
        $connection->delete(
            $this->configResource->getMainTable(),
            [
                'path like "klevu%"',
            ],
        );

        $this->cleanConfig();
    }

    /**
     * @param bool $enabled
     *
     * @return void
     */
    private function assertInitialConfig(bool $enabled = true): void
    {
        $enabledGlobally = $this->scopeConfig->isSetFlag(
            MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        );
        $enabled
            ? $this->assertTrue(condition: $enabledGlobally)
            : $this->assertFalse(condition: $enabledGlobally);

        $testStore1 = $this->storeFixturesPool->get('test_store_1');
        $testStore2 = $this->storeFixturesPool->get('test_store_2');
        $storeIds = [
            $testStore1->getId(),
            $testStore2->getId(),
        ];

        foreach ($storeIds as $storeId) {
            $enabledForStore = $this->scopeConfig->isSetFlag(
                MigrateLegacyConfigurationSettings::XML_PATH_LEGACY_CMS_SYNC_ENABLED,
                ScopeInterface::SCOPE_STORES,
                $storeId,
            );
            $enabled
                ? $this->assertTrue(condition: $enabledForStore)
                : $this->assertFalse(condition: $enabledForStore);
        }
    }

    /**
     * @return void
     */
    private function cleanConfig(): void
    {
        if (method_exists($this->scopeConfig, 'clean')) {
            $this->scopeConfig->clean();
        }
    }
}
