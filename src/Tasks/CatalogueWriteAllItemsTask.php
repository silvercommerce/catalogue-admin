<?php

namespace SilverCommerce\CatalogueAdmin\Tasks;

use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
use SilverStripe\Core\Config\Config;

/**
 * Loops through all products and Categories, and sets their URL Segments, if
 * they do not already have one
 *
 * @package commerce
 * @subpackage tasks
 */
class CatalogueWriteAllItemsTask extends BuildTask
{
    protected $title = 'Write All Products and Categories';
    
    protected $description = 'Loop through all products and product categories and re-save them.';

    private static $run_during_dev_build = true;
    
    public function run($request)
    {
        $products = 0;
        $categories = 0;
        
        // First load all products
        $items = CatalogueProduct::get();
        
        /** @var CatalogueProduct $item */
        foreach ($items as $item) {
            $class = Config::inst()->get(
                CatalogueProduct::class,
                'default_subclass'
            );

            // Alter any existing recods that might have the wrong classname
            if ($item->ClassName === CatalogueProduct::class) {
                $item->ClassName = $class;
            }

            // Just write product, on before write should deal with the rest
            $item->write(false, false, true);
            $products++;
        }
    
        // Then all categories
        $items = CatalogueCategory::get();
        
        /** @var CatalogueCategory $item */
        foreach ($items as $item) {
            $class = Config::inst()->get(
                CatalogueCategory::class,
                'default_subclass'
            );

            if ($item->ClassName === CatalogueCategory::class) {
                $item->ClassName = $class;
            }

            // Just write category, on before write should deal with the rest
            $item->write(false, false, true);
            $categories++;
        }

        DB::alteration_message("Wrote $products products and $categories categories.\n", 'obsolete');
    }
}
