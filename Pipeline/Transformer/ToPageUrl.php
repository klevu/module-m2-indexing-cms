<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Pipeline\Transformer;

use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\UrlInterface;

class ToPageUrl implements TransformerInterface
{
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        UrlInterface $urlBuilder,
    ) {
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return string
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): string {
        if (!($data instanceof PageInterface)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: PageInterface::class,
                arguments: $arguments,
                data: $data,
            );
        }

        return $this->urlBuilder->getUrl(
            routePath: null,
            routeParams: ['_direct' => $data->getIdentifier()],
        );
    }
}
