# Installing the Cataloge module

You need to install this module via composer:

    # composer require silvercommerce/catalogue-admin


## Add a "Product" and "Category" objects and controllers

The catalogue module works in a similar way to the CMS module. Once
installed you will need to add a "Product" and a "Category" (that extend
CatalogueProduct and CatalogueCategory) object to your "mysite" folder.

For example:

    /projectroot/mysite/code/Product.php
    
    <?php

    use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
    
    class Product extends CatalogueProduct {
    
      private static $db = array(
          "StockLevel" => "Int"
      );
    
    }
    
    /projectroot/mysite/code/Category.php
    
    <?php
    
    use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
    
    class Category extends CatalogueCategory {
    
      private static $has_one = array(
          "Image" => "Image"
      );
    
    }
    
**Note** You will need to add this in order to add a product through the
admin.

Once you have done this, you also need to add a Product_Controller and
Catagory_Controller object to your mysite folder that extend
CatalogueProductController and CatalogueCategoryController, EG:

    /projectroot/mysite/code/Product_Controller.php
    
    <?php

    use SilverCommerce\CatalogueFrontend\Control\CatalogueController;
    
    class Product_Controller extends CatalogueProductController {    
    
        public function index() {
            // Some stuff happens here
            return parent::index();
        }
    
    }
    
    /projectroot/mysite/code/Category_Controller.php
    
    <?php

    use SilverCommerce\CatalogueFrontend\Control\CatalogueController;
    
    class Category_Controller extends CatalogueCategoryController {
        
        public function index() {
            // Some stuff happens here
            return parent::index();
        }
    }


## Setting up your catalogue

Once you have installed the module, you can begine setting up products
and categories. To do this, log into your admin, you should now see a
"Catalogue" tab to the left. Click this.

Once in the Catalogue admin, you will see "Products" and "Categories" in
the top right. From here you can fairly easily add new Products and 
Categories.

### Adding nested Categories

This module supports a hierachy for categories. To add "Sub categories"
you must select the category you would like to add children to, then when
the category has loaded, select the "Children" tab. Now you can add child
categories.