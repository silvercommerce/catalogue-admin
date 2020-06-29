# Log of changes for Orders Admin module

## 1.0.0

* First initial release

## 1.0.1

* Fixed bug causing products to have tax added twice.

## 1.0.2

* Re-enable disable/enable buttons on Products / Categories 

## 1.0.3

* Use `TaxAdmin::MathsHelper()` to calculate product tax

## 1.0.4

* Fix tax rate export column

## 1.0.5

* Improved adding of import button

## 1.1.0

* Switch to using `Taxable` for price/tax calculations

## 1.1.1

* Ensure CMS field modifications only apply if the field exists

## 1.2.0

* Fix issue where sub categories are not automatically linked to parents
* Update gridfields to use action menus
* Remove custom item request class for existing categories

## 1.2.1

* made export_fields multidimensional on CatalogueProduct

## 1.2.2

* Fix error causing categories to become unlinked when bulk edited

## 1.3.0

* Add better hierarchical support for importing categorys
* Simplify and improve product import/export process

## 1.3.1

* Simplify CatalogueAdmin
* Update to use `getExportFields` on CatalogueAdmin Products

## 1.3.2

* Fix unit tests

## 1.3.3

* Simplify find or make query and fix issue that can occure when accessing `Children`

## 1.3.4

* Allow adding of base classname to the create product dropdown