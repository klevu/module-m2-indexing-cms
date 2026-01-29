<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Service\Provider;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\Configuration\Service\Provider\StoresProviderInterface;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterfaceFactory;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Klevu\IndexingApi\Service\Provider\EntitySyncConditionsValuesProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;

class EntitySyncConditionsValuesProvider implements EntitySyncConditionsValuesProviderInterface
{
    /**
     * @var EntitySyncConditionsValuesInterfaceFactory
     */
    private readonly EntitySyncConditionsValuesInterfaceFactory $entitySyncConditionsValuesFactory;
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;
    /**
     * @var StoresProviderInterface
     */
    private readonly StoresProviderInterface $storesProvider;
    /**
     * @var IsIndexableDeterminerInterface
     */
    private readonly IsIndexableDeterminerInterface $isIndexableDeterminer;
    /**
     * @var PageRepositoryInterface
     */
    private readonly PageRepositoryInterface $pageRepository;

    /**
     * @param EntitySyncConditionsValuesInterfaceFactory $entitySyncConditionsValuesFactory
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param ApiKeysProviderInterface $apiKeysProvider
     * @param StoresProviderInterface $storesProvider
     * @param IsIndexableDeterminerInterface $isIndexableDeterminer
     * @param PageRepositoryInterface $pageRepository
     */
    public function __construct(
        EntitySyncConditionsValuesInterfaceFactory $entitySyncConditionsValuesFactory,
        IndexingEntityProviderInterface $indexingEntityProvider,
        ApiKeysProviderInterface $apiKeysProvider,
        StoresProviderInterface $storesProvider,
        IsIndexableDeterminerInterface $isIndexableDeterminer,
        PageRepositoryInterface $pageRepository,
    ) {
        $this->entitySyncConditionsValuesFactory = $entitySyncConditionsValuesFactory;
        $this->indexingEntityProvider = $indexingEntityProvider;
        $this->apiKeysProvider = $apiKeysProvider;
        $this->storesProvider = $storesProvider;
        $this->isIndexableDeterminer = $isIndexableDeterminer;
        $this->pageRepository = $pageRepository;
    }

    /**
     * @param string $targetEntityType
     * @param int $targetEntityId
     *
     * @return EntitySyncConditionsValuesInterface[]
     * @throws LocalizedException
     */
    public function get(
        string $targetEntityType,
        int $targetEntityId,
    ): array {
        if ('KLEVU_CMS' !== $targetEntityType) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'Provider of type %s cannot be used to retrieve information about entities of type %s',
                    self::class,
                    $targetEntityType,
                ),
            );
        }

        $return = [];

        $apiKeys = $this->apiKeysProvider->get(storeIds: []);
        $indexingEntities = $this->indexingEntityProvider->get(
            entityType: 'KLEVU_CMS',
            apiKeys: $apiKeys,
            entityIds: [$targetEntityId],
        );

        $cmsPage = $this->pageRepository->getById(
            pageId: $targetEntityId,
        );
        if (!method_exists($cmsPage, 'getStores')) {
            return $return;
        }

        $cmsPageStoreIds = array_map('intval', $cmsPage->getStores());
        $cmsPageInAllStores = in_array(0, $cmsPageStoreIds, true);

        foreach ($apiKeys as $apiKey) {
            $storesForApiKey = $this->storesProvider->get(
                apiKey: $apiKey,
            );

            foreach ($storesForApiKey as $store) {
//                if (
//                    !$cmsPageInAllStores
//                    && !in_array((int)$store->getId(), $cmsPageStoreIds, true)
//                ) {
//                    continue;
//                }

                $discoveredIndexingEntities = array_filter(
                    array: $indexingEntities,
                    callback: static fn (IndexingEntityInterface $indexingEntity) => (
                        $indexingEntity->getApiKey() === $apiKey
                        && $indexingEntity->getTargetId() === (int)$targetEntityId
                        && !$indexingEntity->getTargetParentId()
                    ),
                );

                $return[] = $this->createItem(
                    apiKey: $apiKey,
                    store: $store,
                    cmsPage: $cmsPage,
                    indexingEntity: $discoveredIndexingEntities
                        ? current($discoveredIndexingEntities)
                        : null,
                );
            }
        }

        return $return;
    }

    /**
     * @param string $apiKey
     * @param StoreInterface $store
     * @param PageInterface $cmsPage
     * @param IndexingEntityInterface|null $indexingEntity
     *
     * @return EntitySyncConditionsValuesInterface
     */
    private function createItem(
        string $apiKey,
        StoreInterface $store,
        PageInterface $cmsPage,
        ?IndexingEntityInterface $indexingEntity,
    ): EntitySyncConditionsValuesInterface {
        $item = $this->entitySyncConditionsValuesFactory->create();

        $item->setApiKey($apiKey);
        $item->setStore($store);
        $item->setTargetEntityType('KLEVU_CATEGORY');
        $item->setTargetEntity($cmsPage);
        if ($indexingEntity) {
            $item->setIndexingEntity($indexingEntity);
        }

        $item->setIsIndexable(
            isIndexable: $this->isIndexableDeterminer->execute(
                entity: $cmsPage,
                store: $store,
                entitySubtype: $indexingEntity?->getTargetEntitySubtype() ?? '',
            ),
        );
        $item->addSyncConditionsValue(
            key: 'is_active',
            value: $cmsPage->isActive(),
        );

        return $item;
    }
}
