<?php

namespace SilverCommerce\CatalogueAdmin\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ArrayData;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_Catalogue;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_CatalogueRelated;
use SilverStripe\Assets\Image;
use SilverCommerce\CatalogueAdmin\Catalogue;
use SilverStripe\Core\Convert;
use SilverCommerce\TaxAdmin\Model\TaxCategory;
use SilverCommerce\TaxAdmin\Helpers\MathsHelper;
use Catagory;
use Product;

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
class CatalogueProduct extends DataObject implements PermissionProvider
{
    
    private static $table_name = 'CatalogueProduct';
    
    /**
     * Determines if a product's stock ID will be auto generated if
     * not set.
     * 
     * @config
     */
    private static $auto_stock_id = true;
    
    /**
     * Description for this object that will get loaded by the website
     * when it comes to creating it for the first time.
     * 
     * @var string
     * @config
     */
    private static $description = "A standard catalogue product";
    
    private static $db = [
        "Title"             => "Varchar(255)",
        "StockID"           => "Varchar",
        "BasePrice"         => "Currency",
        "Content"           => "HTMLText",
        "Disabled"          => "Boolean"
    ];
    
    private static $has_one = [
        "TaxCategory"       => TaxCategory::class
    ];

    private static $many_many = [
        "Images"            => Image::class,
        "RelatedProducts"   => CatalogueProduct::class
    ];

    private static $many_many_extraFields = [
        "Images" => ["SortOrder" => "Int"],
        'RelatedProducts' => ['SortOrder' => 'Int']
    ];

    private static $belongs_many_many = [
        "Categories"    => CatalogueCategory::class
    ];

    private static $casting = [
        "MenuTitle"         => "Varchar",
        "CategoriesList"    => "Varchar",
        "CMSThumbnail"      => "Varchar",
        "Price"             => "Currency",
        "TaxRate"           => "Decimal",
        "TaxAmount"         => "Currency",
        "PriceAndTax"       => "Currency",
        "TaxString"         => "Varchar",
        "IncludesTax"       => "Boolean"
    ];

    private static $summary_fields = [
        "CMSThumbnail"  => "Thumbnail",
        "ClassName"     => "Product",
        "StockID"       => "StockID",
        "Title"         => "Title",
        "BasePrice"     => "Price",
        "TaxRate"       => "Tax Percent",
        "CategoriesList"=> "Categories",
        "Disabled"      => "Disabled"
    ];

    private static $searchable_fields = [
        "Title",
        "Content",
        "StockID"
    ];

    private static $default_sort = [
        "Title" => "ASC"
    ];
    
    /**
     * Is this object enabled?
     * 
     * @return Boolean
     */
    public function isEnabled()
    {
        return ($this->Disabled) ? false : true;
    }
    
    /**
     * Is this object disabled?
     * 
     * @return Boolean
     */
    public function isDisabled()
    {
        return $this->Disabled;
    }
    
    /**
     * Method that allows us to define in templates if we should show
     * price including tax, or excluding tax
     * 
     * @return boolean
     */
    public function getIncludesTax()
    {
        $config = SiteConfig::current_site_config();
        return $config->ShowPriceAndTax;
    }
    
    /**
     * Get a final price for this product. We make this a method so that
     * we can tap into extensions and allow third party modules to alter
     * this (to add items such as tax, bulk pricing, etc).
     *
     * @return Float
     */
    public function getPrice()
    {
        $price = $this->BasePrice;
        $this->extend("updatePrice", $price);

        return $price;
    }
    
    /**
     * Get the percentage amount of tax applied to this item
     *
     * @return Decimal
     */
    public function getTaxRate()
    {
        $rate = 0;
        $cat = $this->TaxCategory();

        if (!$cat->exists() || !$cat->Rates()->exists()) {
            $config = SiteConfig::current_site_config();
            $cat = $config
                ->TaxCategories()
                ->sort("Default", "DESC")
                ->first();
        }

        if ($cat->exists() && $cat->Rates()->exists()) {
            $rate = $cat->Rates()->first()->Rate;
        }

        $this->extend("updateTaxRate", $rate);

        return $rate;
    }

