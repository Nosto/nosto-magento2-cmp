All notable changes to this project will be documented in this file. This project adheres to Semantic Versioning.

### 2.0.1
* Change the script type to `application/json` for category mapping
* Add possibility to debug the category query via Magento's debug logging 
* Remove redundant Magento's internal full page cache busting logic
* Fix the category page sorting issue when additional / default category sorting is in use 

### 2.0.0
* Update Nosto PHP-SDK dependency to 5.0.0
* Update compatibility with NostoTagging Module 5.0

### 1.2.2
* Fix [PHP SDK](https://github.com/Nosto/nosto-php-sdk) version constraint clash with [Nosto's Magento 2 base module](https://github.com/Nosto/nosto-magento2)

### 1.2.1
* Fix bug related to Nosto sorting option in admin category page

### 1.2.0
* Add tagging for module version
* Add fragment to product urls served by CMP
* Add block for hashed categories mapping
* Add sort order tagging
* Remove attribution from product url
* Remove secondary sorting query 

### 1.1.0
* Enable support for Full Page Cache using cache variation

### 1.0.4
* Fix issue where cache was disabled for every page 

### 1.0.3
* Function `getCmpResult` return only value of `CategoryMerchandisingResult`
* Refactor logging and exception throwing 

### 1.0.2
* Use `Product\Collection` instead of `Fulltext\Collection`
* Add exceptions for easier debug

### 1.0.1
* Update naming convention

### 1.0.0
* Initial implementation of Nosto CMP extension
