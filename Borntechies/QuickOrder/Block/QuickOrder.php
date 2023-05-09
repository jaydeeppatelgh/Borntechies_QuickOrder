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

namespace Borntechies\QuickOrder\Block;

class QuickOrder extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Framework\Locale\Format
     */
    private $priceFormat;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Borntechies\QuickOrder\Helper\Data
     */
    protected $quickOrderHelper;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Locale\Format $priceFormat
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Borntechies\QuickOrder\Helper\Data $quickOrderHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Locale\Format $priceFormat,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Borntechies\QuickOrder\Helper\Data $quickOrderHelper,
        array $data = []
    ) {
        $this->priceFormat = $priceFormat;
        $this->checkoutSession = $checkoutSession;
        $this->jsonHelper = $jsonHelper;
        $this->quickOrderHelper = $quickOrderHelper;
        parent::__construct($context, $data);
    }

     /**
      * Get Price Format
      *
      * @return object
      */
    public function getPriceFormat()
    {
        return $this->priceFormat->getPriceFormat();
    }

     /**
      * Get Quote
      *
      * @return object
      */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

     /**
      * Check is Quote Exist
      *
      * @return boolean
      */
    public function isQuoteExist()
    {
        $isQuoteExist = 0;
        if ($this->getQuote()->getId()) {
            $isQuoteExist = 1;
        }
        return $isQuoteExist;
    }

     /**
      * Get Cart Items
      *
      * @return array
      */
    public function getCartItems()
    {
        $cartItems = [];
        $index = 0;
        if ($this->getQuote()->getId()) {
            $this->getQuote()->collectTotals();
            $items = $this->getQuote()->getAllVisibleItems();
            foreach ($items as $key => $item) {
                $index++;
                $cartItems[$index]['item_id'] = $item->getId();
                $cartItems[$index]['product_id'] = $item->getProductId();
                $cartItems[$index]['name'] = $item->getName();
                $cartItems[$index]['qty'] = $item->getQty();
                $cartItems[$index]['price'] = $item->getPrice();
                $cartItems[$index]['row_total'] = $item->getRowTotal();
            }
        }
        return $cartItems;
    }

    /**
     * Get Json Helper
     *
     * @return object
     */
    public function getJsonHelper()
    {
        return $this->jsonHelper;
    }

    /**
     * Get Quick Order Helper
     *
     * @return object
     */
    public function getQuickOrderHelper()
    {
        return $this->quickOrderHelper;
    }
}
