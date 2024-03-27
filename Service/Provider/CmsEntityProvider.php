<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeConfigProviderInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\ResourceModel\Page\Collection as PageCollection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class CmsEntityProvider implements EntityProviderInterface
{
    /**
     * @var PageCollectionFactory
     */
    private readonly PageCollectionFactory $pageCollectionFactory;
    /**
     * @var MetadataPool
     */
    private readonly MetadataPool $metadataPool;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ScopeConfigProviderInterface
     */
    private readonly ScopeConfigProviderInterface $syncEnabledProvider;

    /**
     * @param PageCollectionFactory $pageCollectionFactory
     * @param MetadataPool $metadataPool
     * @param LoggerInterface $logger
     * @param ScopeConfigProviderInterface $syncEnabledProvider
     */
    public function __construct(
        PageCollectionFactory $pageCollectionFactory,
        MetadataPool $metadataPool,
        LoggerInterface $logger,
        ScopeConfigProviderInterface $syncEnabledProvider,
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->metadataPool = $metadataPool;
        $this->logger = $logger;
        $this->syncEnabledProvider = $syncEnabledProvider;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     *
     * @return \Generator|null
     * @throws \LogicException
     */
    public function get(?StoreInterface $store = null, ?array $entityIds = []): ?\Generator
    {
        if (!$this->syncEnabledProvider->get()) {
            return null;
        }
        $linkField = $this->getLinkField();
        /** @var PageCollection $pageCollection */
        $pageCollection = $this->pageCollectionFactory->create();
        $pageCollection->addFieldToSelect(field: '*');
        if ($store) {
            $connection = $pageCollection->getConnection();
            $select = $pageCollection->getSelect();
            $select->joinInner(
                name: ['store' => $pageCollection->getTable('cms_page_store')],
                cond: implode(
                    ' ' . Select::SQL_AND . ' ',
                    [
                        sprintf(
                            'main_table.page_id = store.%s',
                            $connection->quoteIdentifier($linkField),
                        ),
                        $connection->quoteInto('store.store_id IN (0,?)', (int)$store->getId()),
                    ],
                ),
                cols: ['store.store_id'],
            );
        }
        $filteredEntityIds = array_filter($entityIds);
        if ($filteredEntityIds) {
            $pageCollection->addFieldToFilter(
                field: PageInterface::PAGE_ID,
                condition: ['in' => implode(',', $filteredEntityIds)],
            );
        }
        $this->logQuery($pageCollection);

        foreach ($pageCollection as $page) {
            yield $page;
        }
    }

    /**
     * @return string
     * @throws \LogicException
     */
    private function getLinkField(): string
    {
        try {
            $cmsPageMetaData = $this->metadataPool->getMetadata(PageInterface::class);
            $return = $cmsPageMetaData->getLinkField();
        } catch (\Exception $exception) {
            throw new \LogicException(
                message: 'CMS page link field could not be retrieved.',
                previous: $exception,
            );
        }

        return $return;
    }

    /**
     * @param PageCollection $pageCollection
     *
     * @return void
     */
    private function logQuery(PageCollection $pageCollection): void
    {
        $this->logger->debug(
            message: 'Method: {method}, Debug: {message}',
            context: [
                'method' => __METHOD__,
                'message' =>
                    sprintf(
                        'CMS Entity Provider Query: %s',
                        $pageCollection->getSelect()->__toString(),
                    ),
            ],
        );
    }
}
