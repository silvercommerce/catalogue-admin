<?php

namespace SilverCommerce\CatalogueAdmin\Forms\GridField;

use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;

class AddNewMultiClass extends GridFieldAddNewMultiClass
{
    /**
     * @var string
     */
    protected $itemRequestClass = AddNewMultiClassHandler::class;
}
