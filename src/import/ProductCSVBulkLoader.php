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

    /**
     * Generate the selected relation from the provided array of values
     * 
     * @param string $object   The current object being imported
     * @param string $relation The name of the relation (eg Images)
     * @param array  $list     The list of values
     * @param string $class    The source class of the relation (eg SilverStripe\Assets\Image)
     * @param string $column   The name of the column to search for existing records
     * @param string $create   Create a new object if none found
     * 
     * @return void
     */
    protected function createRelationFromList(
        $object,
        $relation,
        $list,
        $class,
        $column,
        $create = false
    ) {
        $object->$relation()->removeAll();

        foreach ($list as $name) {
            $name = trim($name);

            if (!empty($name)) {
                $obj = $class::get()->find($column, $name);

                if (empty($obj) && $create) {
                    $obj = $class::create();
                    $obj->$column = $name;
                    $obj->write();
                }

                $object->$relation()->add($obj);
            }
        }
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
                    $this->createRelationFromList(
                        $object,
                        'Tags',
                        explode(",", $value),
                        ProductTag::class,
                        'Title',
                        true
                    );
                }

                // Find and add any images to be imported
                if ($key == 'Images' && !empty($value)) {
                    $this->createRelationFromList(
                        $object,
                        'Images',
                        explode(",", $value),
                        Image::class,
                        'Name'
                    );
                }

                // Find and add any related products to be imported
                if ($key == 'RelatedProducts' && !empty($value)) {
                    $this->createRelationFromList(
                        $object,
                        'RelatedProducts',
                        explode(",", $value),
                        CatalogueProduct::class,
                        'StockID'
                    );
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
