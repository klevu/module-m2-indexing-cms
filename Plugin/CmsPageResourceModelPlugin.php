<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Plugin;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Page as PageResourceModel;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;

class CmsPageResourceModelPlugin
{
    /**
     * @var PageFactory
     */
    private readonly PageFactory $pageFactory;
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var string[]
     */
    private array $attributesToWatch;

    /**
     * @param PageFactory $pageFactory
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param StoreManagerInterface $storeManager
     * @param string[] $attributesToWatch
     */
    public function __construct(
        PageFactory $pageFactory,
        EntityUpdateResponderServiceInterface $responderService,
        StoreManagerInterface $storeManager,
        array $attributesToWatch = [],
    ) {
        $this->pageFactory = $pageFactory;
        $this->responderService = $responderService;
        array_walk($attributesToWatch, [$this, 'addAttributeToWatch']);
        $this->storeManager = $storeManager;
        $this->attributesToWatch = $attributesToWatch;
    }

    /**
     * Ideally we'd use an observer on "cms_page_save_after" rather than this plugin
     * however that event does not give us access to "is_active" in the original page data
     * meaning we can't see if the page has been enabled/disabled when using the observer.
     *
     * @param PageResourceModel $resourceModel
     * @param \Closure $proceed
     * @param AbstractModel $object
     *
     * @return PageResourceModel
     */
    public function aroundSave(
        PageResourceModel $resourceModel,
        \Closure $proceed,
        AbstractModel $object,
    ): PageResourceModel {
        /** @var AbstractModel&PageInterface $object */
        $originalPage = $this->getOriginalPage($resourceModel, $object);

        $return = $proceed($object);

        $storeIds = $this->getStoreIds($originalPage, $object);
        if (!empty($storeIds) || $this->isUpdateRequired($originalPage, $object)) {
            $data = [
                Entity::ENTITY_IDS => [(int)$object->getId()],
                Entity::STORE_IDS => $storeIds,
            ];
            $this->responderService->execute($data);
        }

        return $return;
    }

    /**
     * @param string $attributeToWatch
     *
     * @return void
     */
    private function addAttributeToWatch(string $attributeToWatch): void
    {
        $this->attributesToWatch[] = $attributeToWatch;
    }

    /**
     * @param PageResourceModel $resourceModel
     * @param PageInterface&AbstractModel $page
     *
     * @return PageInterface&AbstractModel
     */
    private function getOriginalPage(
        PageResourceModel $resourceModel,
        PageInterface&AbstractModel $page,
    ): PageInterface&AbstractModel {
        $originalPage = $this->pageFactory->create();
        if ($page->getId()) {
            $resourceModel->load($originalPage, $page->getId());
        }

        return $originalPage;
    }

    /**
     * @param PageInterface&AbstractModel $originalPage
     * @param PageInterface&AbstractModel $page
     *
     * @return bool
     */
    private function isUpdateRequired(
        PageInterface&AbstractModel $originalPage,
        PageInterface&AbstractModel $page,
    ): bool {
        if (!$originalPage->getId()) {
            // is a new page
            return true;
        }
        foreach ($this->attributesToWatch as $attribute) {
            if ($originalPage->getData($attribute) === $page->getData($attribute)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * If store assignment has changed we need the old and new stores to update
     *
     * @param PageInterface&AbstractModel $originalPage
     * @param PageInterface&AbstractModel $page
     *
     * @return int[]
     */
    private function getStoreIds(
        PageInterface&AbstractModel $originalPage,
        PageInterface&AbstractModel $page,
    ): array {
        $originalStoreIds = $this->processStoreIds($originalPage->getStoreId());
        $updatedStoreIds = $this->processStoreIds($page->getStoreId());
        $newStoreIds = array_diff($updatedStoreIds, $originalStoreIds);
        $removedStoreIds = array_diff($originalStoreIds, $newStoreIds);

        return array_filter(
            array_unique(
                array_merge(
                    $newStoreIds,
                    $removedStoreIds,
                ),
            ),
        );
    }

    /**
     * @param mixed $storeId
     *
     * @return int[]
     */
    private function processStoreIds(mixed $storeId): array
    {
        if (null === $storeId) {
            return [];
        }
        if (is_scalar($storeId)) {
            $storeId = [$storeId];
        }
        if (!is_array($storeId)) {
            return [];
        }
        $allStores = array_filter(
            array: array_map('intval', $storeId),
            callback: static fn (mixed $value) => $value === 0,
        );
        $return = count($allStores)
            ? array_keys($this->storeManager->getStores())
            : $storeId;

        return array_map('intval', $return);
    }
}
