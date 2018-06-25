<?php

namespace SilverCommerce\CatalogueAdmin\Admin;

use \Product;
use \Category;
use SilverStripe\Admin\ModelAdmin;
use SilverCommerce\CatalogueAdmin\Model\ProductTag;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverCommerce\CatalogueAdmin\Import\ProductCSVBulkLoader;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_Catalogue;

/**
 * CatalogueAdmin creates an admin area that allows editing of products
 * and product categories
 *
 * @author Mo <morven@ilateral.co.uk>
 * @author Mark <mark@ilateral.co.uk>
 * @package catalogue
 * @subpackage admin
 */
class CatalogueAdmin extends ModelAdmin
{
    /**
     * Set the page length for products
     * 
     * @config
     */
    private static $product_page_length = 20;
    
    /**
     * Set the page length for categories
     * 
     * @config
     */
    private static $category_page_length = 20;
    
    private static $url_segment = 'catalogue';

    private static $menu_title = 'Catalogue';

    private static $menu_priority = 11;

    private static $managed_models = [
        Product::class,
        Category::class,
        ProductTag::class
    ];

    private static $model_importers = [
        Product::class => ProductCSVBulkLoader::class
    ];

    public function init()
    {
        parent::init();
    }
    
    public function getExportFields()
    {
        $fields = [
            "Title" => "Title",
            "URLSegment" => "URLSegment"
        ];
        
        if ($this->modelClass == Product::class) {
            $fields["StockID"] = "StockID";
            $fields["ClassName"] = "Type";
            $fields["BasePrice"] = "Price";
            $fields["TaxRate.Amount"] = "TaxPercent";
            $fields["Images.first.Name"] = "Image1";
            $fields["Categories.first.Title"] = "Category1";
            $fields["Content"] = "Content";
        }
        
        $this->extend("updateExportFields", $fields);
        
        return $fields;
    }

    public function getList()
    {
        $list = parent::getList();
        
        // Filter categories
        if ($this->modelClass == Category::class) {
            $list = $list->filter('ParentID', 0);
        }
        
        $this->extend('updateList', $list);

        return $list;
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        $fields = $form->Fields();
        $import_button = null;
        $grid = $fields
            ->fieldByName($this->sanitiseClassName($this->modelClass));

        if ($this->showImportForm) {
            $import_button = GridFieldImportButton::create('buttons-before-right')
                ->setImportForm($this->ImportForm())
                ->setModalTitle(
                    _t(
                        'SilverStripe\\Admin\\ModelAdmin.IMPORT',
                        'Import from CSV'
                    )
                );
        }

        if ($this->modelClass == Product::class && $grid) {
            $grid->setConfig(GridFieldConfig_Catalogue::create(
                $this->modelClass,
                $this->config()->product_page_length
            ));

            if ($import_button) {
                $grid->getConfig()->addComponent($import_button);
            }
        }
        
        if ($this->modelClass == Category::class && $grid) {
            $grid->setConfig(GridFieldConfig_Catalogue::create(
                $this->modelClass,
                $this->config()->category_page_length,
                "Sort"
            ));

            if ($import_button) {
                $grid->getConfig()->addComponent($import_button);
            }
        }

        if ($this->modelClass == ProductTag::class && $grid) {
            $config = $grid->getConfig();
            $config->addComponent(GridFieldOrderableRows::create());
        }

        $this->extend("updateEditForm", $form);

        return $form;
    }
}