<?php

namespace SilverCommerce\CatalogueAdmin\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

class AdminExtension extends Extension
{
    public function init()
    {
        Requirements::css('silvercommerce/catalogue-admin: client/dist/css/admin.css');
    }
}
