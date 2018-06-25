<?php

namespace SilverCommerce\CatalogueAdmin\Admin;

use \Product;
use \Category;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\Config\Config;
use SilverCommerce\CatalogueAdmin\Model\ProductTag;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverCommerce\CatalogueAdmin\Import\ProductCSVBulkLoader;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_Catalogue;
use SilverStripe\Forms\GridField\GridFieldPrintButton;

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

    /**
     * Get the default export fields for the current model.
     * 
     * First this checks if the `export_fields` config variable is set on
     * the model class, if not, it reverts to the default behaviour.
     * 
     * @return array
     */
    public function getExportFields()
    {
        $export_fields = Config::inst()->get(
            $this->modelClass,
            "export_fields"
        );

        if (isset($export_fields) && is_array($export_fields)) {
            $fields = $export_fields;
        } else {
            $fields = parent::getExportFields();
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

        $export_button = new GridFieldExportButton('buttons-before-right');
        $export_button->setExportColumns($this->getExportFields());

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
        }
        
        if ($this->modelClass == Category::class && $grid) {
            $grid->setConfig(GridFieldConfig_Catalogue::create(
                $this->modelClass,
                $this->config()->category_page_length,
                "Sort"
            ));
        }

        if ($this->modelClass == ProductTag::class && $grid) {
            $grid
                ->getConfig()
                ->removeComponentsByType(GridFieldImportButton::class)
                ->removeComponentsByType(GridFieldPrintButton::class)
                ->addComponent(GridFieldOrderableRows::create());
        }

        $config = $grid->getConfig();

        $config
            ->removeComponentsByType(GridFieldExportButton::class)
            ->addComponents(new GridFieldPrintButton('buttons-before-right'))
            ->addComponent($export_button);

        if ($import_button) {
            $config->addComponent($import_button);
        }

        $this->extend("updateEditForm", $form);

        return $form;
    }
}