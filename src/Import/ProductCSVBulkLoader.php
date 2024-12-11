<?php

namespace SilverCommerce\CatalogueAdmin\Import;

use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\CsvBulkLoader;
use SilverStripe\Dev\BulkLoader_Result;
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
     * @param DataObject $object   The current object being imported
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

    /**
     * Is the current row of data empty (excel sometimes
     * creates CSV's with empty rows)
     *
     * @return bool
     */
    protected function isEmptyRow(array $record)
    {
        $empty_count = 0;

        foreach ($record as $key => $value) {
            if (empty($value)) {
                $empty_count++;
            }
        }

        if (count(array_keys($record)) === $empty_count) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     *
     * @param array $record
     * @param array $columnMap
     * @param BulkLoader_Result $results
     * @param boolean $preview
     *
     * @return int
     */
    public function processRecord($record, $columnMap, &$results, $preview = false)
    {
        $this->extend("onBeforeProcess", $record, $columnMap, $results, $preview);

        $empty_row = $this->isEmptyRow($record);

        if ($empty_row === true) {
            return 0;
        }

        // If classname is set, ensure we either manually set the custom classname
        // (for existing), or create a new object of the correct class and write it
        // (as by default all objects are created as CatalogueProduct)
        $curr_class = null;
        if (isset($record['ClassName']) && class_exists($record['ClassName'])) {
            $curr_class = $this->objectClass;
            $this->objectClass = $record['ClassName'];
            $existingObj = $this->findExistingObject($record, $columnMap);
            /** @var DataObject $obj */
            $obj = ($existingObj) ? $existingObj : $this->objectClass::create();
            $obj->ClassName = $record['ClassName'];
            $obj->write();
            $record['ID'] = $obj->ID;
        }

        $objID = parent::processRecord($record, $columnMap, $results, $preview);
        $object = DataObject::get_by_id(CatalogueProduct::class, $objID);

        $this->extend("onAfterProcess", $object, $record, $columnMap, $results, $preview);

        if (!empty($object)) {
            $object->destroy();
            unset($object);
        }

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
