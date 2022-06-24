<?php

namespace SilverCommerce\CatalogueAdmin\Forms\GridField;

use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClassHandler;

class AddNewMultiClassHandler extends GridFieldAddNewMultiClassHandler
{
    private static $allowed_actions = [
        'ItemEditForm'
    ];

    /**
     * Overload default edit form
     *
     * @return \SilverStripe\Forms\Form
     */
    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();
        $fields = $form->Fields();

        // Manually set Parent ID to this record and lock it if we aer creating a new category
        // as CatalogueCategory::Children() doesn't return a HasManyList
        $id = $this->getRequest()->param("ID");
        $record = $this->getRecord();
        $parent_field = $fields->dataFieldByName('ParentID');

        if (!empty($id) && !empty($parent_field) && !empty($record) && is_a($record, CatalogueCategory::class)) {
            $parent_field->setValue($id);
        }

        return $form;
    }
}
