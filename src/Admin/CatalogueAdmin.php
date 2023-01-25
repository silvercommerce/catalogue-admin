<?php

namespace SilverCommerce\CatalogueAdmin\Admin;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverCommerce\CatalogueAdmin\Model\ProductTag;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use ilateral\SilverStripe\ModelAdminPlus\ModelAdminPlus;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
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
class CatalogueAdmin extends ModelAdminPlus
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

    private static $menu_icon_class = 'font-icon-p-shop';

    private static $managed_models = [
        CatalogueProduct::class,
        CatalogueCategory::class,
        ProductTag::class
    ];

    private static $model_importers = [
        CatalogueProduct::class => ProductCSVBulkLoader::class
    ];

    /**
     * Listen for customised export fields on the currently managed object
     *
     * @return array
     */
    public function getExportFields()
    {
        $model = singleton($this->modelClass);
        if ($model->hasMethod('getExportFields')) {
            return $model->getExportFields();
        }

        return parent::getExportFields();
    }

    /**
     * Is the current managed model a category?
     *
     * @return boolean
     */
    protected function isCategory()
    {
        $singleton = singleton($this->getModelClass());

        if (is_a($singleton, CatalogueCategory::class)) {
            return true;
        }

        return false;
    }

    /**
     * Is the current managed model a product?
     *
     * @return boolean
     */
    protected function isProduct()
    {
        $singleton = singleton($this->getModelClass());

        if (is_a($singleton, CatalogueProduct::class)) {
            return true;
        }

        return false;
    }

    /**
     * Is the current managed model a product tag?
     *
     * @return boolean
     */
    protected function isTag()
    {
        $singleton = singleton($this->getModelClass());

        if (is_a($singleton, ProductTag::class)) {
            return true;
        }

        return false;
    }

    /**
     * Update the current gridfield list
     *
     * @return \SilverStripe\ORM\SS_List
     */
    public function getList()
    {
        $list = parent::getList();

        // Filter categories
        if ($this->isCategory()) {
            $list = $list->filter('ParentID', 0);
        }

        $this->extend('updateList', $list);

        return $list;
    }

    protected function getGridFieldConfig(): GridFieldConfig
    {
        $model = $this->getModelClass();
        $singleton = singleton($model);
        $config = parent::getGridFieldConfig();
        $import_button = $config->getComponentByType(GridFieldImportButton::class);

        if ($this->isProduct()) {
            $config = GridFieldConfig_Catalogue::create(
                $model,
                $this->config()->product_page_length
            );

            /** @var GridFieldExportButton  */
            $export_button = $config->getComponentByType(GridFieldExportButton::class);
            $export_button->setExportColumns($this->getExportFields());
            
            $config
                ->removeComponentsByType(GridFieldExportButton::class)
                ->addComponents(new GridFieldPrintButton('buttons-before-left'))
                ->addComponent($export_button);

            if (!empty($import_button)) {
                $config->addComponent($import_button);
            }
        }
        
        if ($this->isCategory()) {
            $config = GridFieldConfig_Catalogue::create(
                $this->modelClass,
                $this->config()->category_page_length,
                "Sort"
            );

            $config->removeComponentsByType(GridFieldExportButton::class);

            if (!empty($import_button)) {
                $config->addComponent($import_button);
            }
        }

        // Re-add vaidation
        if (($this->isProduct() || $this->isCategory())
            && $singleton->hasMethod('getCMSCompositeValidator')
        ) {
            $detailValidator = $singleton->getCMSCompositeValidator();
            /** @var GridFieldDetailForm $detailform */
            $detailform = $config->getComponentByType(GridFieldDetailForm::class);
            $detailform->setValidator($detailValidator);
        }

        if ($this->isTag()) {
            $config
                ->removeComponentsByType(GridFieldImportButton::class)
                ->addComponent(new GridFieldOrderableRows('Sort'));
        }

        $this->extend('updateGridFieldConfig', $config);

        return $config;
    }
}
