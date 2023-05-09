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
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Controller\ResultFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\View\Result\PageFactory;
use Borntechies\QuickOrder\Helper\Data as Helper;
use Borntechies\QuickOrder\Model\ResourceModel\Catalog\ProductFactory as QuickProductFactory;

class Search extends Action
{
    /**
     * @var \Magento\Catalog\Model\Layer
     */
    private $_catalogLayer;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $_product;

    /**
     * @var ProductCollectionFactory
     */
    private $catalogProductCollection;

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var QuickProductFactory
     */
    private $quickProductFactory;

    /**
     * @param Context $context
     * @param Resolver $layerResolver
     * @param ProductFactory $product
     * @param QuickProductFactory $quickProductFactory
     * @param ProductCollectionFactory $catalogProductCollection
     * @param Helper $helper
     * @param PageFactory $resultPageFactory
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Context $context,
        Resolver $layerResolver,
        ProductFactory $product,
        QuickProductFactory $quickProductFactory,
        ProductCollectionFactory $catalogProductCollection,
        Helper $helper,
        PageFactory $resultPageFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($context);
        $this->_catalogLayer = $layerResolver->get();
        $this->_product = $product;
        $this->quickProductFactory = $quickProductFactory;
        $this->catalogProductCollection = $catalogProductCollection;
        $this->helper = $helper;
        $this->resultPageFactory = $resultPageFactory;
        $this->productRepository = $productRepository ?: \Magento\Framework\App\ObjectManager::getInstance()
        ->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
    }

    /**
     * Get catalog layer model
     *
     * @return \Magento\Catalog\Model\Layer
     */
    protected function getLayer()
    {
        return $this->_catalogLayer;
    }

    /**
     * Product Search Action
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
            $requestedData = $this->getRequest()->getParams();
            $responseData = [];
            $responseData['size'] = 0;
            $responseData['products'] = [];
            if (isset($requestedData['q']) && !empty($requestedData['q']) && strlen($requestedData['q']) > 2) {
                $searchKey = strtolower($requestedData['q']);
                // Search record by sku if available
                $productId = $this->quickProductFactory->create()->getIdBySku($searchKey);

                if ($productId) {
                    $responseData = $this->getProductDataById($productId);
                } else {
                    $responseData = $this->getProductsResponseData($searchKey);
                }
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

    /**
     * Get Products Response Data
     *
     * @param string $searchKey
     * @return array
     */
    public function getProductsResponseData($searchKey)
    {
        $results = $this->quickProductFactory->create()->getCollection($searchKey);
        if (!empty($results)) {
            $resultPage = $this->resultPageFactory->create();
                
            $blockInstance = $resultPage->getLayout()->getBlock('quick_order_product_search');
            $sizeCounter = 0;
            foreach ($results as $product) {
                if ($product['is_in_stock'] == '1') {
                    $productModel = $this->_product->create();
                    $productModel->setData($product);
                    $isOptions = $this->hasOptions($product);
                    $price = str_replace("<p", "<span", $blockInstance->getProductPrice($productModel));
                    $price = str_replace("p>", "span>", $price);
                    $responseData['products'][] = [
                        'entity_id' => $product['entity_id'],
                        'name' => $product['name'],
                        'sku' => $product['sku'],
                        'price' => $product['price'],
                        'is_options' => $isOptions,
                        'final_price' => $product['final_price'],
                        'price_html' => $price,
                        'type_id' => $product['type_id']
                    ];
                    $sizeCounter++;
                }
            }
            $responseData['size'] = $sizeCounter;
        } else {
            // Search record by name for any of the search keyword
            $responseData = $this->getProductDataBySearchKeywords(
                $searchKey,
                $responseData
            );
        }
        return $responseData;
    }

    /**
     * Return Product Search Data by id
     *
     * @param int $productId
     * @return array
     */
    protected function getProductDataById($productId)
    {
        $responseData =  [];
        $productModel = $this->_product->create();
        $productModel->setId($productId);
        $product = $this->quickProductFactory->create()->getSuggestProductById($productModel);
        if ($product->isSaleable()) {
            $isOptions = $this->hasOptions($product->getData());
            $resultPage = $this->resultPageFactory->create();
            $blockInstance = $resultPage->getLayout()->getBlock('quick_order_product_search');
            $price = str_replace("<p", "<span", $blockInstance->getProductPrice($product));
            $price = str_replace("p>", "span>", $price);
            $responseData['size'] = 1;
            $responseData['products'][] = [
                'entity_id' => $product->getId(),
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'price' => $product->getPrice(),
                'is_options' => $isOptions,
                'final_price' => $product->getFinalPrice(),
                'price_html' => $price,
                'type_id' => $product->getTypeId()
            ];
        }
        return $responseData;
    }

    /**
     * Return Product Search Data for all search keyword
     *
     * @param string $searchKey
     * @param array $responseData
     * @return array
     */
    protected function getProductDataBySearchKeywords($searchKey, $responseData)
    {
        $searchKeyArr = explode(' ', $searchKey);
        $results = $this->quickProductFactory->create()->getCollection($searchKeyArr);
        $responseData['size'] = count($results);
        $resultPage = $this->resultPageFactory->create();
        $blockInstance = $resultPage->getLayout()->getBlock('quick_order_product_search');
        foreach ($results as $product) {
            $productModel = $this->_product->create();
            $productModel->setData($product);
            $isOptions = $this->hasOptions($product);
            $price = str_replace("<p", "<span", $blockInstance->getProductPrice($productModel));
            $price = str_replace("p>", "span>", $price);
            $responseData['products'][] = [
                'entity_id' => $product['entity_id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
                'price' => $product['price'],
                'is_options' => $isOptions,
                'final_price' => $product['final_price'],
                'price_html' => $price,
                'type_id' => $product['type_id']
            ];
        }
        return $responseData;
    }

    /**
     * Check if product has options
     *
     * @param array $product
     * @return bool
     */
    protected function hasOptions(array $product)
    {
        $isOptions = 0;
        if ($product['type_id'] == 'configurable' || $product['has_options']) {
            $isOptions = 1;
        } elseif ($product['type_id'] == 'downloadable') {
            $count = 0;
            $linkFile = '';
            $linkUrl = '';
            if (isset($product['downloadable_links'])) {
                $count = count($product['downloadable_links']);
                foreach ($product['downloadable_links'] as $link) {
                    if (isset($link['link_file'])) {
                        $linkFile = $link['link_file'];
                    }
                    if (isset($link['link_url'])) {
                        $linkUrl = $link['link_url'];
                    }
                }
            }
            if (($linkFile || $linkUrl) && $count > 1) {
                $isOptions = 1;
            }
        } elseif ($product['type_id'] == 'bundle') {
            $isOptions = 1;
        } elseif ($product['type_id'] == 'grouped') {
            $isOptions = 1;
        }
        return $isOptions;
    }
}
