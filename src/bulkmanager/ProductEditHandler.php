<?php

namespace SilverCommerce\CatalogueAdmin\BulkManager;

use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\TreeMultiselectField;
use Colymba\BulkManager\BulkAction\EditHandler;

class ProductEditHandler extends EditHandler
{
    /**
     * RequestHandler allowed actions.
     *
     * @var array
     */
    private static $allowed_actions = [
        'index',
        'bulkEditForm',
        'recordEditForm',
    ];

    /**
     * @param GridField            $gridField
     * @param GridField_URLHandler $component
     */
    public function __construct($gridField = null, $component = null)
    {
        parent::__construct($gridField, $component);
    }

    /**
     * Return a form for all the selected DataObjects
     * with their respective editable fields.
     *
     * @return Form Selected DataObjects editable fields
     */
    public function bulkEditForm()
    {
        $crumbs = $this->Breadcrumbs();
        if ($crumbs && $crumbs->count() >= 2) {
            $one_level_up = $crumbs->offsetGet($crumbs->count() - 2);
        }

        $actions = new FieldList();

        $actions->push(
            FormAction::create('doSave', _t('GRIDFIELD_BULKMANAGER_EDIT_HANDLER.SAVE_BTN_LABEL', 'Save all'))
                ->setAttribute('id', 'bulkEditingSaveBtn')
                ->addExtraClass('btn btn-success')
                ->setAttribute('data-icon', 'accept')
                ->setUseButtonTag(true)
        );

        $actions->push(
            FormAction::create('Cancel', _t('GRIDFIELD_BULKMANAGER_EDIT_HANDLER.CANCEL_BTN_LABEL', 'Cancel'))
                ->setAttribute('id', 'bulkEditingUpdateCancelBtn')
                ->addExtraClass('btn btn-danger cms-panel-link')
                ->setAttribute('data-icon', 'decline')
                ->setAttribute('href', $one_level_up->Link)
                ->setUseButtonTag(true)
                ->setAttribute('src', '')//changes type to image so isn't hooked by default actions handlers
        );

        $recordList = $this->getRecordIDList();
        $recordsFieldList = new FieldList();
        $editingCount = count($recordList);
        $modelClass = $this->gridField->getModelClass();
        $singleton = singleton($modelClass);
        $titleModelClass = (($editingCount > 1) ? $singleton->i18n_plural_name() : $singleton->i18n_singular_name());

        //some cosmetics
        $headerText = _t(
            'GRIDFIELD_BULKMANAGER_EDIT_HANDLER.HEADER_TEXT',
            'Editing {count} {class}',
            [
                'count' => $editingCount,
                'class' => $titleModelClass,
            ]
        );
        $header = LiteralField::create(
            'bulkEditHeader',
            '<h1 id="bulkEditHeader">'.$headerText.'</h1>'
        );
        $recordsFieldList->push($header);

        $toggle = LiteralField::create(
            'bulkEditToggle',
            '<span id="bulkEditToggle">' . _t('GRIDFIELD_BULKMANAGER_EDIT_HANDLER.TOGGLE_ALL_LINK', 'Show/Hide all') . '</span>'
        );
        $recordsFieldList->push($toggle);

        //fetch fields for each record and push to fieldList
        foreach ($recordList as $id) {
            $record = DataObject::get_by_id($modelClass, $id);
            $recordEditingFields = $this->getRecordEditingFields($record);

            $toggleField = ToggleCompositeField::create(
                'RecordFields_' . $id,
                $record->getTitle(),
                $recordEditingFields
            )
            ->setHeadingLevel(4)
            ->setAttribute('data-id', $id)
            ->addExtraClass('bulkEditingFieldHolder');

            $recordsFieldList->push($toggleField);
        }

        $bulkEditForm = Form::create(
            $this,
            'recordEditForm', //recordEditForm name is here to trick SS to pass all subform request to recordEditForm()
            $recordsFieldList,
            $actions
        );

        if ($crumbs && $crumbs->count() >= 2) {
            $bulkEditForm->Backlink = $one_level_up->Link;
        }

        //override form action URL back to bulkEditForm
        //and add record ids GET var
        $bulkEditForm->setAttribute(
            'action',
            $this->Link('bulkEditForm?records[]='.implode('&', $recordList))
        );

        return $bulkEditForm;
    }

    /**
     * Return's a form with only one record's fields
     * Used for bulkEditForm subForm requests via ajax.
     *
     * @return Form Currently being edited form
     */
    public function recordEditForm()
    {
        //clone current request : used to figure out what record we are asking
        $request = clone $this->request;
        $recordInfo = $request->shift();

        //shift request till we find the requested field
        while ($recordInfo) {
            if ($unescapedRecordInfo = $this->unEscapeFieldName($recordInfo)) {
                $id = $unescapedRecordInfo['id'];
                $fieldName = $unescapedRecordInfo['name'];

                $action = $request->shift();
                break;
            } else {
                $recordInfo = $request->shift();
            }
        }

        //generate a form with only that requested record's fields
        if ($id) {
            $modelClass = $this->gridField->getModelClass();
            $record = DataObject::get_by_id($modelClass, $id);

            $cmsFields = $record->getCMSFields();
            $recordEditingFields = $this->getRecordEditingFields($record);

            return Form::create(
                $this->gridField,
                'recordEditForm',
                FieldList::create($recordEditingFields),
                FieldList::create()
            );
        }
    }

    private function getRecordEditingFields(DataObject $record)
    {
        $tempForm = Form::create(
            $this,
            'TempEditForm',
            $record->getCMSFields(),
            FieldList::create()
        );

        $tempForm->loadDataFrom($record);
        $fields = $tempForm->Fields();

        $fields = $this->filterRecordEditingFields($fields, $record->ID);

        // Check for any TreeMultiSelectFields and forcefuly set their values
        foreach ($fields as $key => $field) {
            if (is_a($field, TreeMultiselectField::class) && $record->hasMethod($key)) {
                $field->setValue(implode(",", $record->$key()->column('ID')));
            }
        }

        return $fields;
    }

    private function filterRecordEditingFields(FieldList $fields, $id)
    {
        $config = $this->component->getConfig();
        $editableFields = $config['editableFields'];

        // get all dataFields or just the ones allowed in config
        if ($editableFields) {
            $dataFields = [];

            foreach ($editableFields as $fieldName) {
                $dataFields[$fieldName] = $fields->dataFieldByName($fieldName);
            }
        } else {
            $dataFields = $fields->dataFields();
        }

        // escape field names with unique prefix
        foreach ($dataFields as $name => $field) {
            $field->Name = $this->escapeFieldName($id, $name);
            $dataFields[$name] = $field;
        }

        return $dataFields;
    }
}
