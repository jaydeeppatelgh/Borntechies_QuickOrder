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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart as CustomerCart;
use Magento\Framework\Exception\NoSuchEntityException;
use Borntechies\QuickOrder\Helper\Data as Helper;
use Magento\Framework\Filter\LocalizedToNormalized;

class Add extends \Magento\Checkout\Controller\Cart
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Checkout\Model\CompositeConfigProvider
     */
    protected $configProvider;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param CustomerCart $cart
     * @param ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Checkout\Model\CompositeConfigProvider $configProvider
     * @param Helper $helper
     * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
     * @param \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository
     * * @param RequestQuantityProcessor|null $quantityProcessor
     * @param \Magento\InventorySalesApi\Api\GetProductSalableQtyInterface $salableQuantity
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        CustomerCart $cart,
        ProductRepositoryInterface $productRepository,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Checkout\Model\CompositeConfigProvider $configProvider,
        Helper $helper,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
        \Magento\InventorySalesApi\Api\GetProductSalableQtyInterface $salableQuantity
    ) {
        parent::__construct(
            $context,
            $scopeConfig,
            $checkoutSession,
            $storeManager,
            $formKeyValidator,
            $cart
        );
        $this->productRepository = $productRepository;
        $this->jsonHelper = $jsonHelper;
        $this->configProvider = $configProvider;
        $this->helper = $helper;
        $this->serializer = $serializer;
        $this->stockItemRepository = $stockItemRepository ?: \Magento\Framework\App\ObjectManager::getInstance()
        ->get(\Magento\CatalogInventory\Model\Stock\StockItemRepository::class);
        $this->salableQuantity = $salableQuantity ?: \Magento\Framework\App\ObjectManager::getInstance()
        ->get(\Magento\InventorySalesApi\Api\GetProductSalableQtyInterface::class);
    }

    /**
     * Initialize product instance from request data
     *
     * @return \Magento\Catalog\Model\Product|false
     */
    private function _initProduct()
    {
        $productId = (int)$this->getRequest()->getParam('product');
        if ($productId) {
            try {
                $storeId = $this->helper->getCurrentStoreId();
                return $this->productRepository->getById($productId, false, $storeId);
            } catch (NoSuchEntityException $e) {
                return false;
            }
        }
        return false;
    }

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
            if (isset($params['qty'])) {
                $filter = new LocalizedToNormalized(
                    ['locale' => $this->_objectManager->get(
                        \Magento\Framework\Locale\ResolverInterface::class
                    )->getLocale()]
                );
                $params['qty'] = $filter->filter($params['qty']);
            }
            
            $product = $this->_initProduct();
            $related = $this->getRequest()->getParam('related_product');
            if ($product && $product->getIsSalable() && isset($params['qty']) && empty($params['super_attribute'])
                && empty($params['super_group']) && empty($params['bundle_option'])) {
                $stockId = $product->getExtensionAttributes()->getStockItem()->getStockId();
                $maxQty =  $this->salableQuantity->execute($product->getSku(), $stockId);
                if ($params['qty'] > $maxQty && $product->getTypeId() != "downloadable") {
                    $params['qty'] = $maxQty;
                    $this->messageManager->addError(__('Max quantity available for '.
                    $product->getName().' is '.$maxQty));
                }
            }
            if (!empty($params['super_attribute']) && isset($params['qty'])) {
                $configProduct = $product->getTypeInstance()->getProductByAttributes(
                    $params['super_attribute'],
                    $product
                );
                $productSku = $configProduct->getSku();
                $selectConfigProduct = $this->productRepository->get($productSku);
                $stockId = $selectConfigProduct->getExtensionAttributes()->getStockItem()->getStockId();
                $maxQty =  $this->salableQuantity->execute($productSku, $stockId);
                if ($params['qty'] > $maxQty) {
                    $params['qty'] = $maxQty;
                    $this->messageManager->addError(__('Max quantity available for '.
                    $product->getName().' is '.$maxQty));
                }
            } elseif (!empty($params['super_group']) && isset($params['qty'])) {
                $groupArray = $params['super_group'];
                foreach ($groupArray as $key => $qty) {
                    if ($qty !=0) {
                        
                        $groupProduct = $this->productRepository->getById($key);
                        $stockId = $groupProduct->getExtensionAttributes()->getStockItem()->getStockId();
                        $maxQty =  $this->salableQuantity->execute($groupProduct->getSku(), $stockId);
                        if ($qty > $maxQty) {
                            $groupArray[$key] = (string)$maxQty;
                            $this->messageManager->addError(__('Max quantity available for '.
                            $groupProduct->getName().' is '.$maxQty));
                        }
                    }
                }
                $params['super_group'] = $groupArray;
            } elseif (!empty($params['bundle_option']) && isset($params['qty'])
            && !empty($params['bundle_option_qty'])) {
                $selectionCollection = $product->getTypeInstance(true)
                ->getSelectionsCollection(
                    $product->getTypeInstance(true)->getOptionsIds($product),
                    $product
                );
                $bundleQtyArray = $params['bundle_option_qty'];
                $maxQtyArray = [];
                   
                foreach ($selectionCollection as $selection) {
                        $bundleProduct = $this->productRepository->get($selection->getSku());
                        $stockId = $bundleProduct->getExtensionAttributes()->getStockItem()->getStockId();
                        $maxQty =  $this->salableQuantity->execute($bundleProduct->getSku(), $stockId);
                        
                    if ($bundleQtyArray[$selection->getOptionId()] > $maxQty) {
                        $bundleQtyArray[$selection->getOptionId()] = (string)$maxQty;
                        $this->messageManager->addError(__('Max quantity available for '.
                        $bundleProduct->getName().' is '.$maxQty));
                    }
                        array_push($maxQtyArray, $maxQty);
                }
                $params['bundle_option_qty'] = $bundleQtyArray;
                $bundleProductQty = min($maxQtyArray);
                if ($params['qty'] > $bundleProductQty) {
                    $params['qty'] = (string)$bundleProductQty;
                    $this->messageManager->addError(__('Max quantity available for '.
                    $product->getName().' is '.$bundleProductQty));
                }
            }

            /**
             * Check product availability
             */
            if (!$product) {
                $resultData['error'] = 1;
                $resultData['message'] = __("Product doesn't exist.");
            } elseif ($product && !$product->getIsSalable()) {
                $resultData['error'] = 1;
                $resultData['message'] = __('Requested product %1 is not in stock.', $product->getName());
                $this->messageManager->addError(__('Requested product %1 is not in stock.', $product->getName()));
            } else {
                $this->cart->addProduct($product, $params);
                if (!empty($related)) {
                    $this->cart->addProductsByIds(explode(',', $related));
                }

                $this->cart->save();

                $this->_eventManager->dispatch(
                    'checkout_cart_add_product_complete',
                    ['product' => $product, 'request' => $this->getRequest(), 'response' => $this->getResponse()]
                );

                if (!$this->_checkoutSession->getNoCartRedirect(true)) {
                    if (!$this->cart->getQuote()->getHasError() || $this->_checkoutSession->getItemMinQtyStatus()) {
                        $message = __(
                            'You added %1 to your shopping cart.',
                            $product->getName()
                        );
                        if (array_key_exists('super_group', $params)) {
                            $resultData['grouped_items'] = $this->getGroupedProducts($params);
                            $resultData['group_product_id'] = $params['product'];
       
                        } else {
                                $itemId = $this->_checkoutSession->getLastAddedItem();
                                $resultData['item'] = $itemId;
                                $resultData['price'] = $this->_checkoutSession->getLastAddedItemPrice();
                                $resultData['qty'] = $this->_checkoutSession->getLastAddedItemQty();
                            
                        }
                        
                        $resultData['error'] = 0;
                        $resultData['message'] =  $message;
                    } else {
                        $resultData['error'] = 1;
                        $resultData['message'] =  __('We can\'t add this item to your shopping cart right now.');
                    }
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $resultData['error'] = 1;
            $resultData['message'] =  $this->_objectManager->get(
                \Magento\Framework\Escaper::class
            )->escapeHtml($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('We can\'t add this item to your shopping cart right now.'));
            $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
            $resultData['error'] = 1;
            $resultData['message'] =  __('We can\'t add this item to your shopping cart right now.');
        }
        $resultData['checkout_data'] = $this->getSerializedCheckoutConfig();
        if (isset($params['hasCustomFileTypeReOption']) && $params['hasCustomFileTypeReOption'] == 1) {
            return $this->resultRedirectFactory->create()->setPath('quickOrder/index/', ['_current' => false]);
        }
        return $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($resultData)
        );
    }

    /**
     * Retrieve checkout configuration
     *
     * @return array
     */
    public function getCheckoutConfig()
    {
        return $this->configProvider->getConfig();
    }

    /**
     * Get Serialized Checkout Config
     *
     * @return bool|string
     * @since 100.2.0
     */
    public function getSerializedCheckoutConfig()
    {
        return $this->getSerializer()->serialize(
            $this->getCheckoutConfig()
        );
    }

    /**
     * Get Serializer
     *
     * @return bool|string
     * @since 100.2.0
     */
    public function getSerializer()
    {
        if (null === $this->serializer) {
            $this->serializer = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Serialize\Serializer\Json::class
            );
        }
        return $this->serializer;
    }

    /**
     * Get grouped items added to cart
     *
     * @param [type] $params
     * @return array
     */
    public function getGroupedProducts($params)
    {
        $groupedArray = $params['super_group'];
        $resultData['grouped_items'] =[];
        foreach ($groupedArray as $productId => $qty) {
            if ($qty) {
                $cartItems = $this->cart->getQuote()->getAllVisibleItems();
                $cartdata = [];
                foreach ($cartItems as $key => $item) {
                    if ($item->getProductId() == $productId) {
                        $cartdata['item'] = $item->getItemId();
                        $cartdata['price'] = $item->getProduct()->getPrice();
                        $cartdata['product_id'] = $item->getProductId();
                        $cartdata['qty'] = $qty;
                        $cartdata['name'] = $item->getProduct()->getName();
                        $cartdata['checkout_data'] = $this->getSerializedCheckoutConfig();
                    }
                   
                }
                    $resultData['grouped_items'][] = $cartdata;
            }
        }
        return $resultData['grouped_items'];
    }
}
