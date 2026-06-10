<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Plugin\Console\Command\EntitySyncInformationCommand;

use Klevu\Indexing\Console\Command\EntitySyncInformationCommand;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface;
use Magento\Cms\Api\Data\PageInterface;

class AddCmsSyncInformationPlugin
{
    /**
     * @param EntitySyncInformationCommand $subject
     * @param array $result
     * @param string $targetEntityType
     * @param EntitySyncConditionsValuesInterface $conditionsValuesData
     *
     * @return array<array<string, mixed>>
     */
    public function afterGetRealTimeSyncInformationData(
        EntitySyncInformationCommand $subject, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.Files.LineLength.TooLong
        array $result,
        string $targetEntityType,
        EntitySyncConditionsValuesInterface $conditionsValuesData,
    ): array {
        if ('KLEVU_CMS' !== $targetEntityType) {
            return $result;
        }

        $cmsPage = $conditionsValuesData->getTargetEntity();
        if (!($cmsPage instanceof PageInterface)) {
            return $result;
        }

        $store = $conditionsValuesData->getStore();
        $cmsPageStoreIds = (method_exists($cmsPage, 'getStores'))
            ? array_map('intval', $cmsPage->getStores())
            : null;
        $assignedToStore = (
            in_array(0, $cmsPageStoreIds, true)
            || in_array((int)$store->getId(), $cmsPageStoreIds, true)
        );

        $result[] = [
            'CMS Page assigned to store' => __($assignedToStore ? 'Yes' : 'No')->render(),
            'Is Active' => __($cmsPage->isActive() ? 'Yes' : 'No')->render(),
        ];

        return $result;
    }
}
