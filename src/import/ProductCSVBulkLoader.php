<?php

namespace SilverCommerce\CatalogueAdmin\Import;

use SilverStripe\Dev\CsvBulkLoader;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\TaxAdmin\Model\TaxRate;
use SilverStripe\SiteConfig\SiteConfig;
use Product;

/**
 * Allow slightly more complex product imports from a CSV file
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class ProductCSVBulkLoader extends CsvBulkLoader
{
    
    public $columnMap = [
        "Product"   => "ClassName",
        "ClassName" => "ClassName",
        "SKU"       => "StockID",
        "Name"      => "Title",
        "Price"     => "BasePrice",
        "TaxPercent"=> '->importTaxPercent'
    ];

    public $duplicateChecks = [
        'ID'        => 'ID',
        'SKU'       => 'StockID',
        'StockID'   => 'StockID'
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

                // Find any categories (denoted by a 'CategoryXX' column)
                if (strpos($key, 'Category') !== false) {
                    $category = CatalogueCategory::get()
                        ->filter("Title", $value)
                        ->first();

                    if ($category) {
                        $object->Categories()->add($category);
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
                    $product = Product::get()
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
