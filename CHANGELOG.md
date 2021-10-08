All notable changes to this project will be documented in this file. This project adheres to Semantic Versioning.

### 3.3.0-rc5 
* Fix facet mapping to Nosto's include parameters 

### 3.3.0-rc4
* Display general nosto sorting when customer is missing

### 3.3.0-rc3
* Add support for subcategory filtering

### 3.3.0-rc2
* Set total number of products to search result

### 3.3.0-rc1
* Fix incorrect products number in filters

### 3.2.2
* Add ACL resource for the module

### 3.2.1
* Build boolean filters for CMP 

### 3.2.0
* Add configuration to select fallback sorting for relevance

### 3.1.2
* Improve logging for getting filter values

### 3.1.1
* Fix version in composer file

### 3.1.0
* Cache category mapping block
* Render magento sorting directly when Nosto customer cookie is missing
* Render frontend layouts only when CM configuration is enabled
* Fix CM not displaying Nosto sorting when batchToken is null
* Fix issue where filters were not passed when using MySQL
* Return Magento sorting when CM call fails
* Fix Magento products graphql query pagination issue
* Add support for fetching more than 250 products from Nosto
* Add category merchandising support for headless (graphql) implementations

### 3.0.0
* Add support for using Elasticsearch as a catalog search engine
* Introduce possibility to use all categories (not only the ones in navigation) with Nosto's category merchandising

### 2.0.3
* Fix the default max product limit configuration and set the default value to be 250 which is the current max products limit in Nosto     

### 2.0.2
* Introduce possibility to define maximum amount of products to be fetched from Nosto to support category pages with that allow all products to be viewed

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