    /**
     * Get a final tax amount for this product. You can extend this
     * method using "UpdateTax" allowing third party modules to alter
     * tax amounts dynamically.
     * 
     * @return Float
     */
    public function getTaxAmount($decimal_size = null)
    {
        $tax = ($this->BasePrice / 100) * $this->TaxRate;
        $this->extend("updateTaxAmount", $tax);
        
        return $tax;
    }
    
    /**
     * Get the final price of this product, including tax (if any)
     *
     * @return Float
     */
    public function getPriceAndTax()
    {
        $price = $this->Price + $this->TaxAmount;
        $this->extend("updatePriceAndTax", $price);
        
        return $price;
    }
    
    /**
     * Generate a string to go with the the product price. We can
     * overwrite the wording of this by using Silverstripes language
     * files
     *
     * @return String
     */
    public function getTaxString()
    {
        if ($this->TaxRate && $this->IncludesTax) {
            $return = _t("CatalogueAdmin.TaxIncludes", "Includes") . " " . $this->TaxRate()->Title;
        } elseif ($this->TaxRate && !$this->IncludesTax) {
            $return = _t("CatalogueAdmin.TaxExcludes", "Excludes") . " " . $this->TaxRate()->Title;
        } else {
            $return = "";
        }
        
        return $return;
    }

    /**
	 * Stub method to get the site config, unless the current class can provide an alternate.
	 *
	 * @return SiteConfig
	 */
    public function getSiteConfig()
    {
		if($this->hasMethod('alternateSiteConfig')) {
			$altConfig = $this->alternateSiteConfig();
			if($altConfig) return $altConfig;
		}

		return SiteConfig::current_site_config();
	}
    
    
    /**
     * Return the link for this {@link SimpleProduct} object, with the
     * {@link Director::baseURL()} included.
     *
     * @param string $action Optional controller action (method). 
     *  Note: URI encoding of this parameter is applied automatically through template casting,
     *  don't encode the passed parameter.
     *  Please use {@link Controller::join_links()} instead to append GET parameters.
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
     * Get the absolute URL for this page, including protocol and host.
     *
     * @param string $action See {@link Link()}
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
	 * Return the link for this {@link Product}
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
        $object    = $this->Categories()->first();

        if($object) {
            if($include_parent) $ancestors->push($object);

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
     * Return sorted products related to this product
     *
     * @return ArrayList
     */
    public function SortedRelatedProducts()
    {
        return $this
            ->RelatedProducts()
            ->Sort([
                "SortOrder" => "ASC",
                "Title" => "ASC"
            ]);
    }

    /**
     * Return sorted images, if no images exist, create a new opbject set
     * with a blank product image in it.
     *
     * @return SSList
     */
    public function SortedImages()
    {
        $config = SiteConfig::current_site_config();
        $default_image = SiteConfig::current_site_config()->DefaultProductImage();
        
        if ($this->Images()->exists()) {
            $images = $this->Images()->Sort('SortOrder');
        } elseif ($default_image->exists()) {
            $default_image = $default_image;
            $images = ArrayList::create();
            $images->add($default_image);
        } else {
            $no_image = $this->find_or_create_no_image();
            
            $images = ArrayList::create();
            
            $default_image = Image::create();
            $default_image->ID = -1;
            $default_image->Title = "No Image Available";
            $default_image->FileName = $no_image;
            
            $images->add($default_image);
        }

        return $images;
    }

    /**
     * If we have no image set, and none in the @link SiteConfig
     * then use our template no image
     *
     * @return void
     */
    private function find_or_create_no_image()
    {
        $no_image = "assets/no-image.png";
        $no_image_path = Controller::join_links(
            BASE_PATH,
            $no_image
        );

        // if no-image does not exist, copy to the assets folder
        if (file_exists($no_image_path)) {
            return $no_image_path;
        } else {
            $reflector = new \ReflectionClass(CatalogueProduct::class);
            $curr_file = dirname($reflector->getFileName());

            $curr_file = str_replace(
                "src/model",
                "client/dist/images/no-image.png",
                $curr_file
            );
            
            if (copy($curr_file, $no_image_path)) {
                return $no_image_path;
            } else {
                return "";
            }
        }
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
        $items = array();
        
        $ancestors = $this->getAncestors(true);

        if($ancestors->exists()) {
            $items[] = $this;

            foreach($ancestors as $item) {
                $items[] = $item;
            }
        }

        $template = new SSViewer('BreadcrumbsTemplate');

        return $template->process($this->customise(ArrayData::create(array(
            'Pages' => ArrayList::create(array_reverse($items))
        ))));
    }

