<?php

namespace SilverCommerce\CatalogueAdmin\Import;

use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\CsvBulkLoader;
use SilverStripe\SiteConfig\SiteConfig;
use SilverCommerce\TaxAdmin\Model\TaxRate;
use SilverCommerce\CatalogueAdmin\Model\ProductTag;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;

/**
 * Allow slightly more complex product imports from a CSV file
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class ProductCSVBulkLoader extends CsvBulkLoader
{
    
    public $columnMap = [
        "Product"       => "ClassName",
        "ClassName"     => "ClassName",
        "SKU"           => "StockID",
        "Stock ID"      => "StockID",
        "Name"          => "Title",
        "Price"         => "BasePrice",
        "TaxPercent"    => '->importTaxPercent',
        "Tax Percent"   => '->importTaxPercent'
    ];

    public $duplicateChecks = [
        'ID'        => 'ID',
        'SKU'       => 'StockID',
        'StockID'   => 'StockID',
        'Stock ID'  => 'StockID'
    ];

    public function __construct($objectClass = null)
    {
        if (class_exists(Product::class)) {
            if (!$objectClass || $objectClass == CatalogueProduct::class) {
                $objectClass = Product::class;
                $this->objectClass = Product::class;
            }
        }

        parent::__construct($objectClass);
    }

    public function processRecord($record, $columnMap, &$results, $preview = false)
    {

        // Get Current Object
        $objID = parent::processRecord($record, $columnMap, $results, $preview);
        $object = DataObject::get_by_id($this->objectClass, $objID);

        $this->extend("onBeforeProcess", $object, $record, $columnMap, $results, $preview);
        
        if ($object != null) {

            // Loop through all fields and setup associations
            foreach ($record as $key => $value) {

                // Find and add any categories imported
                if ($key == 'Categories' && !empty($value)) {
                    $object->Categories()->removeAll();
                    $categories = explode(",", $value);

                    foreach ($categories as $cat_name) {
                        $cat_name = trim($cat_name);

                        if (!empty($cat_name)) {
                            $cat = CatalogueCategory::getFromHierarchy($cat_name);

                            if (empty($cat)) {
                                $cat = CatalogueCategory::create();
                                $cat->Title = $cat_name;
                                $cat->write();
                            }

                            $object->Categories()->add($cat);
                        }
                    }
                }

                // Find and add any tags imported
                if ($key == 'Tags' && !empty($value)) {
                    $object->Tags()->removeAll();
                    $tags = explode(",", $value);

                    foreach ($tags as $tag_name) {
                        $tag_name = trim($tag_name);

                        if (!empty($tag_name)) {
                            $tag = ProductTag::get()->find("Title", $tag_name);

                            if (empty($tag)) {
                                $tag = ProductTag::create();
                                $tag->Title = $tag_name;
                                $tag->write();
                            }

                            $object->Tags()->add($tag);
                        }
                    }
                }
                
                // Find any Images (denoted by a 'ImageXX' column)
                if (strpos($key, 'Image') !== false && $key != "Images") {
                    $image = Image::get()
                        ->filter("Name", $value)
                        ->first();

                    if ($image) {
                        $object->Images()->add($image);
                    }
                }
                
                // Find any related products (denoted by a 'RelatedXX' column)
                if (strpos($key, 'Related') !== false && $key != "RelatedProducts") {
                    $product = CatalogueProduct::get()
                        ->filter("StockID", $value)
                        ->first();

                    if ($product) {
                        $object->RelatedProducts()->add($product);
                    }
                }
            }

            $this->extend("onAfterProcess", $object, $record, $columnMap, $results, $preview);

            $object->destroy();
            unset($object);
        }

        return $objID;
    }

    public static function importTaxPercent(&$obj, $val, $record)
    {
        $config = SiteConfig::current_site_config();

        $tax = $config
            ->TaxCategories()
            ->filter("Rates.Rate", $val)
            ->first();
        
        if ($tax) {
            $obj->TaxCategoryID = $tax->ID;
        }
    }
}
