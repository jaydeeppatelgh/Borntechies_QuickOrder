/**
 * Born Techies.
 *
 * @category  Borntechies
 * @package   Borntechies_QuickOrder
 * @author    Borntechies
 * @copyright Copyright (c) Born Techies Private Limited 
 * @license   https://borntechies.com/
 */

var config = {
    map: {
        '*': {
            wkQuickOrder: 'Borntechies_QuickOrder/js/quick-order',
            'Magento_Checkout/js/checkout-data':'Borntechies_QuickOrder/js/checkout-data'
        }
    },
    config: {
        mixins: {
            'Magento_ConfigurableProduct/js/configurable': {
                'Borntechies_QuickOrder/js/configurable-mixin': true
            },
            'Magento_Swatches/js/swatch-renderer': {
                'Borntechies_QuickOrder/js/swatch-renderer-mixin':true
            }
        }
    }
};
