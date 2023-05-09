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

class GiftcardProductView extends \Magento\Catalog\Block\Product\View
{
    /**
     * @var \Borntechies\QuickOrder\Helper\Data
     */
    private $helper;

    /**
     * @param \Magento\Catalog\Block\Product\Context $context
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
        $childBlock = $this->getLayout()->createBlock(
            \Magento\GiftCard\Block\Catalog\Product\View\Type\Giftcard::class
        )->setData('area', 'frontend')
        ->setTemplate('Magento_GiftCard::product/view/type/giftcard.phtml');
        $this->setChild('product.info.form.options', $childBlock);
    }
}