    public function getCMSThumbnail()
    {
        return $this
            ->SortedImages()
            ->first()
            ->Pad(50, 50);
    }

    public function getCategoriesList()
    {
        $list = $this->Categories()->column("Title");
        return implode(", ", $list);
    }

    public function getCMSFields()
    {
        // Get a list of available product classes
        $classnames = array_values(ClassInfo::subclassesFor("Product"));
        $product_types = array();
        $config = SiteConfig::current_site_config();

        foreach ($classnames as $classname) {
            $instance = singleton($classname);
            $product_types[$classname] = $instance->i18n_singular_name();
        }

        $fields = FieldList::create(
            TabSet::create(
                "Root",
                // Main Tab Fields
                Tab::create(
                    'Main',
                    TextField::create("Title"),
                    CurrencyField::create("BasePrice"),
                    DropdownField::create(
                        "TaxCategoryID",
                        _t("CatalogueAdmin.Tax", "Tax"),
                        $config->TaxCategories()->map()
                    )->setEmptyString(_t("CatalogueAdmin.None", "None")),
                    TextField::create("StockID")
                        ->setRightTitle(_t("Catalogue.StockIDHelp", "For example, a product SKU")),
                    HTMLEditorField::create('Content')
                ),
                // Settings fields
                Tab::create(
                    'Settings',
                    DropdownField::create(
                        "ClassName",
                        _t("CatalogueAdmin.ProductType", "Type of product"),
                        $product_types
                    ),
                    TreeMultiSelectField::create(
                        "Categories",
                        null,
                        CatalogueCategory::class
                    )
                )
            )
        );

        if ($this->ID) {
            $fields->addFieldToTab(
                'Root.Images',
                UploadField::create(
                    'Images',
                    $this->fieldLabel('Images'),
                    $this->Images()
                )
            );

            $fields->addFieldToTab(
                'Root.Related',
                GridField::create(
                    'RelatedProducts',
                    "",
                    $this->RelatedProducts()
                )->setConfig(new GridFieldConfig_CatalogueRelated(
                    Product::class,
                    null,
                    'SortOrder'
                ))
            );
        }

        $this->extend('updateCMSFields', $fields);

        return $fields;
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
        
        $records = CatalogueProduct::get()
            ->filter("ClassName", "CatalogueProduct");
        
        if ($records->exists()) {
            // Alter any existing recods that might have the wrong classname
            foreach ($records as $product) {
                $product->ClassName = "Product";
                $product->write();
            }
            DB::alteration_message("Updated {$records->count()} Product records", 'obsolete');
        }
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

    public function canView($member = null, $context = [])
    {
        return true;
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

        if ($memberID && Permission::checkMember($memberID, array("ADMIN", "CATALOGUE_ADD_PRODUCTS"))) {
            return true;
        } elseif ($memberID && $memberID == $this->CustomerID) {
            return true;
        }

        return true;
    }

    public function canEdit($member = null, $context = [])
    {
        if ($member instanceof Member) {
            $memberID = $member->ID;
        } elseif (is_numeric($member)) {
            $memberID = $member;
        } else {
            $memberID = Member::currentUserID();
        }

        if ($memberID && Permission::checkMember($memberID, array("ADMIN", "CATALOGUE_EDIT_PRODUCTS"))) {
            return true;
        } elseif ($memberID && $memberID == $this->CustomerID) {
            return true;
        }

        return false;
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

        if ($memberID && Permission::checkMember($memberID, array("ADMIN", "CATALOGUE_DELETE_PRODUCTS"))) {
            return true;
        } elseif ($memberID && $memberID == $this->CustomerID) {
            return true;
        }

        return false;
    }
}
