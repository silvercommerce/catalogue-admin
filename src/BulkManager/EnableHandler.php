<?php

namespace SilverCommerce\CatalogueAdmin\BulkManager;

use Exception;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\Deprecation;
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
class EnableHandler extends GridFieldBulkActionHandler
{
    private static $url_segment = 'enable';

    private static $allowed_actions = [
        'enable'
    ];

    private static $url_handlers = [
        "" => "enable"
    ];

    /**
     * Front-end label for this handler's action
     *
     * @var string
     */
    protected $label = 'Enable';

    protected $buttonClasses = 'font-icon-tick';

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
        return _t(__CLASS__ . '.Enable', $this->getLabel());
    }

    public function enable(HTTPRequest $request)
    {
        Deprecation::notice('2.0', 'Disabled status has been enabled in favour of product versioning');

        $response = new HTTPBulkToolsResponse(true, $this->gridField);

        try {
            $ids = [];

            foreach ($this->getRecords() as $record) {
                array_push($ids, $record->ID);
                $record->Disabled = 0;
                $record->write();
                $response->addSuccessRecord($record);
            }

            $response->setMessage(
                _t(
                    __CLASS__ . ".EnabledXRecords",
                    "Enabled {value} records",
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
