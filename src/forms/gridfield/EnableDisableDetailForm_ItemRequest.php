<?php

namespace SilverCommerce\CatalogueAdmin\Forms\GridField;

use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Security\Security;
use SilverStripe\Core\Convert;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FormAction;

/**
 * Custom detailform for items that can vbe enabled and disabled
 *
 * @author ilateral
 */
class EnableDisableDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest
{

    private static $allowed_actions = array(
        'edit',
        'view',
        'ItemEditForm'
    );

    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();

        if ($form && $this->record->ID !== 0 && $this->record->canEdit()) {
            $fields = $form->Fields();
            $actions = $form->Actions();
        
            // Remove the disabled field
            $fields->removeByName("Disabled");
            
            if ($this->record->isEnabled()) {
                $actions->insertBefore(
                    FormAction::create(
                        'doDisable',
                        _t('Catalogue.Disable', 'Disable')
                    )->setUseButtonTag(true)
                    ->addExtraClass('btn btn-outline-danger btn-hide-outline')
                    ->addExtraClass('action font-icon-cancel-circled'),
                    "action_doDelete"
                );
            } elseif ($this->record->isDisabled()) {
                $actions->insertBefore(
                    FormAction::create(
                        'doEnable',
                        _t('Catalogue.Enable', 'Enable')
                    )->setUseButtonTag(true)
                    ->addExtraClass('btn btn-outline-primary btn-hide-outline')
                    ->addExtraClass('action font-icon-check-mark-circle'),
                    "action_doDelete"
                );
            }
        }
        
        $this->extend("updateItemEditForm", $form);
        
        return $form;
    }


    public function doEnable($data, $form)
    {
        $record = $this->record;

        if ($record && !$record->canEdit()) {
            return Security::permissionFailure($this);
        }

        $form->saveInto($record);
        
        $record->Disabled = 0;
        $record->write();
        $this->gridField->getList()->add($record);

        $message = sprintf(
            _t('Catalogue.Enabled', 'Enabled %s %s'),
            $this->record->singular_name(),
            '"'.Convert::raw2xml($this->record->Title).'"'
        );
        
        $form->sessionMessage($message, 'good');
        return $this->edit(Controller::curr()->getRequest());
    }
    
    
    public function doDisable($data, $form)
    {
        $record = $this->record;

        if ($record && !$record->canEdit()) {
            return Security::permissionFailure($this);
        }

        $form->saveInto($record);
        
        $record->Disabled = 1;
        $record->write();
        $this->gridField->getList()->add($record);

        $message = sprintf(
            _t('Catalogue.Disabled', 'Disabled %s %s'),
            $this->record->singular_name(),
            '"'.Convert::raw2xml($this->record->Title).'"'
        );
        
        $form->sessionMessage($message, 'good');
        return $this->edit(Controller::curr()->getRequest());
    }
}
