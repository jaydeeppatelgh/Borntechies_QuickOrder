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
namespace Borntechies\QuickOrder\Plugin\Checkout\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Context as CustomerContext;

class DefaultConfigProvider
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var HttpContext
     */
    private $httpContext;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param HttpContext $httpContext
     * @param CartManagementInterface $cartManagement
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        HttpContext $httpContext,
        CartManagementInterface $cartManagement
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->httpContext = $httpContext;
        $this->cartManagement = $cartManagement;
    }

    /**
     * Set Quote Id
     *
     * @param \Magento\Checkout\Model\DefaultConfigProvider $subject
     * @param callable $proceed
     * @return array
     */
    public function aroundGetConfig(
        \Magento\Checkout\Model\DefaultConfigProvider $subject,
        callable $proceed
    ) {
        $quoteId = $this->checkoutSession->getQuote()->getId();
        $output = [];
        if (!$quoteId) {
            if ($this->isCustomerLoggedIn()) {
                $customerId = $this->customerSession->getCustomerId();
                $cartId = $this->cartManagement->createEmptyCartForCustomer($customerId);
            } else {
                $cartId = $this->cartManagement->createEmptyCart();
            }
            // set checkout session quote id
            $this->checkoutSession->clearStorage();
            $this->checkoutSession->setQuoteId($cartId);
            $this->checkoutSession->setLoadInactive(true);
        }
        return $proceed();
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    private function isCustomerLoggedIn()
    {
        return (bool)$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }
}
