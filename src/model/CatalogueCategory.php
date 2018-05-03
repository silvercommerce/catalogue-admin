<?php

namespace SilverCommerce\CatalogueAdmin\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\Hierarchy\Hierarchy;
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
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Core\Convert;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_Catalogue;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_CatalogueRelated;
use SilverCommerce\CatalogueAdmin\Catalogue;
use \Category;

/**
 * Base class for all product categories stored in the database. The
 * intention is to allow category objects to be extended in the same way
 * as a more conventional "Page" object.
 * 
 * This allows users familier with working with the CMS a common
 * platform for developing ecommerce type functionality.
 * 
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class CatalogueCategory extends DataObject implements PermissionProvider
{

    private static $table_name = 'CatalogueCategory';

    /**
     * Description for this object that will get loaded by the website
     * when it comes to creating it for the first time.
     * 
     * @var string
     * @config
     */
    private static $description = "A basic product category";

    private static $db = [
        "Title"             => "Varchar",
        "Content"           => "HTMLText",
        "Sort"              => "Int",
        "Disabled"          => "Boolean"
    ];

    private static $has_one = [
        'Parent'        => CatalogueCategory::class
    ];

    private static $many_many = [
        'Products'      => CatalogueProduct::class
    ];

    private static $many_many_extraFields = [
        'Products' => ['SortOrder' => 'Int']
    ];

    private static $extensions = [
        Hierarchy::class
    ];

    private static $summary_fields = [
        'Title'         => 'Title',
        'Disabled'      => 'Disabled'
    ];

    private static $casting = [
        "MenuTitle"     => "Varchar",
        "AllProducts"   => "ArrayList"
    ];

    private static $default_sort = [
        "Sort" => "ASC"
    ];

    private static $searchable_fields = [
        "Title",
        "Content"
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
	 * Stub method to get the site config, unless the current class can provide an alternate.
	 *
	 * @return SiteConfig
	 */
	public function getSiteConfig(){

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
	 * Return the link for this {@link Category}
	 *
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

    public function getMenuTitle()
    {
        return $this->Title;
    }

    /**
     * Return a breadcrumb trail for this product (which accounts for parent
     * categories)
     *
     * @param int $maxDepth The maximum depth to traverse.
     * @param boolean $unlinked Whether to link page titles.
     * @param boolean|string $stopAtType ClassName to stop the upwards traversal.
     * @param boolean $showHidden Include pages marked with the attribute ShowInMenus = 0
     * @return string The breadcrumb trail.
     */
    public function Breadcrumbs($maxDepth = 20, $unlinked = false, $stopAtType = false, $showHidden = false, $delimiter = '&raquo;')
    {
        $page = $this;
        $pages = [];

        while ($page
            && $page->exists()
            && (!$maxDepth || count($pages) < $maxDepth)
            && (!$stopAtType || $page->ClassName != $stopAtPageType)
        ) {
            $pages[] = $page;
            $page = $page->Parent();
        }

        $pages = ArrayList::create(array_reverse($pages));
        $template = SSViewer::create('BreadcrumbsTemplate');

        
        return $template->process($this->customise(new ArrayData(array(
            "Pages" => $pages,
            "Unlinked" => $unlinked,
            "Delimiter" => $delimiter,
        ))));
    }

    /**
     * Returns the category in the current stack of the given level.
     * Level(1) will return the category item that we're currently inside, etc.
     */
    public function Level($level)
    {
        $parent = $this;
        $stack = array($parent);
        while ($parent = $parent->Parent) {
            array_unshift($stack, $parent);
        }

        return isset($stack[$level-1]) ? $stack[$level-1] : null;
    }

    /**
     * Return a list of child categories that are not disabled
     *
     * @return ArrayList
     */
    public function EnabledChildren()
    {
        return $this
            ->Children()
            ->filter("Disabled", 0);
    }

    /**
     * Return a list of products in that category that are not disabled
     *
     * @return ArrayList
     */
    public function EnabledProducts()
    {
        return $this
            ->Products()
            ->filter("Disabled", 0);
    }

    /**
     * Return sorted products in thsi category that are enabled
     *
     * @return ArrayList
     */
    public function SortedProducts()
    {
        return $this
            ->EnabledProducts()
            ->Sort(array(
                "SortOrder" => "ASC",
                "Title" => "ASC"
            ));
    }

    /**
     * Get a list of all products from this category and it's children
     * categories.
     *
     * @return ArrayList
     */
    public function AllProducts($sort = [])
    {
        // Setup the default sort for our products
        if (count($sort) == 0) {
            $sort = array(
                "SortOrder" => "ASC",
                "Title" => "ASC"
            );
        }
        
        $ids = array($this->ID);
        $ids = array_merge($ids, $this->getDescendantIDList());

        $products = CatalogueProduct::get()
            ->filter(array(
                "Categories.ID" => $ids,
                "Disabled" => 0
            ))->sort($sort);

        return $products;
    }

    /**
     * Get a list of all tags on products within this category
     * 
     * @return SSList|null
     */
    public function AllTags()
    {
        $products = $this->AllProducts();

        if ($products->exists()) {
            return ProductTag::get()
                ->filter(
                    "Products.ID",
                    $products->column("ID")
                );
        }
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Get a list of available product classes
        $classnames = array_values(ClassInfo::subclassesFor(Category::class));
        $category_types = array();

        foreach ($classnames as $classname) {
            $instance = singleton($classname);
            $category_types[$classname] = $instance->i18n_singular_name();
        }

        $fields->removeByName("Sort");
        $fields->removeByName("Disabled");
        $fields->removeByName("Products");
        
        if ($this->exists()) {

            // Ensure that we set the parent ID to the current category
            // when creating a new record 
            $fields->addFieldToTab(
                'Root.Children',
                GridField::create(
                    "Children",
                    "",
                    Category::get()->filter("ParentID", $this->ID)
                )->setConfig($child_config = new GridFieldConfig_Catalogue(
                    Category::class,
                    null,
                    "Sort"
                ))
            );

            $child_edit = $child_config->getComponentByType('GridFieldDetailForm');

            if ($child_edit) {
                $self = $this; // PHP 5.3 support - $this can't be used in closures
                $child_edit->setItemEditFormCallback(function($form, $itemRequest) use ($self) {
                    $record = $form->getRecord();

                    if (!$record->ID) {
                        $parent_field = $form->Fields()->dataFieldByName("ParentID");
                        $parent_field->setValue($self->ID);
                    }
                });
            }

            // Add related products
            $fields->addFieldToTab(
                'Root.Products',
                GridField::create(
                    "Products",
                    "",
                    $this->Products()
                )->setConfig(new GridFieldConfig_CatalogueRelated(
                    Product::class,
                    null,
                    "SortOrder"
                ))
            );
        }

        $fields->addFieldToTab(
            "Root.Settings",
            DropdownField::create(
                "ClassName",
                _t("CatalogueAdmin.CategoryType", "Type of Category"),
                $category_types
            )
        );

        if ($this->exists()) {
            $fields->addFieldToTab(
                "Root.Settings",
                TreeDropdownField::create(
                    "ParentID",
                    _t("CatalogueAdmin.ParentCategory", "Parent Category"),
                    CatalogueCategory::class
                )->setLabelField("Title")
                ->setKeyField("ID")
            );
        }
        
        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        if ($this->Children()) {
            foreach ($this->Children() as $child) {
                $child->delete();
            }
        }
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        
        // Alter any existing recods that might have the wrong classname
        foreach (CatalogueCategory::get()->filter("ClassName", CatalogueCategory::class) as $category) {
            $category->ClassName = "Category";
            $category->write();
        }
    }

    public function providePermissions()
    {
        return [
            "CATALOGUE_ADD_CATEGORIES" => [
                'name' => 'Add categories',
                'help' => 'Allow user to add categories to catalogue',
                'category' => 'Catalogue',
                'sort' => 50
            ],
            "CATALOGUE_EDIT_CATEGORIES" => [
                'name' => 'Edit categories',
                'help' => 'Allow user to edit any categories in catalogue',
                'category' => 'Catalogue',
                'sort' => 100
            ],
            "CATALOGUE_DELETE_CATEGORIES" => [
                'name' => 'Delete categories',
                'help' => 'Allow user to delete any categories in catalogue',
                'category' => 'Catalogue',
                'sort' => 150
            ]
        ];
    }

    public function canView($member = null, $context = [])
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
            ["ADMIN", "CATALOGUE_ADD_CATEGORIES"]
        );
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

        return Permission::checkMember(
            $memberID,
            ["ADMIN", "CATALOGUE_EDIT_CATEGORIES"]
        );
    }

    public function canDelete($member = null, $context = [])
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
            ["ADMIN", "CATALOGUE_DELETE_CATEGORIES"]
        );
    }
}