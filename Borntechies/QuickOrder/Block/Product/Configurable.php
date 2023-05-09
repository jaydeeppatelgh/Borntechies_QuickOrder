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

/**
 * Confugurable product view type
 *
 * @api
 * @api
 * @since 100.0.2
 */
class Configurable extends \Magento\ConfigurableProduct\Block\Product\View\Type\Configurable
{
    /**
     * Get allowed attributes
     *
     * @return array
     */
    public function getAllowAttributes()
    {
        if ($this->getProduct()->getTypeId() =='configurable') {
            return $this->getProduct()->getTypeInstance()->getConfigurableAttributes($this->getProduct());
        }
        return [];
    }
}
