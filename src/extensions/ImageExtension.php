<?php

namespace SilverCommerce\CatalogueAdmin\Extensions;

use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverStripe\ORM\DataExtension;

class ImageExtension extends DataExtension
{
    private static $belongs_many_many = [
        'Products'      => CatalogueProduct::class
    ];
}
