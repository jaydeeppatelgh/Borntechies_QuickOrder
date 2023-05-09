#Installation

Magento2 Quick Order module installation is very easy, please follow the steps for installation-

1. Unzip the respective extension zip and create Borntechies(vendor) and QuickOrder(module) name folder inside your magento/app/code/ directory and then move all module's files into magento root directory Magento2/app/code/Borntechies/QuickOrder/ folder.

Run Following Command via terminal
-----------------------------------
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy

2. Flush the cache and reindex all.
