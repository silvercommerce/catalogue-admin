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

        if (!empty($form) && $exists && $can_create && $can_edit) {
            $actions = $form->Actions();

            $actions->addFieldsToTab(
                'RightGroup',
                FormAction::create(
                    'doDuplicate',
                    _t('Catalogue.Duplicate', 'Duplicate')
                )->setUseButtonTag(true)
                ->addExtraClass('btn btn-outline-primary btn-hide-outline')
                ->addExtraClass('action font-icon-page-multiple')
            );
        }

        $this->extend("updateItemEditForm", $form);

        return $form;
    }

    public function doDuplicate($data, $form)
    {
        $record = $this->getRecord();
        $can_create = $record->canEdit();
        $can_edit = $record->canEdit();

        // Check permission
        if (!$can_create || !$can_edit) {
            $this->httpError(403, _t(
                'Catalogue.DuplicatePermissionsFailure',
                'You do not have permission to duplicate "{ObjectTitle}"',
                ['ObjectTitle' => $record->singular_name()]
            ));
            return null;
        }

        $duplicate = $record->duplicate();

        $link = '<a href="' . $this->Link('edit') . '">"'
            . htmlspecialchars($this->record->Title, ENT_QUOTES)
            . '"</a>';
        $message = _t(
            'Catalogue.Duplicated',
            'Duplicated {name} {link}',
            [
                'name' => $duplicate->i18n_singular_name(),
                'link' => $link
            ]
        );

        $form->sessionMessage(
            $message,
            'good',
            ValidationResult::CAST_HTML
        );

        // Changes to the record properties might've excluded the record from
        // a filtered list, so return back to the main view if it can't be found
        $controller = $this->getToplevelController();
        $url = $controller->getRequest()->getURL();
        $noActionURL = $controller->removeAction($url);
        $controller->getRequest()->addHeader('X-Pjax', 'Content');
        return $controller->redirect($noActionURL, 302);
    }
}
