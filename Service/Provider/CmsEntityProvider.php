<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeConfigProviderInterface;
use Klevu\Indexing\Validator\BatchSizeValidator;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\IndexingCms\Model\ResourceModel\Page\Collection as PageCollection;
use Klevu\IndexingCms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class CmsEntityProvider implements EntityProviderInterface
{
    public const ENTITY_SUBTYPE_CMS_PAGE = 'cms_page';

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
     * @var string
     */
    private readonly string $entitySubtype;
    /**
     * @var int|null
     */
    private readonly ?int $batchSize;

    /**
     * @param PageCollectionFactory $pageCollectionFactory
     * @param MetadataPool $metadataPool
     * @param LoggerInterface $logger
     * @param ScopeConfigProviderInterface $syncEnabledProvider
     * @param string $entitySubtype
     * @param int|null $batchSize
     * @param ValidatorInterface|null $batchSizeValidator
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        PageCollectionFactory $pageCollectionFactory,
        MetadataPool $metadataPool,
        LoggerInterface $logger,
        ScopeConfigProviderInterface $syncEnabledProvider,
        string $entitySubtype = self::ENTITY_SUBTYPE_CMS_PAGE,
        ?int $batchSize = null,
        ?ValidatorInterface $batchSizeValidator = null,
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->metadataPool = $metadataPool;
        $this->logger = $logger;
        $this->syncEnabledProvider = $syncEnabledProvider;
        $this->entitySubtype = $entitySubtype;

        $objectManager = ObjectManager::getInstance();
        $batchSizeValidator = $batchSizeValidator ?: $objectManager->get(BatchSizeValidator::class);
        if (!$batchSizeValidator->isValid($batchSize)) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'Invalid Batch Size: %s',
                    implode(', ', $batchSizeValidator->getMessages()),
                ),
            );
        }
        $this->batchSize = $batchSize;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     *
     * @return \Generator<PageInterface[]>|null
     * @throws \LogicException
     */
    public function get(?StoreInterface $store = null, ?array $entityIds = []): ?\Generator
    {
        if (!$this->syncEnabledProvider->get()) {
            return null;
        }
        $currentEntityId = 0;
        while (true) {
            $pageCollection = $this->getPageCollection(
                store: $store,
                entityIds: $entityIds,
                pageSize: $this->batchSize,
                currentEntityId: $currentEntityId + 1,
            );
            if (!$pageCollection->getSize()) {
                break;
            }
            /** @var PageInterface[] $pages */
            $pages = $pageCollection->getItems();
            yield $pages;
            $lastPage = array_pop($pages);
            $currentEntityId = $lastPage->getId();
            if (null === $this->batchSize || $pageCollection->getSize() < $this->batchSize) {
                break;
            }
        }
    }

    /**
     * @return string
     */
    public function getEntitySubtype(): string
    {
        return $this->entitySubtype;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     * @param int|null $pageSize
     * @param int $currentEntityId
     *
     * @return PageCollection
     */
    private function getPageCollection(
        ?StoreInterface $store,
        ?array $entityIds,
        ?int $pageSize = null,
        int $currentEntityId = 1,
    ): PageCollection {
        // @TODO extract to own class
        $linkField = $this->getLinkField();
        /** @var PageCollection $pageCollection */
        $pageCollection = $this->pageCollectionFactory->create();
        $pageCollection->addFieldToSelect(field: '*');
        if (null !== $pageSize) {
            $pageCollection->setPageSize($pageSize);
            $pageCollection->addFieldToFilter(PageInterface::PAGE_ID, ['gteq' => $currentEntityId]);
        }
        $pageCollection->setOrder(PageInterface::PAGE_ID, Select::SQL_ASC);
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

        return $pageCollection;
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
