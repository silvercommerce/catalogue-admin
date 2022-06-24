<?php

namespace SilverCommerce\CatalogueAdmin\Extensions;

use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverStripe\ORM\DataExtension;

/**
 * Extension for Image that allows mapping of products to multiple
 * images
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package product-catalogue
 */
class ImageExtension extends DataExtension
{
    private static $belongs_many_many = [
        'Products'      => CatalogueProduct::class
    ];
}
