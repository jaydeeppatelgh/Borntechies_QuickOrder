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
namespace Borntechies\QuickOrder\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Borntechies QuickOrder CheckoutCartProductAddAfterObserver Observer.
 */
class CheckoutCartProductAddAfterObserver implements ObserverInterface
{
    /**
     * Checkout cart product add event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($observer->getQuoteItem()) {
            $item = $observer->getQuoteItem();
            $item->setIsLastAddedItem(true);
        }
    }
}
