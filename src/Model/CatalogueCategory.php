<?php

namespace SilverCommerce\CatalogueAdmin\Model;

use SilverStripe\ORM\ArrayList;
use SilverStripe\View\SSViewer;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Security\PermissionProvider;
use SilverCommerce\CatalogueAdmin\Helpers\Helper;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_Catalogue;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_CatalogueRelated;

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
     * Human-readable singular name.
     * @var string
     * @config
     */
    private static $singular_name = 'Category';

    /**
     * Human-readable plural name
     * @var string
     * @config
     */
    private static $plural_name = 'Categories';

    /**
     * Description for this object that will get loaded by the website
     * when it comes to creating it for the first time.
     *
     * @var string
     * @config
     */
    private static $description = "A basic product category";

    /**
     * What character shall we use to seperate hierachy elements
     * when rendering a categories hierarchy?
     *
     * @var string
     */
    private static $hierarchy_seperator = "/";

    /**
     * The default class used to extend this object
     *
     * @var string
     */
    private static $default_subclass = \Category::class;

    private static $db = [
        'Title'             => 'Varchar',
        'Content'           => 'HTMLText',
        'Sort'              => 'Int',
        'Disabled'          => 'Boolean' // Now legacy, as switched to versioning
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
        Hierarchy::class,
        Versioned::class . '.versioned'
    ];

    private static $summary_fields = [
        'Title'         => 'Title',
        'Children.Count'=> 'Children',
        'Products.Count'=> 'Products'
    ];

    private static $casting = [
        "MenuTitle"     => "Varchar",
        "AllProducts"   => "ArrayList",
        "Hierarchy"     => "Varchar(255)",
        "FullHierarchy" => "Varchar(255)"
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
     * Return a list of child categories that are not disabled
     *
     * @return DataList
     */
    public function EnabledChildren()
    {
        Deprecation::notice('2.0', 'Disabled status discontinued, use versioning/published instead');

        return $this
            ->Children()
            ->filter("Disabled", 0);
    }

    /**
     * Return a list of products in that category that are not disabled
     *
     * @return DataList
     */
    public function EnabledProducts()
    {
        Deprecation::notice('2.0', 'Disabled status discontinued, use versioning/published instead');

        return $this
            ->Products()
            ->filter("Disabled", 0);
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
     * Get the full hierarchy of the current category (including the
     * currnt department title in the string).
     *
     * @return string
     */
    public function getFullHierarchy()
    {
        $sep = $this->config()->hierarchy_seperator;

        return ($this->ParentID) ? $this->getBreadcrumbs($sep) : $this->Title;
    }

    /**
     * Get the hierarchy of the current category (with the current
     * department removed from the list).
     *
     * @return string
     */
    public function getHierarchy()
    {
        $sep = $this->config()->hierarchy_seperator;

        if ($this->ParentID) {
            $length = strlen("/" . $this->Title);
            $crumbs = $this->getBreadcrumbs($sep);
            return substr($this->getBreadcrumbs($sep), 0, strlen($crumbs) - $length);
        } else {
            return "";
        }
    }



    /**
     * Find and/or create categories based on the provided hierarchy:
     *
     * Eg: Department/Sub Department/Sub Sub Department
     *
     * This method will go through and find, or create categories in the above structure and
     * return an ArrayList of all categories created
     *
     * @param string $hierarchy A list of departments and sub departments, seperated by a forward slash
     *
     * @return ArrayList
     */
    public static function findOrMakeHierarchy($hierarchy)
    {
        $sep = self::config()->hierarchy_seperator;
        $items = explode($sep, $hierarchy);
        $list = ArrayList::create();

        if (count($items) == 0) {
            return $list;
        }

        $top_item = self::findOrMake($items[0]);
        $list->add($top_item);

        for ($x = 1; $x < count($items); $x++) {
            $next_item = self::findOrMake($items[$x], $top_item->ID, true);
            $top_item = $next_item;
            $list->add($top_item);
        }

        return $list;
    }

    /**
     * Find or create a category with the provided info
     *
     * @param string $title The name of the category
     * @param int $parent_id The ID of the parent category
     * @param boolean $use_children When finding from a list, use the parent ID
     */
    protected static function findOrMake(string $title, $parent_id = 0, $use_children = false)
    {
        $list = CatalogueCategory::get();
        $filter = ['Title' => $title];
        if ($use_children) {
            $filter['ParentID'] = $parent_id;
        }
        $item = $list->filter($filter)->first();

        if (empty($item)) {
            $item = CatalogueCategory::create(['Title' => $title]);
            $item->ParentID = $parent_id;
            $item->write();
        }

        return $item;
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

        
        return $template->process($this->customise(ArrayData::create([
            "Pages" => $pages,
            "Unlinked" => $unlinked,
            "Delimiter" => $delimiter,
        ])));
    }

    /**
     * Returns the category in the current stack of the given level.
     * Level(1) will return the category item that we're currently inside, etc.
     */
    public function Level($level)
    {
        $parent = $this;
        $stack = [$parent];
        while ($parent = $parent->Parent) {
            array_unshift($stack, $parent);
        }

        return isset($stack[$level-1]) ? $stack[$level-1] : null;
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
            ->Sort([
                "SortOrder" => "ASC",
                "Title" => "ASC"
            ]);
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
            $sort = [
                "SortOrder" => "ASC",
                "Title" => "ASC"
            ];
        }

        $ids = [$this->ID];
        $ids = array_merge($ids, $this->getDescendantIDList());

        $products = CatalogueProduct::get()
            ->filter("Categories.ID", $ids)
            ->sort($sort);

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
                )->Sort('Sort', 'ASC');
        }
    }

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function ($fields) {
            if (!$this->canEdit()) {
                return;
            }

            // Get a list of available product classes
            $category_classes = Helper::getCreatableClasses(CatalogueCategory::class);

            $fields->removeByName("Sort");
            $fields->removeByName("Products");
            $fields->removeByName("Disabled");
            
            if ($this->exists()) {
                // Ensure that we set the parent ID to the current category
                // when creating a new record
                $fields->addFieldToTab(
                    'Root.Children',
                    GridField::create(
                        "Children",
                        "",
                        CatalogueCategory::get()->filter("ParentID", $this->ID)
                    )->setConfig($child_config = GridFieldConfig_Catalogue::create(
                        CatalogueCategory::class,
                        null,
                        "Sort"
                    ))
                );

                $child_edit = $child_config->getComponentByType('GridFieldDetailForm');

                if ($child_edit) {
                    $self = $this; // PHP 5.3 support - $this can't be used in closures
                    $child_edit->setItemEditFormCallback(
                        function ($form, $itemRequest) use ($self) {
                            $record = $form->getRecord();

                            if (!$record->ID) {
                                $parent_field = $form->Fields()->dataFieldByName("ParentID");
                                $parent_field->setValue($self->ID);
                            }
                        }
                    );
                }

                // Add related products
                $fields->addFieldToTab(
                    'Root.Products',
                    GridField::create(
                        "Products",
                        "",
                        $this->Products()
                    )->setConfig(GridFieldConfig_CatalogueRelated::create(
                        CatalogueProduct::class,
                        null,
                        "SortOrder"
                    ))
                );

                $parent_field = TreeDropdownField::create(
                    "ParentID",
                    _t("CatalogueAdmin.ParentCategory", "Parent Category"),
                    CatalogueCategory::class
                )->setLabelField("Title")
                ->setKeyField("ID");
            } else {
                $parent_field = HiddenField::create(
                    "ParentID",
                    _t("CatalogueAdmin.ParentCategory", "Parent Category")
                );
            }

            $fields->addFieldToTab(
                "Root.Settings",
                DropdownField::create(
                    "ClassName",
                    _t("CatalogueAdmin.CategoryType", "Type of Category"),
                    $category_classes
                )
            );

            $fields->addFieldToTab("Root.Settings", $parent_field);
        });

        return parent::getCMSFields();
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
        $class = $this->config()->default_subclass;
        $categories = CatalogueCategory::get()->filter("ClassName", CatalogueCategory::class);

        // Alter any existing recods that might have the wrong classname
        foreach ($categories as $category) {
            $category->ClassName = $class;
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
