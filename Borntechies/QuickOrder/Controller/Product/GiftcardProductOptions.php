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

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Borntechies\QuickOrder\Helper\Data as Helper;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

class GiftcardProductOptions extends Action
{
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $_product;

    /**
     * Core registry
     *
     * @var Registry
     */
    private $_registry = null;

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var FormKeyValidator
     */
    private $_formKeyValidator;

    /**
     * @param Context $context
     * @param ProductFactory $product
     * @param Registry $registry
     * @param Helper $helper
     * @param FormKeyValidator $formKeyValidator
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        ProductFactory $product,
        Registry $registry,
        Helper $helper,
        FormKeyValidator $formKeyValidator,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->_product = $product;
        $this->_registry = $registry;
        $this->helper = $helper;
        $this->_formKeyValidator = $formKeyValidator;
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Gift card Product Options Action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        try {
            // If Quick Order is disabled
            if (!$this->helper->isQuickOrderEnable()) {
                $this->getRequest()->initForward();
                $this->getRequest()->setActionName('noroute');
                $this->getRequest()->setDispatched(false);
    
                return false;
            }

            if (!$this->_formKeyValidator->validate($this->getRequest())) {
                return $this->resultRedirectFactory->create()->setPath('quickOrder/');
            }

            $requestedData = $this->getRequest()->getParams();
            $responseData = [];
            if (isset($requestedData['product']) && !empty($requestedData['product'])) {
                $resultPage = $this->resultPageFactory->create();
                $blockInstance = $resultPage->getLayout()->getBlock('quick_order_product_options');
                $productModel = $this->_product->create()->load($requestedData['product'])
                    ->setTypeId($requestedData['typeId']);
                $productModel = $this->_product->create()->load($requestedData['product']);
                $this->_registry->register('product', $productModel);
                $this->_registry->register('current_product', $productModel);
                $blockInstance1 = $resultPage->getLayout()->getBlock('product.info.form.options');
                $responseData['detail_html'] = $blockInstance->getProductDetailsHtml($productModel);
                $responseData['options_html'] = $blockInstance1->getChildHtml();
                $this->_registry->unregister('product');
                $this->_registry->unregister('current_product');
            }
            /** @var \Magento\Framework\Controller\Result\Json $resultJson */
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $resultJson->setData($responseData);
            return $resultJson;
        } catch (\Exception $e) {
            $responseData = [
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode(),
            ];
            /** @var \Magento\Framework\Controller\Result\Json $resultJson */
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $resultJson->setData($responseData);
            return $resultJson;
        }
    }
}
