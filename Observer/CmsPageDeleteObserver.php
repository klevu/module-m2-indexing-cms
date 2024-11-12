<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Observer;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\Page;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CmsPageDeleteObserver implements ObserverInterface
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger; // @phpstan-ignore-line

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
    ) {
        $this->responderService = $responderService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        /** @var Page $page */
        $page = $event->getData('entity');
        if (!($page instanceof PageInterface)) {
            return;
        }

        $this->responderService->execute([
            Entity::ENTITY_IDS => [(int)$page->getId()],
            Entity::STORE_IDS => $this->getStoreIds($page),
        ]);
    }

    /**
     * @param PageInterface $page
     *
     * @return int[]
     */
    private function getStoreIds(PageInterface $page): array
    {
        $return = $this->getStoreIdsFromPage($page);
        if (in_array(Store::DEFAULT_STORE_ID, $return, true)) {
            $return = array_map(
                callback: static fn (StoreInterface $store) => (int)$store->getId(),
                array: $this->storeManager->getStores(),
            );
        }

        return $return;
    }

    /**
     * @param PageInterface $page
     *
     * @return int[]
     */
    private function getStoreIdsFromPage(PageInterface $page): array
    {
        /** @var PageInterface&DataObject $page */
        $storeId = method_exists($page, 'getStoreId')
            ? $page->getStoreId()
            : $page->getData('store_id');
        if (!$storeId) {
            return [];
        }

        return is_array($storeId)
            ? array_map(
                callback: static fn (mixed $store): int => (int)$store,
                array: $storeId,
            )
            : [(int)$storeId];
    }
}
