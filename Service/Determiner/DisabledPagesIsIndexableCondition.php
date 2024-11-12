<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Service\Determiner;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableConditionInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class DisabledPagesIsIndexableCondition implements IsIndexableConditionInterface
{
    public const XML_PATH_EXCLUDE_DISABLED_CMS = 'klevu/indexing/exclude_disabled_cms';

    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param ScopeProviderInterface $scopeProvider
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        ScopeProviderInterface $scopeProvider,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->scopeProvider = $scopeProvider;
    }

    /**
     * @param PageInterface|ExtensibleDataInterface $entity
     * @param StoreInterface $store
     * @param string $entitySubtype
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function execute(
        PageInterface|ExtensibleDataInterface $entity,
        StoreInterface $store,
        string $entitySubtype = '', // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): bool {
        if (!($entity instanceof PageInterface)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid argument provided for "$entity". Expected %s, received %s.',
                    PageInterface::class,
                    get_debug_type($entity),
                ),
            );
        }
        return !$this->isCheckEnabled() || $this->isIndexable(page: $entity, store: $store);
    }

    /**
     * @return bool
     */
    private function isCheckEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_DISABLED_CMS,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            null,
        );
    }

    /**
     * @param PageInterface $page
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isIndexable(PageInterface $page, StoreInterface $store): bool
    {
        $isPageEnabled = $this->isPageEnabled(page: $page);
        if (!$isPageEnabled) {
            $currentScope = $this->scopeProvider->getCurrentScope();
            $this->scopeProvider->setCurrentScope(scope: $store);
            $this->logger->debug(
                message: 'Store ID: {storeId} Page ID: {pageId} not indexable due to Is Active: {isActive} in {method}',
                context: [
                    'storeId' => $store->getId(),
                    'pageId' => $page->getId(),
                    'isActive' => $page->isActive(),
                    'method' => __METHOD__,
                ],
            );
            if ($currentScope->getScopeObject()) {
                $this->scopeProvider->setCurrentScope(scope: $currentScope->getScopeObject());
            } else {
                $this->scopeProvider->unsetCurrentScope();
            }
        }

        return $isPageEnabled;
    }

    /**
     * @param PageInterface $page
     *
     * @return bool
     */
    private function isPageEnabled(PageInterface $page): bool
    {
        return (bool)$page->isActive();
    }
}
