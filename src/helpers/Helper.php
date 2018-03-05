<?php

namespace SilverCommerce\CatalogueAdmin\Helpers;

use ReflectionClass;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\View\ViewableData;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;

/**
 * Simple helper class to provide common functions across
 * all libraries
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class Helper extends ViewableData
{
    /**
     * Template names to be removed from the default template list 
     * 
     * @var array
     * @config
     */
    private static $classes_to_remove = [
        "Object",
        "ViewableData",
        "DataObject",
        "CatalogueProduct",
        "CatalogueCategory"
    ];

    /**
     * Get a list of templates for rendering
     *
     * @param $classname ClassName to find tempaltes for
     * @return array Array of classnames
     */
    public static function get_templates_for_class($classname)
    {
        $classes = ClassInfo::ancestry($classname);
        $classes = array_reverse($classes);
        $remove_classes = self::config()->classes_to_remove;
        $return = array();

        array_push($classes, "Catalogue", "Page");

        foreach ($classes as $class) {
            if (!in_array($class, $remove_classes)) {
                $return[] = $class;
            }
        }
        
        return $return;
    }

    /**
     * Copy the default no product image from this module and then
     * add a new image to the DB.
     * 
     * Returns the new image, so it can be assigned
     */
    public static function generate_no_image()
    {
        // See if the image is already in the DB
        $no_image = "no-image.png";
        $image = File::find($no_image);

        // If not, create new record
        if (!isset($image)) {
            $reflector = new ReflectionClass(self::class);
            $curr_file = dirname($reflector->getFileName());
            $curr_file = str_replace(
                "src/helpers",
                "client/dist/images/no-image.png",
                $curr_file
            );

            $config = array(
                'conflict' => AssetStore::CONFLICT_OVERWRITE,
                'visibility' => AssetStore::VISIBILITY_PUBLIC
            );
            
            $image = Injector::inst()->create(Image::class);
            $image->setFromLocalFile(
                $curr_file,
                $no_image,
                null,
                null,
                $config
            );
            $image->write();
            $image->publishSingle();
        }

        return $image;
    }
}