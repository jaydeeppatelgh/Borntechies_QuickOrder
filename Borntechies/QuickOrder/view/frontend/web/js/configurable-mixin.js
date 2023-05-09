/**
 * Born Techies.
 *
 * @category  Borntechies
 * @package   Borntechies_QuickOrder
 * @author    Borntechies
 * @copyright Copyright (c) Born Techies Private Limited 
 * @license   https://borntechies.com/
 */
/**
 * @api
 */
define([
    'jquery',
    'underscore',
    'mage/template',
    'mage/translate',
    'priceUtils',
    'priceBox',
    'jquery/ui',
    'jquery/jquery.parsequery'
], function ($, _, mageTemplate, $t, priceUtils) {
    'use strict';
    return function (widget) {
        $.widget('mage.configurable', widget, {

            /**
             * Initialize tax configuration, initial settings, and options values.
             * @private
             */
            _initializeOptions: function () {
                var options = this.options;

                if (!window.location.href.includes("quickOrder")) {
                    var gallery = $(options.mediaGallerySelector),
                    priceBoxOptions = $(this.options.priceHolderSelector).priceBox('option').priceConfig || null;

                    if (priceBoxOptions && priceBoxOptions.optionTemplate) {
                        options.optionTemplate = priceBoxOptions.optionTemplate;
                    }

                    if (priceBoxOptions && priceBoxOptions.priceFormat) {
                        options.priceFormat = priceBoxOptions.priceFormat;
                    }
                }

                options.optionTemplate = mageTemplate(options.optionTemplate);
                options.tierPriceTemplate = $(this.options.tierPriceTemplateSelector).html();

                options.settings = options.spConfig.containerId ?
                    $(options.spConfig.containerId).find(options.superSelector) :
                    $(options.superSelector);

                options.values = options.spConfig.defaultValues || {};
                options.parentImage = $('[data-role=base-image-container] img').attr('src');

                this.inputSimpleProduct = this.element.find(options.selectSimpleProduct);

                if (!window.location.href.includes("quickOrder")) {
                    gallery.data('gallery') ?
                        this._onGalleryLoaded(gallery) :
                        gallery.on('gallery:loaded', this._onGalleryLoaded.bind(this, gallery));
                }
            },

            /**
             * Returns prices for configured products
             *
             * @param {*} config - Products configuration
             * @returns {*}
             * @private
             */
            _calculatePrice: function (config) {
                if (!window.location.href.includes("quickOrder")) {
                    var displayPrices = $(this.options.priceHolderSelector).priceBox('option').prices,
                        newPrices = this.options.spConfig.optionPrices[_.first(config.allowedProducts)];

                    _.each(displayPrices, function (price, code) {
                        if (newPrices[code]) {
                            displayPrices[code].amount = newPrices[code].amount - displayPrices[code].amount;
                        }
                    });

                    return displayPrices;
                }
            }
        });
        return $.mage.configurable;
    }
});
