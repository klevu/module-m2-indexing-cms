<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Plugin\Ui\Component\Listing\Column;

use Klevu\IndexingCms\Plugin\Ui\Component\Listing\Column\PageActionsPlugin;
use Klevu\IndexingProducts\Plugin\Catalog\Ui\Component\Listing\Columns\ProductActionsPlugin;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Cms\Ui\Component\Listing\Column\PageActions;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers ProductActionsPlugin
 * @method ProductActionsPlugin instantiateTestObject(?array $arguments = null)
 * @method ProductActionsPlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class PageActionsPluginTest extends TestCase
{
    use ObjectInstantiationTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingCms::UiComponentListingColumnsAddHistoryToActions';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = PageActionsPlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @magentoAppArea global
     */
    public function testPlugin_DoesNotInterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayNotHasKey($this->pluginName, $pluginInfo);
    }

    /**
     * @magentoAppArea adminhtml
     */
    public function testPlugin_InterceptsCallsToTheField_InAdminScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(PageActionsPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(PageActions::class, []);
    }
}
