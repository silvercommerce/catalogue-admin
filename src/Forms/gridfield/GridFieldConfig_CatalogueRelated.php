<?php

namespace SilverCommerce\CatalogueAdmin\Forms\GridField;

use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

/**
 * Allows editing of records contained within the GridField, instead of only allowing the ability to view records in
 * the GridField.
 *
 * @package forms
 * @subpackage fields-gridfield
 */
class GridFieldConfig_CatalogueRelated extends GridFieldConfig_Catalogue
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
        parent::__construct($classname, $itemsPerPage, $sort_col, $create_baseclass);

        // Remove uneeded components
        $this->removeComponentsByType(GridFieldDeleteAction::class);
        $this->removeComponentsByType(GridFieldExportButton::class);

        $this->addComponent(new GridFieldAddExistingAutocompleter('buttons-before-right'));
        $this->addComponent(new GridFieldDeleteAction(true));
    }
}
