<?php

namespace SilverCommerce\CatalogueAdmin;

use SilverStripe\View\ViewableData;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;

/**
 * Object designed to allow injection of catalogue global settings into
 * templates without having to flood the base controller with methods   
 * 
 * @author Mo <morven@ilateral.co.uk>
 * @author Mark <mark@ilateral.co.uk>
 * @package catalogue
 */
class Catalogue extends ViewableData
{
    
    /**
     * Gets a list of all Categories, either top level (default) or
     * from a sub level
     *
     * @param Parent the ID of a parent cetegory
     * @return SS_List
     */
    public function Categories($ParentID = 0)
    {
        return CatalogueCategory::get()
            ->filter(array(
                "ParentID" => $ParentID,
                "Disabled" => 0
            ));
    }

    /**
     * Get a full list of products, filtered by a category if provided.
     *
     * @param ParentCategoryID the ID of the parent category
     */
    public function Products($ParentID = 0)
    {
        return CatalogueProduct::get()
            ->filter(array(
                "ParentID" => $ParentID,
                "Disabled" => 0
            ));
    }
}
