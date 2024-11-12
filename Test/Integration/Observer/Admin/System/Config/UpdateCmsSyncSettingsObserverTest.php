<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Observer\Admin\System\Config;

use Klevu\IndexingApi\Service\Action\CreateCronScheduleActionInterface;
use Klevu\IndexingCms\Constants;
use Klevu\IndexingCms\Observer\Admin\System\Config\UpdateCmsSyncSettingsObserver;
use Klevu\IndexingCms\Service\Determiner\DisabledPagesIsIndexableCondition;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers UpdateCmsSyncSettingsObserver
 * @method UpdateCmsSyncSettingsObserver instantiateTestObject(?array $arguments = null)
 * @method UpdateCmsSyncSettingsObserver instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea adminhtml
 */
class UpdateCmsSyncSettingsObserverTest extends TestCase
{
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_IndexingCms_adminSystemConfig_updateCmsSyncSettings';
    private const EVENT_NAME = 'admin_system_config_changed_section_klevu_developer';

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

        $this->implementationFqcn = UpdateCmsSyncSettingsObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppArea global
     */
    public function testObserver_IsNotConfigured_InGlobalScope(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayNotHasKey(key: self::OBSERVER_NAME, array: $observers);
    }

    public function testObserver_IsConfiguredInAdminScope(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: UpdateCmsSyncSettingsObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    public function testExecute_NoPathsChanged(): void
    {
        $mockCreateCronScheduleAction = $this->getMockCreateCronScheduleAction();
        $mockCreateCronScheduleAction->expects($this->never())
            ->method('execute');

        /** @var Observer $observer */
        $observer = $this->objectManager->create(Observer::class, [
            'data' => [
                'changed_paths' => [],
            ],
        ]);

        $updateOrderSyncCron = $this->instantiateTestObject([
            'createCronScheduleAction' => $mockCreateCronScheduleAction,
        ]);

        $updateOrderSyncCron->execute($observer);
    }

    public function testExecute_UnrelatedPathsChanged(): void
    {
        $mockCreateCronScheduleAction = $this->getMockCreateCronScheduleAction();
        $mockCreateCronScheduleAction->expects($this->never())
            ->method('execute');

        /** @var Observer $observer */
        $observer = $this->objectManager->create(Observer::class, [
            'data' => [
                'changed_paths' => [
                    'some/other/path',
                ],
            ],
        ]);

        $updateOrderSyncCron = $this->instantiateTestObject([
            'createCronScheduleAction' => $mockCreateCronScheduleAction,
        ]);

        $updateOrderSyncCron->execute($observer);
    }

    public function testExecute_ExcludeDisabledCmsPagesPathChanged(): void
    {
        $mockCreateCronScheduleAction = $this->getMockCreateCronScheduleAction();
        $mockCreateCronScheduleAction->expects($this->once())
            ->method('execute');

        /** @var Observer $observer */
        $observer = $this->objectManager->create(Observer::class, [
            'data' => [
                'changed_paths' => [
                    Constants::XML_PATH_CMS_SYNC_ENABLED,
                    DisabledPagesIsIndexableCondition::XML_PATH_EXCLUDE_DISABLED_CMS,
                ],
            ],
        ]);

        $updateOrderSyncCron = $this->instantiateTestObject([
            'createCronScheduleAction' => $mockCreateCronScheduleAction,
        ]);

        $updateOrderSyncCron->execute($observer);
    }

    /**
     * @return MockObject&CreateCronScheduleActionInterface
     */
    private function getMockCreateCronScheduleAction(): CreateCronScheduleActionInterface&MockObject
    {
        return $this->getMockBuilder(CreateCronScheduleActionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

}
