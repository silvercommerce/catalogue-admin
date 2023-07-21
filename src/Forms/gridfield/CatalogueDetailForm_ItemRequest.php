<?php

namespace SilverCommerce\CatalogueAdmin\Forms\GridField;

use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;

class CatalogueDetailForm_ItemRequest extends VersionedGridFieldItemRequest
{
    private static $allowed_actions = [
        'edit',
        'view',
        'ItemEditForm'
    ];

    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();
        $record = $this->getRecord();
        $exists = $record->exists();
        $can_create = $record->canEdit();
        $can_edit = $record->canEdit();

        if (!empty($form) && $exists
            && $can_create && $can_edit
        ) {
            $actions = $form->Actions();

            $actions->insertBefore(
                'action_doUnpublish',
                FormAction::create(
                    'doDuplicate',
                    _t('Catalogue.Duplicate', 'Duplicate')
                )->addExtraClass('btn-secondary')
            );
        }

        $this->extend("updateItemEditForm", $form);

        return $form;
    }

    public function doDuplicate($data, $form)
    {
        $record = $this->getRecord();

        // Check permission
        if (!$record->canEdit()) {
            $this->httpError(403, _t(
                __CLASS__ . '.EditPermissionsFailure',
                'It seems you don\'t have the necessary permissions to edit "{ObjectTitle}"',
                ['ObjectTitle' => $record->singular_name()]
            ));
            return null;
        }

        // Save existing from form data
        $this->saveFormIntoRecord($data, $form);

        // Duplicate and update name
        $duplicate = $record->duplicate(true);
        $duplicate->Title = _t(
            'Catalogue.Copy',
            '{title} COPY',
            [ 'title' => $record->Title]
        );
        $duplicate->write();

        // Generate link to original
        $link = '<a href="' . $this->Link('edit') . '">"'
            . htmlspecialchars($record->Title, ENT_QUOTES)
            . '"</a>';
        
        $this->record = $duplicate;

        $message = _t(
            'Catalogue.Duplicated',
            'Duplicated {name} {link}',
            [
                'name' => $duplicate->i18n_singular_name(),
                'link' => $link
            ]
        );

        // Load message
        $form->sessionMessage(
            $message,
            'good',
            ValidationResult::CAST_HTML
        );

        // Redirect after save
        return $this->redirectAfterSave(true);
    }
}
