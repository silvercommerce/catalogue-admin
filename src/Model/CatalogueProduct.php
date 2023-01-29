<?php

namespace SilverCommerce\CatalogueAdmin\Model;

use SilverStripe\i18n\i18n;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\SSViewer;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Security\Security;
use SilverStripe\TagField\TagField;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injector;
use SilverCommerce\TaxAdmin\Model\TaxRate;
use SilverCommerce\TaxAdmin\Traits\Taxable;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\Security\PermissionProvider;
use SilverCommerce\TaxAdmin\Model\TaxCategory;
use SilverCommerce\CatalogueAdmin\Helpers\Helper;
use Bummzack\SortableFile\Forms\SortableUploadField;
use SilverCommerce\TaxAdmin\Interfaces\TaxableProvider;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_CatalogueRelated;
use SilverCommerce\CatalogueAdmin\Tasks\CatalogueWriteAllItemsTask;

/**
 * Base class for all products stored in the database. The intention is
 * to allow Product objects to be extended in the same way as a more
 * conventional "Page" object.
 *
 * This allows users familier with working with the CMS a common
 * platform for developing ecommerce type functionality.
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class CatalogueProduct extends DataObject implements PermissionProvider, TaxableProvider
{
    use Taxable;
    
    private static $table_name = 'CatalogueProduct';
    
    /**
     * Determines if a product's stock ID will be auto generated if
     * not set.
     *
     * @var bool
     */
    private static $auto_stock_id = true;

    /**
     * Human-readable singular name.
     *
     * @var string
     */
    private static $singular_name = 'Product';

    /**
     * Human-readable plural name
     *
     * @var string
     */
    private static $plural_name = 'Products';
    
    /**
     * Description for this object that will get loaded by the website
     * when it comes to creating it for the first time.
     *
     * @var string
     */
    private static $description = "A standard catalogue product";

    /**
     * When duplicating this item, add this to the end of
     * title and stock ID
     *
     * @var string
     */
    private static $duplicate_suffix = "-copy";

    private static $db = [
        'Title'             => 'Varchar(255)',
        'BasePrice'         => 'Decimal(9,3)',
        'StockID'           => 'Varchar',
        'Content'           => 'HTMLText',
        'ContentSummary'    => 'Text',
        'Weight'            => 'Decimal',
        'Disabled'          => 'Boolean' // Now legacy, as switched to versioning
    ];

    private static $has_one = [
        'TaxRate' => TaxRate::class,
        'TaxCategory' => TaxCategory::class
    ];

    private static $many_many = [
        "Images"            => Image::class,
        "Tags"              => ProductTag::class,
        "RelatedProducts"   => CatalogueProduct::class
    ];

    private static $many_many_extraFields = [
        "Images" => ["SortOrder" => "Int"],
        'RelatedProducts' => ['SortOrder' => 'Int']
    ];

    private static $belongs_many_many = [
        "Categories"    => CatalogueCategory::class
    ];

    private static $owns = [
        "Images"
    ];

    private static $extensions = [
        Versioned::class . '.versioned'
    ];

    private static $casting = [
        "MenuTitle"             => "Varchar",
        "CategoriesList"        => "Varchar",
        "TagsList"              => "Varchar",
        "ImagesList"            => "Varchar",
        "RelatedProductsList"   => "Varchar",
        "CMSThumbnail"          => "Varchar"
    ];

    private static $summary_fields = [
        "CMSThumbnail",
        "ClassName",
        "StockID",
        "Title",
        "NoTaxPrice",
        "TaxPercentage",
        "CategoriesList",
        "TagsList"
    ];

    private static $export_fields = [
        "ID",
        "StockID",
        "ClassName",
        "Title",
        "Content",
        "BasePrice",
        "TaxRateID",
        "TaxCategoryID",
        "Weight",
        "CategoriesList",
        "TagsList",
        "ImagesList",
        "RelatedProductsList"
    ];

    private static $field_labels = [
        "CMSThumbnail"          => "Thumbnail",
        "ClassName"             => "Product",
        "NoTaxPrice"            => "Price",
        "CategoriesList"        => "Categories",
        "TagsList"              => "Tags",
        "ImagesList"            => "Images",
        "RelatedProductsList"   => "Related Products"
    ];

    private static $searchable_fields = [
        "Title",
        "Content",
        "StockID",
        "ClassName"
    ];

    private static $default_sort = "Title ASC";

    private static $cascade_duplicates = [
        'TaxRate',
        'TaxCategory',
        'Images',
        'Tags',
        'RelatedProducts',
        'Categories'
    ];

    /**
     * Is this object enabled?
     *
     * @return Boolean
     */
    public function isEnabled()
    {
        Deprecation::notice('2.0', 'Disabled status discontinued, use versioning/published instead');

        return ($this->Disabled) ? false : true;
    }
    
    /**
     * Is this object disabled?
     *
     * @return Boolean
     */
    public function isDisabled()
    {
        Deprecation::notice('2.0', 'Disabled status discontinued, use versioning/published instead');

        return $this->Disabled;
    }

    /**
     * Return a list of categories that have not been disabled
     *
     * @return DataList
     */
    public function getEnabledCategories()
    {
        Deprecation::notice('2.0', 'Disabled status discontinued, use versioning/published instead');

        return $this
            ->Categories()
            ->exclude('Disabled', true);
    }

    /**
     * Get the default export fields for this object
     *
     * @return array
     */
    public function getExportFields()
    {
        $rawFields = $this->config()->get('export_fields');

        // Merge associative / numeric keys
        $fields = [];
        foreach ($rawFields as $key => $value) {
            if (is_int($key)) {
                $key = $value;
            }
            $fields[$key] = $value;
        }

        // If subsite module installed, add as an export field
        // (as duplicate stock ID's across sites can result in the wrong product being found)
        $subsites_exists = ModuleLoader::inst()->getManifest()->moduleExists('silverstripe/subsites');
        if ($subsites_exists && !in_array('SubsiteID', $fields)) {
            $fields['SubsiteID'] = 'SubsiteID';
        }

        $this->extend("updateExportFields", $fields);

        // Final fail-over, just list ID field
        if (!$fields) {
            $fields['ID'] = 'ID';
        }

        return $fields;
    }

    public function getBasePrice()
    {
        return $this->dbObject('BasePrice')->getValue();
    }

    /**
     * Stub method to get the site config, unless the current class can provide an alternate.
     *
     * @return SiteConfig
     */
    public function getSiteConfig()
    {
        if ($this->hasMethod('alternateSiteConfig')) {
            $altConfig = $this->alternateSiteConfig();
            if ($altConfig) {
                return $altConfig;
            }
        }

        return SiteConfig::current_site_config();
    }

    /**
     * Get should this field automatically show the price including TAX?
     *
     * @return bool
     */
    public function getShowPriceWithTax()
    {
        $show = $this->getSiteConfig()->ShowPriceAndTax;

        $result = $this->filterTaxableExtensionResults(
            $this->extend("updateShowPriceWithTax", $show)
        );

        if (!empty($result)) {
            return (bool)$result;
        }

        return (bool)$show;
    }

    /**
     * Get if this field should add a "Tax String" (EG Includes VAT) to the rendered
     * currency?
     *
     * @return bool|null
     */
    public function getShowTaxString()
    {
        $show = $this->getSiteConfig()->ShowPriceTaxString;

        $result = $this->filterTaxableExtensionResults(
            $this->extend("updateShowTaxString", $show)
        );

        if (!empty($result)) {
            return (bool)$result;
        }

        return (bool)$show;
    }

    /**
     * Return the currently available locale
     *
     * @return string
     */
    public function getLocale()
    {
        return i18n::get_locale();
    }

    /**
     * Return the link for this {@link SimpleProduct} object, with the
     * {@link Director::baseURL()} included.
     *
     * @param string $action Optional controller action (method).
     *  Note: URI encoding of this parameter is applied automatically through template casting,
     *  don't encode the passed parameter.
     *  Please use {@link Controller::join_links()} instead to append GET parameters.
     *
     * @return string
     */
    public function Link($action = null)
    {
        return Controller::join_links(
            Director::baseURL(),
            $this->RelativeLink($action)
        );
    }

    /**
     * Shortcut for the first enabled category assigned to this product
     *
     * @return CaltalogueCategory
     */
    public function Parent()
    {
        return $this
            ->Categories()
            ->first();
    }

    /**
     * Get the absolute URL for this page, including protocol and host.
     *
     * @param string $action See {@link Link()}
     *
     * @return string
     */
    public function AbsoluteLink($action = null)
    {
        if ($this->hasMethod('alternateAbsoluteLink')) {
            return $this->alternateAbsoluteLink($action);
        } else {
            return Director::absoluteURL($this->Link($action));
        }
    }
    
    /**
     * Return the link for this {@link CatalogueProduct}
     *
     * @param string $action See {@link Link()}
     * @return string
     */
    public function RelativeLink($action = null)
    {
        $link = Controller::join_links(
            $this->ID,
            $action
        );

        $this->extend('updateRelativeLink', $link, $action);

        return $link;
    }

    /**
     * We use this to tap into the categories "isSection" setup,
     * essentially adding the product's first category to the list
     *
     * @param $include_parent Include the direct parent of this product
     * @return ArrayList
     */
    public function getAncestors($include_parent = false)
    {
        $ancestors = ArrayList::create();
        $object    = $this->Parent();

        if ($object) {
            if ($include_parent) {
                $ancestors->push($object);
            }

            while ($object = $object->getParent()) {
                $ancestors->push($object);
            }
        }

        $this->extend('updateAncestors', $ancestors, $include_parent);

        return $ancestors;
    }
    
    public function getMenuTitle()
    {
        return $this->Title;
    }

    /**
     * Find a tax rate based on the selected ID, or revert to using the valid tax
     * from the current category
     *
     * @return \SilverCommerce\TaxAdmin\Model\TaxRate
     */
    public function getTaxRate()
    {
        $tax = TaxRate::get()->byID($this->TaxRateID);

        // If no tax explicity set, try to get from category
        if (empty($tax)) {
            $category = TaxCategory::get()->byID($this->TaxCategoryID);

            $tax = (!empty($category)) ? $category->ValidTax() : null ;
        }

        if (empty($tax)) {
            $tax = TaxRate::create();
            $tax->ID = -1;
        }

        return $tax;
    }

    /**
     * Return sorted products related to this product
     *
     * @return ArrayList
     */
    public function SortedRelatedProducts()
    {
        return $this
            ->RelatedProducts()
            ->Sort(
                [
                    "SortOrder" => "ASC",
                    "Title" => "ASC"
                ]
            );
    }

    /**
     * Get a primary image (first image we find)
     * for this product
     *
     * @return Image
     */
    public function PrimaryImage()
    {
        return $this
            ->SortedImages()
            ->first();
    }

    /**
     * Return sorted images, if no images exist, create a new opbject set
     * with a blank product image in it.
     *
     * @return SSList
     */
    public function SortedImages()
    {
        // If this product has images, display them
        if ($this->Images()->exists()) {
            return $this->Images()->Sort('SortOrder');
        }

        $config = SiteConfig::current_site_config();
        $default_image = $config->DefaultProductImage();
        $images = ArrayList::create();
        
        // Try to use default from SiteConfig, if none is available,
        // use a system default
        if ($default_image->exists()) {
            $image = $default_image;
        } else {
            $image = Helper::generate_no_image();
        }

        // Finally return a list with only the default
        $images->add($image);
        return $images;
    }

    /**
     * Return a breadcrumb trail for this product (which accounts for parent
     * categories)
     *
     * @param int $maxDepth The maximum depth to traverse.
     *
     * @return string The breadcrumb trail.
     */
    public function Breadcrumbs($maxDepth = 20)
    {
        $items = [];
        
        $ancestors = $this->getAncestors(true);

        if ($ancestors->exists()) {
            $items[] = $this;

            foreach ($ancestors as $item) {
                $items[] = $item;
            }
        }

        $template = new SSViewer('BreadcrumbsTemplate');

        return $template->process($this->customise(ArrayData::create([
            'Pages' => ArrayList::create(array_reverse($items))
        ])));
    }

    public function getCMSThumbnail()
    {
        return $this
            ->SortedImages()
            ->first()
            ->Pad(50, 50);
    }

    /**
     * Generate a comma seperated list of category names
     * assigned to this product.
     *
     * @return string
     */
    public function getCategoriesList()
    {
        $list = [];
        
        foreach ($this->Categories() as $cat) {
            $list[] = $cat->FullHierarchy;
        }

        return implode(", ", $list);
    }

    /**
     * Generate a comma seperated list of tag names
     * assigned to this product.
     *
     * @return string
     */
    public function getTagsList()
    {
        $list = $this->Tags()->column("Title");
        return implode(", ", $list);
    }

    /**
     * Generate a comma seperated list of image names
     * assigned to this product.
     *
     * @return string
     */
    public function getImagesList()
    {
        $list = $this->Images()->column("Name");
        return implode(", ", $list);
    }

    /**
     * Generate a comma seperated list of related product
     * stock IDs for this product.
     *
     * @return string
     */
    public function getRelatedProductsList()
    {
        $list = $this->RelatedProducts()->column("StockID");
        return implode(", ", $list);
    }

    /**
     * Generate a stock ID, based on the title and ID
     * of this product
     *
     * @return string
     */
    protected function generateStockID()
    {
        $title = "";
            
        foreach (explode(" ", $this->Title) as $string) {
            $string = substr($string, 0, 1);
            $title .= $string;
        }
        
        return $title . "-" . $this->ID;
    }

    public function getCMSFields()
    {
        $self = $this;

        $this->beforeUpdateCMSFields(
            function ($fields) use ($self) {
                // Add field group to add Price and Tax field
                $fields->removeByName([
                    'BasePrice',
                    'TaxCategoryID',
                    'TaxRateID',
                    'Disabled'
                ]);

                $field = FieldGroup::create(
                    $this->getOwner()->dbObject("BasePrice")->scaffoldFormField(''),
                    DropdownField::create('TaxCategoryID', "", TaxCategory::get())
                        ->setEmptyString(
                            _t(self::class . '.SelectTaxCategory', 'Select a Tax Category')
                        ),
                    ReadonlyField::create("PriceOr", "")
                        ->addExtraClass("text-center")
                        ->setValue(_t(self::class . '.OR', ' OR ')),
                    DropdownField::create(
                        'TaxRateID',
                        "",
                        TaxRate::get()
                    )->setEmptyString(
                        _t(self::class . '.SelectTaxRate', 'Select a Tax Rate')
                    )
                )->setName('PriceFields')
                ->setTitle($this->getOwner()->fieldLabel('Price'));

                $fields->addFieldToTab(
                    'Root.Main',
                    $field,
                    'Content'
                );

                $fields->addFieldToTab(
                    "Root.Settings",
                    DropdownField::create(
                        "ClassName",
                        _t("CatalogueAdmin.ProductType", "Type of product"),
                        Helper::getCreatableClasses(self::class)
                    )
                );

                $summary_field = $fields->dataFieldByName('ContentSummary');
                if (!empty($summary_field)) {
                    $fields->removeByName("ContentSummary");
                    $fields->addFieldToTab(
                        'Root.Main',
                        ToggleCompositeField::create(
                            'SummaryFields',
                            _t(
                                "SilverCommerce\CatalogueAdmin.SummaryInfo",
                                "Summary Info"
                            ),
                            [$summary_field]
                        )
                    );
                }

                $stock_field = $fields->dataFieldByName('StockID');
                if (!empty($stock_field)) {
                    $stock_field->setRightTitle(
                        _t("Catalogue.StockIDHelp", "For example, a product SKU")
                    );
                }

                $categories_field = $fields->dataFieldByName('Categories');
                if (!empty($categories_field)) {
                    $fields->removeByName('Categories');
                    $fields->addFieldToTab(
                        "Root.Settings",
                        TreeMultiSelectField::create(
                            'Categories',
                            $this->fieldLabel('Categories'),
                            CatalogueCategory::class
                        )
                    );
                }

                $tags_field = $fields->dataFieldByName('Tags');
                if (!empty($tags_field)) {
                    $fields->removeByName('Tags');
                    $fields->addFieldToTab(
                        "Root.Settings",
                        TagField::create(
                            'Tags',
                            $this->fieldLabel("Tags"),
                            ProductTag::get(),
                            $this->Tags()
                        )->setCanCreate($this->canCreateTags())
                        ->setShouldLazyLoad(true)
                    );
                }

                $images_field = $fields->dataFieldByName('Images');
                if (!empty($images_field)) {
                    $fields->addFieldToTab(
                        'Root.Images',
                        SortableUploadField::create(
                            'Images',
                            $this->fieldLabel('Images')
                        )->setSortColumn('SortOrder')
                    );
                }

                $related_field = $fields->dataFieldByName('RelatedProducts');
                if (!empty($related_field)) {
                    $related_field->setConfig(
                        GridFieldConfig_CatalogueRelated::create(
                            self::class,
                            null,
                            'SortOrder'
                        )
                    );
                }

                $weight_field = $fields->dataFieldByName('Weight');
                if (!empty($weight_field)) {
                    $fields->addFieldToTab(
                        'Root.Settings',
                        $weight_field->setScale(2)
                    );
                }
            }
        );

        return parent::getCMSFields();
    }

    public function getCMSValidator()
    {
        $required = ["Title"];
        
        if (!$this->config()->auto_stock_id) {
            $required[] = "StockID";
        }
        
        return RequiredFields::create($required);
    }
    
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $run_migration = CatalogueWriteAllItemsTask::config()->run_during_dev_build;

        if ($run_migration) {
            $request = Injector::inst()->get(HTTPRequest::class);
            CatalogueWriteAllItemsTask::create()->run($request);
        }
    }

    /**
     * Overwrite default search fields to add an updated dropdown to classname
     *
     * Used by {@link SearchContext}.
     *
     * @param array $_params
     *
     * @return \SilverStripe\Forms\FieldList
     */
    public function scaffoldSearchFields($_params = null)
    {
        $fields = parent::scaffoldSearchFields($_params);

        // Update the classname field if set
        $classname = $fields->dataFieldByName('ClassName');

        if (!empty($classname)) {
            $classname->setSource(Helper::getCreatableClasses(CatalogueProduct::class, false, true));
        }

        return $fields;
    }

    public function providePermissions()
    {
        return [
            "CATALOGUE_ADD_PRODUCTS" => [
                'name' => 'Add products',
                'help' => 'Allow user to add products to catalogue',
                'category' => 'Catalogue',
                'sort' => 50
            ],
            "CATALOGUE_EDIT_PRODUCTS" => [
                'name' => 'Edit products',
                'help' => 'Allow user to edit any product in catalogue',
                'category' => 'Catalogue',
                'sort' => 100
            ],
            "CATALOGUE_DELETE_PRODUCTS" => [
                'name' => 'Delete products',
                'help' => 'Allow user to delete any product in catalogue',
                'category' => 'Catalogue',
                'sort' => 150
            ]
        ];
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if (empty($this->StockID) && $this->config()->auto_stock_id) {
            $this->StockID = $this->generateStockID();
            $this->write();
        }
    }

    public function onBeforeDuplicate()
    {
        $suffix = $this->config()->duplicate_suffix;

        $this->StockID = $this->StockID . $suffix;
        $this->Title = $this->Title . $suffix;
    }

    public function canView($member = null)
    {
        // Is the site locked down via siteconfig?
        return SiteConfig::current_site_config()->canViewPages($member);
    }

    public function canCreate($member = null, $context = [])
    {
        if ($member instanceof Member) {
            $memberID = $member->ID;
        } elseif (is_numeric($member)) {
            $memberID = $member;
        } else {
            $memberID = Member::currentUserID();
        }

        return Permission::checkMember(
            $memberID,
            ["ADMIN", "CATALOGUE_ADD_PRODUCTS"]
        );
    }

    public function canEdit($member = null)
    {
        if ($member instanceof Member) {
            $memberID = $member->ID;
        } elseif (is_numeric($member)) {
            $memberID = $member;
        } else {
            $memberID = Member::currentUserID();
        }

        return Permission::checkMember(
            $memberID,
            ["ADMIN", "CATALOGUE_EDIT_PRODUCTS"]
        );
    }

    public function canDelete($member = null)
    {
        if ($member instanceof Member) {
            $memberID = $member->ID;
        } elseif (is_numeric($member)) {
            $memberID = $member;
        } else {
            $memberID = Member::currentUserID();
        }

        return Permission::checkMember(
            $memberID,
            ["ADMIN", "CATALOGUE_DELETE_PRODUCTS"]
        );
    }

    /**
     * Determine whether user can create new tags.
     *
     * @param null|int|Member $member
     *
     * @return bool
     */
    public function canCreateTags($member = null)
    {
        if (empty($member)) {
            $member = Security::getCurrentUser();
        }

        return Permission::checkMember(
            $member,
            ["ADMIN", "CATALOGUE_ADD_TAGS"]
        );
    }

    /**
     * Returns the page in the current page stack of the given level. Level(1) will return the main menu item that
     * we're currently inside, etc.
     *
     * @param int $level
     * @return SiteTree
     */
    public function Level($level)
    {
        $parent = $this;
        $stack = [$parent];
        while (($parent = $parent->Parent()) && $parent->exists()) {
            array_unshift($stack, $parent);
        }

        return isset($stack[$level-1]) ? $stack[$level-1] : null;
    }
}
