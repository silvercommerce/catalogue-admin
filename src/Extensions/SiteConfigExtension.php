<?php

namespace SilverCommerce\CatalogueAdmin\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverCommerce\CatalogueAdmin\Helpers\Helper;

class SiteConfigExtension extends DataExtension
{

    private static $has_one = [
        'DefaultProductImage'    => Image::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
        // Add config sets
        $fields->addFieldsToTab(
            'Root.Shop',
            ToggleCompositeField::create(
                'CatalogueSettings',
                _t("SilverCommerce\CatalogueAdmin.CatalogueSettings", "Catalogue Settings"),
                [
                    UploadField::create(
                        'DefaultProductImage',
                        _t("SilverCommerce\CatalogueAdmin.DefaultProductImage", 'Default product image')
                    )
                ]
            )
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
