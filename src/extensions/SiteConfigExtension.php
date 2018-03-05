<?php

namespace SilverCommerce\CatalogueAdmin\Extensions;

use Exception;
use ReflectionClass;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Helpers\Helper;

/**
 * Provides additional settings required globally for this module
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package product-catalogue
 */
class SiteConfigExtension extends DataExtension
{
    
    private static $db = [
        "ShowPriceAndTax" => "Boolean"
    ];

    private static $has_one = [
        'DefaultProductImage'    => Image::class
    ];

    public function updateCMSFields(FieldList $fields)
    {   
        // Add config sets
        $fields->addFieldsToTab(
            'Root.Catalogue',
            [
                UploadField::create(
                    'DefaultProductImage',
                    _t("Catalogue.DefaultProductImage", 'Default product image')
                ),
                CheckboxField::create("ShowPriceAndTax")
                    ->setDescription(_t(
                        "Catalogue.ShowPriceAndTaxDescription",
                        "Show product prices including tax"
                    )),
            ]
        );
    }

    public function onAfterWrite()
    {
        // Setup default product image (if not set)
        if (!$this->owner->DefaultProductImage()->exists()) {
            $image = Helper::generate_no_image();
            $this->owner->DefaultProductImageID = $image->ID;
            $this->owner->write();
        }
    }
}
