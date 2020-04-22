<?php

namespace SilverCommerce\CatalogueAdmin\Tests;

use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
use SilverStripe\Dev\SapphireTest;

class CatalogueCategoryTest extends SapphireTest
{
    protected static $fixture_file = 'Catalogue.yml';

    public function testGetFullHierarchy()
    {
        $top = $this->objFromFixture(CatalogueCategory::class, 'nested_top');
        $two = $this->objFromFixture(CatalogueCategory::class, 'nested_two');
        $four = $this->objFromFixture(CatalogueCategory::class, 'nested_four');

        $this->assertEquals('Nested Top', $top->getFullHierarchy());
        $this->assertEquals('Nested Top/Nested Two', $two->getFullHierarchy());
        $this->assertEquals('Nested Top/Nested Two/Tested Four', $four->getFullHierarchy());
    }

    public function testGetHierarchy()
    {
        $top = $this->objFromFixture(CatalogueCategory::class, 'nested_top');
        $two = $this->objFromFixture(CatalogueCategory::class, 'nested_two');
        $four = $this->objFromFixture(CatalogueCategory::class, 'nested_four');

        $this->assertEquals('', $top->getHierarchy());
        $this->assertEquals('Nested Two', $two->getHierarchy());
        $this->assertEquals('Nested Two/Tested Four', $four->getHierarchy());
    }

    public function testFindOrMakeHierarchy()
    {
        // Test creating new categories
        $test_string = 'Cat 1/Cat 2/Cat 3';
        $cats = CatalogueCategory::findOrMakeHierarchy($test_string);
        $array = $cats->toArray();
        $cat_one = $array[0];
        $cat_two = $array[1];
        $cat_three = $array[2];

        $this->assertCount(3, $cats);
        $this->assertInstanceOf(CatalogueCategory::class, $cat_one);
        $this->assertInstanceOf(CatalogueCategory::class, $cat_two);
        $this->assertInstanceOf(CatalogueCategory::class, $cat_three);
        $this->assertEquals('Cat 1', $cat_one->Title);
        $this->assertEquals('Cat 2', $cat_two->Title);
        $this->assertEquals('Cat 3', $cat_three->Title);
        $this->assertEquals(0, $cat_one->ParentID);
        $this->assertEquals($cat_one->ID, $cat_two->ParentID);
        $this->assertEquals($cat_two->ID, $cat_three->ParentID);

        // Test creating new categories
        $test_string = 'Nested Top/Nested Two/Nested Four';
        $top = $this->objFromFixture(CatalogueCategory::class, 'nested_top');
        $two = $this->objFromFixture(CatalogueCategory::class, 'nested_two');
        $four = $this->objFromFixture(CatalogueCategory::class, 'nested_four');
        $cats = CatalogueCategory::findOrMakeHierarchy($test_string);
        $array = $cats->toArray();
        $cat_one = $array[0];
        $cat_two = $array[1];
        $cat_three = $array[2];

        $this->assertCount(3, $cats);
        $this->assertInstanceOf(CatalogueCategory::class, $cat_one);
        $this->assertInstanceOf(CatalogueCategory::class, $cat_two);
        $this->assertInstanceOf(CatalogueCategory::class, $cat_three);
        $this->assertEquals('Nested Top', $cat_one->Title);
        $this->assertEquals('Nested Two', $cat_two->Title);
        $this->assertEquals('Nested Four', $cat_three->Title);
        $this->assertEquals(0, $cat_one->ParentID);
        $this->assertEquals($cat_two->ParentID, $two->ParentID);
        $this->assertEquals($cat_three->ParentID, $four->ParentID);
        $this->assertEquals($cat_one->ID, $top->ID);
        $this->assertEquals($cat_two->ID, $two->ID);
        $this->assertEquals($cat_three->ID, $four->ID);
    }
}
