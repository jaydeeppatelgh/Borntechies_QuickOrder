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
namespace Borntechies\QuickOrder\Controller\Product;
use Magento\Framework\Filter\LocalizedToNormalized;

class UpdateItemQty extends Add
{
    /**
     * Add product to shopping cart action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        // If Quick Order is disabled
        if (!$this->helper->isQuickOrderEnable()) {
            $this->getRequest()->initForward();
            $this->getRequest()->setActionName('noroute');
            $this->getRequest()->setDispatched(false);

            return false;
        }

        $params = $this->getRequest()->getParams();

        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            return $this->resultRedirectFactory->create()->setPath('quickOrder/');
        }

        try {
            if (isset($params['item_qty'])) {
                $filter = new LocalizedToNormalized(
                    ['locale' => $this->_objectManager->get(
                        \Magento\Framework\Locale\ResolverInterface::class
                    )->getLocale()]
                );
                $params['item_qty'] = $filter->filter($params['item_qty']);
            }
            $cartData = [];
            $cartData[$params['item_id']]['qty'] = $params['item_qty'];
            $cartData = $this->cart->suggestItemsQty($cartData);
            $this->cart->updateItems($cartData)->save();
            $resultData['error'] = 0;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $resultData['error'] = 1;
            $resultData['message'] =  $this->_objectManager->get(
                \Magento\Framework\Escaper::class
            )->escapeHtml($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('We can\'t update the shopping cart.'));
            $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
            $resultData['error'] = 1;
            $resultData['message'] =  __('We can\'t update the shopping cart.');
        }
        $resultData['checkout_data'] = $this->getSerializedCheckoutConfig();
        $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($resultData)
        );
    }
}
