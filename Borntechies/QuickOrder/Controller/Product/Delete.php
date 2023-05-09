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

class Delete extends Add
{
    /**
     * Delete shopping cart item action
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

        $id = (int)$this->getRequest()->getParam('id');
        if ($id) {
            try {
                $this->cart->removeItem($id)->save();
                $resultData['error'] = 0;
            } catch (\Exception $e) {
                $resultData['error'] = 1;
                $resultData['message'] = __('We can\'t remove the item.');
                $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
            }
        } else {
            $resultData['error'] = 1;
            $resultData['message'] = __('Something went wrong.');
        }
        $resultData['checkout_data'] = $this->getSerializedCheckoutConfig();
        $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($resultData)
        );
    }
}
