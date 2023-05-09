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

namespace Borntechies\QuickOrder\Block\Product;

use Magento\Catalog\Model\Product;

class View extends \Magento\Catalog\Block\Product\View
{
    /**
     * @var \Borntechies\QuickOrder\Helper\Data
     */
    private $helper;

    /**
     * @param Context $context
     * @param \Magento\Framework\Url\EncoderInterface $urlEncoderInterface
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoderInterface
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Magento\Catalog\Helper\Product $productHelperData
     * @param \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig
     * @param \Magento\Framework\Locale\FormatInterface $localeFormat
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Borntechies\QuickOrder\Helper\Data $helper
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Url\EncoderInterface $urlEncoderInterface,
        \Magento\Framework\Json\EncoderInterface $jsonEncoderInterface,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Catalog\Helper\Product $productHelperData,
        \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Borntechies\QuickOrder\Helper\Data $helper,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct(
            $context,
            $urlEncoderInterface,
            $jsonEncoderInterface,
            $string,
            $productHelperData,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );
    }

    /**
     * @inheritdoc
     */
    protected function _prepareLayout()
    {
        if ($this->helper->isMagentoSwatchesModuleInstalled()) {
            $childBlock = $this->getLayout()->createBlock(
                \Magento\Swatches\Block\Product\Renderer\Listing\Configurable::class
            );
            $this->setChild('product.info.options.wrapper', $childBlock);
        } else {
            $childBlock = $this->getLayout()->createBlock(
                \Magento\ConfigurableProduct\Block\Product\View\Type\Configurable::class
            )->setData('area', 'frontend')
            ->setTemplate('Magento_ConfigurableProduct::product/view/type/options/configurable.phtml');
            $this->setChild('product.info.options.configurable', $childBlock);
        }
    }

    /**
     * Get Product Price
     *
     * @param Product $product
     * @return string
     */
    public function getProductPrice(Product $product)
    {
        $priceRender = $this->getPriceRender();

        $price = '';
        if ($priceRender) {
            $price = $priceRender->render(
                \Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE,
                $product,
                [
                    'include_container' => true,
                    'display_minimal_price' => true,
                    'zone' => \Magento\Framework\Pricing\Render::ZONE_ITEM_LIST,
                    'list_category_page' => true
                ]
            );
        }

        return $price;
    }

    /**
     * Specifies that price rendering should be done for the list of products
     *
     * @return \Magento\Framework\Pricing\Render
     */
    protected function getPriceRender()
    {
        return $this->getLayout()->getBlock('product.price.render.default')
            ->setData('is_product_list', true);
    }
}
