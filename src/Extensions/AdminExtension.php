<?php

namespace SilverCommerce\CatalogueAdmin\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * Inject extra requirements into the CMS
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package orders
 */
class AdminExtension extends Extension
{
    public function init()
    {
        Requirements::css('silvercommerce/catalogue-admin: client/dist/css/admin.css');
    }
}
