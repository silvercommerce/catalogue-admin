<?php

namespace SilverCommerce\CatalogueAdmin\Extensions;

use SilverStripe\Core\Extension;
use SilverCommerce\CatalogueAdmin\Catalogue;

/**
 * Extension for Controller that provide additional methods to all
 * templates 
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class ControllerExtension extends Extension
{   
    /**
     * Inject our product catalogue object into the controller
     * 
     * @return Catalogue
     */
    public function getCatalogue()
    {
        return Catalogue::create();
    }
}
