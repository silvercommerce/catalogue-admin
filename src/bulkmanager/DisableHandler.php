<?php

namespace SilverCommerce\CatalogueAdmin\BulkManager;

use Exception;
use SilverStripe\Core\Convert;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use Colymba\BulkTools\HTTPBulkToolsResponse;
use Colymba\BulkManager\BulkAction\Handler as GridFieldBulkActionHandler;

/**
 * A {@link GridFieldBulkActionHandler} for bulk marking products
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class DisableHandler extends GridFieldBulkActionHandler
{
    private static $url_segment = 'disable';

    private static $allowed_actions = [
        'disable'
    ];

    private static $url_handlers = [
        "" => "disable"
    ];

    /**
     * Front-end label for this handler's action
     * 
     * @var string
     */
    protected $label = 'Disable';

    protected $buttonClasses = 'font-icon-cancel';

    /**
     * Whether this handler should be called via an XHR from the front-end
     * 
     * @var boolean
     */
    protected $xhr = true;
    
    /**
     * Set to true is this handler will destroy any data.
     * A warning and confirmation will be shown on the front-end.
     * 
     * @var boolean
     */
    protected $destructive = false;

    /**
     * Return i18n localized front-end label
     *
     * @return array
     */
    public function getI18nLabel()
    {
        return _t(__CLASS__ . '.Disable', $this->getLabel());
    }

    public function disable(HTTPRequest $request)
    {
        $response = new HTTPBulkToolsResponse(true, $this->gridField);

        try {
            $ids = [];

            foreach ($this->getRecords() as $record) {
                array_push($ids, $record->ID);
                $record->Disabled = 1;
                $record->write();
                $response->addSuccessRecord($record);
            }

            $response->setMessage(
                _t(
                    __CLASS__ . ".DisabledXRecords",
                    "Disabled {value} records",
                    ['value' => count($ids)]
                )
            );
        } catch (Exception $ex) {
            $response->setStatusCode(500);
            $response->setMessage($ex->getMessage());
        }

        return $response;
    }
}
