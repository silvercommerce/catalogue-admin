<?php

namespace SilverCommerce\CatalogueAdmin\Validator;

use LogicException;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;

/**
 * Commerce Product Validator
 *
 * Provide required fields for a SilverCommerce product
 * and also ensure that the StockID is not duplicated
 * 
 * Additional required fields can be set via config API, eg.
 * <code>
 * SilverCommerce\CatalogueAdmin\Validator\ProductValidator:
 *   custom_required:
 *     - MyCustomField
 * </code>
 */
class ProductValidator extends RequiredFields
{
    private static $custom_required = [
        "Title",
        "StockID"
    ];

    protected $classname = CatalogueProduct::class;

    public function __construct()
    {
        $required = func_get_args();

        if (isset($required[0]) && is_array($required[0])) {
            $required = $required[0];
        }

        // check for config API values and merge them in
        $config = $this->config()->custom_required;
        if (is_array($config)) {
            $required = array_merge($required, $config);
        }

        parent::__construct(array_unique($required ?? []));
    }

    /**
     * Check if the submitted data is valid and that the.
     * stock ID doesn't already exist.
     *
     * @param array $data
     * @return bool TRUE if data is valid, otherwise FALSE
     */
    public function php($data)
    {
        $valid = parent::php($data);
        $controller = $this->form->getController();
        $classname = $this->getClassName();
        $stockid = null;
        $id = 0;

        if (isset($data['ID'])) {
            $id = $data['ID'];
        }

        if (empty($id) && !empty($controller)
            && $controller instanceof GridFieldDetailForm_ItemRequest
            && !empty($controller->getRecord())
        ) {
            $id = $controller->getRecord()->ID;
        }

        if (isset($data['StockID'])) {
            $stockid = $data['StockID'];
        }

        $existing = $classname::get()->find('StockID', $stockid);

        if (!empty($existing) && $existing->ID != $id) {
            $this->validationError(
                'StockID',
                _t(
                    __CLASS__ . '.ValidationExistingStockID',
                    'This StockID is already in use on product "{productname}"',
                    ['productname' => $existing->Title]
                )
            );
        }

        // Execute the validators on the extensions
        $results = $this->extend('updatePHP', $data, $this->form);
        $results[] = $valid;
        return min($results);
    }

    public function getClassName(): string
    {
        return $this->classname;
    }

    public function setClassName(string $classname): self 
    {
        $this->classname = $classname;
        return $this;
    }
}
