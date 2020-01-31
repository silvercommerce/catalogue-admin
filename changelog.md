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