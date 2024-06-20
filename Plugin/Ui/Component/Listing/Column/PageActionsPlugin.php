<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Plugin\Ui\Component\Listing\Column;

use Magento\Cms\Ui\Component\Listing\Column\PageActions;

class PageActionsPlugin
{
    /**
     * @param PageActions $subject
     * @param mixed[] $result
     *
     * @return mixed[]
     */
    public function afterPrepareDataSource(
        PageActions $subject,
        array $result,
    ): array {
        if (isset($result['data']['items'])) {
            // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedAssigningByReference
            foreach ($result['data']['items'] as &$item) {
                $entityId = $item['page_id'] ?? null;
                if (!$entityId) {
                    continue;
                }
                $item[$subject->getData('name')]['klevu_sync_info'] = $this->createAction(
                    pageId: (int)$item['page_id'],
                );
            }
        }

        return $result;
    }

    /**
     * @param int $pageId
     *
     * @return mixed[][][][]
     */
    private function createAction(int $pageId): array
    {
        return [
            'href' => '#',
            'ariaLabel' => __('Klevu Sync Info'),
            'label' => __('Klevu Sync Info'),
            'hidden' => false,
            'callback' => $this->createCallback(pageId: $pageId),
        ];
    }

    /**
     * @param int $pageId
     *
     * @return mixed[][][]
     */
    private function createCallback(int $pageId): array
    {
        $cmsListing = 'cms_page_listing.cms_page_listing';
        $modal = $cmsListing . '.klevu_cms_sync_info_modal';
        $container = $modal . '.klevu_cms_sync_info_container';
        $actionFieldset = $container . '.klevu_cms_next_action_fieldset';
        $actionListing = $actionFieldset . '.klevu_cms_sync_next_action_listing';
        $historyFieldset = $container . '.klevu_cms_sync_history_fieldset';
        $historyListing = $historyFieldset . '.klevu_cms_sync_history';
        $historyConsolidationFieldset = $container . '.klevu_cms_sync_consolidated_history_fieldset';
        $historyConsolidationListing = $historyConsolidationFieldset . '.klevu_cms_sync_history_consolidation';

        return [
            [
                'provider' => $actionListing,
                'target' => 'destroyInserted',
            ],
            [
                'provider' => $historyListing,
                'target' => 'destroyInserted',
            ],
            [
                'provider' => $historyConsolidationListing,
                'target' => 'destroyInserted',
            ],
            [
                'provider' => $actionListing,
                'target' => 'updateData',
                'params' => [
                    'target_id' => $pageId,
                ],
            ],
            [
                'provider' => $historyListing,
                'target' => 'updateData',
                'params' => [
                    'target_id' => $pageId,
                ],
            ],
            [
                'provider' => $historyConsolidationListing,
                'target' => 'updateData',
                'params' => [
                    'target_id' => $pageId,
                ],
            ],
            [
                'provider' => $modal,
                'target' => 'setTitle',
                'params' => __(
                    'Klevu Entity Sync Information: Page ID: %1',
                    $pageId,
                ),
            ],
            [
                'provider' => $modal,
                'target' => 'openModal',
            ],
        ];
    }
}
