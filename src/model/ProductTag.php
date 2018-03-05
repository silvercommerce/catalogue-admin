<?php

namespace SilverCommerce\CatalogueAdmin\Model;

use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * A simple tag that can be used to filter products
 * 
 * @author i-lateral (http://www.i-lateral.com)
 * @package CatalogueAdmin
 */
class ProductTag extends DataObject implements PermissionProvider
{
    
    private static $table_name = 'ProductTag';

    private static $db = [
        'Title'      => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)'
    ];

    /**
     * @var array
     */
    private static $belongs_many_many = [
        'Products' => CatalogueProduct::class
    ];

    private static $default_sort = [
        "Title" => "ASC"
    ];

    private static $summary_fields = [
        "Title" => "Title",
        "Products.Count" => "# or products"
    ];

    public function providePermissions()
    {
        return [
            "CATALOGUE_ADD_TAGS" => [
                'name' => 'Add tags',
                'help' => 'Allow user to add tags to catalogue',
                'category' => 'Catalogue',
                'sort' => 200
            ],
            "CATALOGUE_EDIT_TAGS" => [
                'name' => 'Edit tags',
                'help' => 'Allow user to edit any tags in catalogue',
                'category' => 'Catalogue',
                'sort' => 250
            ],
            "CATALOGUE_DELETE_TAGS" => [
                'name' => 'Delete tags',
                'help' => 'Allow user to delete any tags in catalogue',
                'category' => 'Catalogue',
                'sort' => 300
            ]
        ];
    }

    public function canView($member = null)
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

        return Permission::checkMember(
            $memberID,
            ["ADMIN", "CATALOGUE_ADD_TAGS"]
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
            ["ADMIN", "CATALOGUE_EDIT_TAGS"]
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
            ["ADMIN", "CATALOGUE_DELETE_TAGS"]
        );
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->URLSegment = Convert::raw2url($this->Title);
    }
}