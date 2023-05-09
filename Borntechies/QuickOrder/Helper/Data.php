<?php
/**
 * Born Techies.
 *
 * @category  Borntechies
 * @package   Borntechies_QuickOrder
 * @author    Borntechies
 * @copyright Copyright (c) Born Techies Private Limited 
 * @license   https://borntechies.com/
 */
namespace Borntechies\QuickOrder\Helper;

/**
 * Borntechies QuickOrder Helper Data.
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param \Magento\Framework\App\Helper\Context        $context
     * @param \Magento\Store\Model\StoreManagerInterface   $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
    }

    /**
     * Return Current Store Id
     *
     * @return int
     */
    public function getCurrentStoreId()
    {
        // give the current store id
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Check if Magento Swatches Module Installed
     *
     * @return bool
     */
    public function isMagentoSwatchesModuleInstalled()
    {
        return $this->_moduleManager->isEnabled('Magento_Swatches');
    }

    /**
     * Check if Quick Order is enable
     *
     * @return bool
     */
    public function isQuickOrderEnable()
    {
        return $this->scopeConfig->getValue(
            'quickOrder/settings/enable',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
