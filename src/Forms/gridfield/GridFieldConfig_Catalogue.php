<?php

namespace SilverCommerce\CatalogueAdmin\Forms\GridField;

use Colymba\BulkManager\BulkAction\EditHandler;
use Colymba\BulkManager\BulkAction\UnlinkHandler;
use SilverCommerce\CatalogueAdmin\Helpers\Helper;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use Colymba\BulkManager\BulkManager as GridFieldBulkManager;
use SilverCommerce\CatalogueAdmin\BulkManager\ProductEditHandler;
use SilverStripe\Versioned\GridFieldArchiveAction;
use SilverStripe\Versioned\VersionedGridFieldState\VersionedGridFieldState;
use Symbiote\GridFieldExtensions\GridFieldConfigurablePaginator;

/**
 * Allows editing of records contained within the GridField, instead of only allowing the ability to view records in
 * the GridField.
 *
 * @package forms
 * @subpackage fields-gridfield
 */
class GridFieldConfig_Catalogue extends GridFieldConfig
{
    /**
     *
     * @param array $classname Name of class who's subclasses will be added to form
     * @param int $itemsPerPage - How many items per page should show up
     * @param boolean | string $sorting Allow sorting of rows, either false or the name of the sort column
     * @param boolean $create_baseclass Allow adding of the baseclass to the multiclass dropdown 
     */
    public function __construct($classname, $itemsPerPage = null, $sort_col = false, $create_baseclass = false)
    {
        parent::__construct();

        // Setup initial gridfield
        $this
            ->addComponent(new GridFieldButtonRow('before'))
            ->addComponent(new GridFieldToolbarHeader())
            ->addComponent($sort = new GridFieldSortableHeader())
            ->addComponent($filter = new GridFieldFilterHeader())
            ->addComponent(new GridFieldDataColumns())
            ->addComponent(new VersionedGridFieldState([])) // Set state display to first column
            ->addComponent(new GridFieldEditButton())
            ->addComponent(new GridFieldArchiveAction())
            ->addComponent(new GridField_ActionMenu())
            ->addComponent(new GridFieldPageCount('toolbar-header-right'))
            ->addComponent($pagination = new GridFieldConfigurablePaginator($itemsPerPage))
            ->addComponent(new GridFieldExportButton("buttons-before-right"));

        $detailform = new GridFieldDetailForm();
        $detailform->setItemRequestClass(CatalogueDetailForm_ItemRequest::class);
        $this->addComponent($detailform);

        // Setup Bulk manager
        $manager = new GridFieldBulkManager();
        $manager->removeBulkAction(UnlinkHandler::class);
        $manager->removeBulkAction(EditHandler::class);
        $manager->addBulkAction(ProductEditHandler::class);
        $this->addComponent($manager);

        // Setup add new button
        $subclasses = Helper::getCreatableClasses($classname, $create_baseclass);
        $add_button = new AddNewMultiClass("buttons-before-left");
        $add_button->setClasses($subclasses);
        $this->addComponent($add_button);

        if ($sort_col) {
            $this->addComponent(new GridFieldOrderableRows($sort_col));
        }

        $sort->setThrowExceptionOnBadDataType(false);
        $filter->setThrowExceptionOnBadDataType(false);
        $pagination->setThrowExceptionOnBadDataType(false);
    }
}
