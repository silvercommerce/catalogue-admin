<?php

namespace SilverCommerce\CatalogueAdmin\Import;

use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\CsvBulkLoader;
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
        "CategoriesList"        => '->importCategoriesList',
        "TagsList"              => '->importTagsList',
        "ImagesList"            => '->importImagesList',
        "RelatedProductsList"   => '->importRelatedProductsList'
    ];

    public $duplicateChecks = [
        'ID'        => 'ID',
        'StockID'   => 'StockID'
    ];

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
    protected static function createRelationFromList(
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

                if (!empty($obj)) {
                    $object->$relation()->add($obj);
                }
            }
        }
    }

    public function processRecord($record, $columnMap, &$results, $preview = false)
    {
        $this->extend("onBeforeProcess", $record, $columnMap, $results, $preview);

        // If classname is set, ensure we use it (as by default all objects are created as CatalogueProduct)
        $curr_class = null;
        if (isset($record['ClassName']) && class_exists($record['ClassName'])) {
            $curr_class = $this->objectClass;
            $this->objectClass = $record['ClassName'];
        }

        $objID = parent::processRecord($record, $columnMap, $results, $preview);
        $object = DataObject::get_by_id($this->objectClass, $objID);
        
        $this->extend("onAfterProcess", $object, $record, $columnMap, $results, $preview);

        $object->destroy();
        unset($object);

        // Reset default object class
        if (!empty($curr_class)) {
            $this->objectClass = $curr_class;
        }

        return $objID;
    }

    public static function importCategoriesList(&$obj, $val, $record)
    {
        $obj->Categories()->removeAll();
        $categories = explode(",", $val);

        foreach ($categories as $cat_name) {
            $cat_name = trim($cat_name);

            if (!empty($cat_name)) {
                $cat = CatalogueCategory::findOrMakeHierarchy($cat_name)->last();
                $obj->Categories()->add($cat);
            }
        }
    }

    public static function importTagsList(&$obj, $val, $record)
    {
        self::createRelationFromList(
            $obj,
            'Tags',
            explode(",", $val),
            ProductTag::class,
            'Title',
            true
        );
    }

    public static function importImagesList(&$obj, $val, $record)
    {
        self::createRelationFromList(
            $obj,
            'Images',
            explode(",", $val),
            Image::class,
            'Name'
        );
    }

    public static function importRelatedProductsList(&$obj, $val, $record)
    {
        self::createRelationFromList(
            $obj,
            'RelatedProducts',
            explode(",", $val),
            CatalogueProduct::class,
            'StockID'
        );
    }
}
