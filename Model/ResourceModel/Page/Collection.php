<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Model\ResourceModel\Page;

use Magento\Cms\Model\ResourceModel\Page\Collection as MagentoPageCollection;

/**
 * We have provided extensions of core magento collections, as we often find third-party modules can hijack
 * all entity collections in order to add their own attributes/filters to the collections.
 * This class makes it possible for developers to target core collections used by Klevu only.
 * e.g. you may wish add a plugin to remove some third-party filter from the collection when it is used by Klevu.
 *
 * Unfortunately it is not possible to use a virtualType for this
 * as a virtualType has the same class name as the class it is a type of.
 *
 * Klevu Developers Note:
 * DO NOT set a preference for this class
 * DO NOT add any methods or properties to this class
 */
class Collection extends MagentoPageCollection
{
}
