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
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Borntechies QuickOrder SalesQuoteItemSaveAfterObserver Observer.
 */
class SalesQuoteItemSaveAfterObserver implements ObserverInterface
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Sale quote item add after event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quoteItem = $observer->getItem();
        if ($quoteItem->getIsLastAddedItem()) {
            $this->checkoutSession->setLastAddedItem($quoteItem->getId());
            $this->checkoutSession->setLastAddedItemPrice($quoteItem->getRowTotal());
            $this->checkoutSession->setLastAddedItemQty($quoteItem->getQty());
        }
    }
}
