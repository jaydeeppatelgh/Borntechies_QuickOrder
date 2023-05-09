/**
 * Born Techies.
 *
 * @category  Borntechies
 * @package   Borntechies_QuickOrder
 * @author    Borntechies
 * @copyright Copyright (c) Born Techies Private Limited 
 * @license   https://borntechies.com/
 */

define([
    'jquery',
    'underscore',
    'mage/template',
    'mage/smart-keyboard-handler',
    'mage/translate',
    'priceUtils',
    'jquery/ui',
    'jquery/jquery.parsequery',
    'mage/validation/validation'
], function ($, _, mageTemplate, keyboardHandler, $t, priceUtils) {
    'use strict';

    /**
     * Render swatch controls with options and use tooltips.
     * Required two json:
     *  - jsonConfig (magento's option config)
     *  - jsonSwatchConfig (swatch's option config)
     *
     *  Tuning:
     *  - numberToShow (show "more" button if options are more)
     *  - onlySwatches (hide selectboxes)
     *  - moreButtonText (text for "more" button)
     *  - selectorProduct (selector for product container)
     *  - selectorProductPrice (selector for change price)
     */
    return function (widget) {
        $.widget('mage.SwatchRenderer', widget, {

            /**
             * Update [gallery-placeholder] or [product-image-photo]
             * @param {Array} images
             * @param {jQuery} context
             * @param {Boolean} isInProductView
             * @param {Object} gallery
             */
            processUpdateBaseImage: function (images, context, isInProductView, gallery) {
                if (!window.location.href.includes("quickOrder")) {
                    var justAnImage = images[0],
                        initialImages = this.options.mediaGalleryInitial,
                        imagesToUpdate,
                        isInitial;

                    if (isInProductView) {
                        imagesToUpdate = images.length ? this._setImageType($.extend(true, [], images)) : [];
                        isInitial = _.isEqual(imagesToUpdate, initialImages);

                        if (this.options.gallerySwitchStrategy === 'prepend' && !isInitial) {
                            imagesToUpdate = imagesToUpdate.concat(initialImages);
                        }

                        imagesToUpdate = this._setImageIndex(imagesToUpdate);
                        gallery.updateData(imagesToUpdate);

                        if (isInitial) {
                            $(this.options.mediaGallerySelector).AddFotoramaVideoEvents();
                        } else {
                            $(this.options.mediaGallerySelector).AddFotoramaVideoEvents({
                                selectedOption: this.getProduct(),
                                dataMergeStrategy: this.options.gallerySwitchStrategy
                            });
                        }

                        gallery.first();
                    } else if (justAnImage && justAnImage.img) {
                        context.find('.product-image-photo').attr('src', justAnImage.img);
                    }
                }
            },

            /**
             * Update total price
             *
             * @private
             */
            _UpdatePrice: function () {
                if (!window.location.href.includes("quickOrder")) {
                    var $widget = this,
                        $product = $widget.element.parents($widget.options.selectorProduct),
                        $productPrice = $product.find(this.options.selectorProductPrice),
                        options = _.object(_.keys($widget.optionsMap), {}),
                        result,
                        tierPriceHtml;

                    $widget.element.find('.' + $widget.options.classes.attributeClass + '[option-selected]').each(function () {
                        var attributeId = $(this).attr('attribute-id');

                        options[attributeId] = $(this).attr('option-selected');
                    });

                    result = $widget.options.jsonConfig.optionPrices[_.findKey($widget.options.jsonConfig.index, options)];

                    $productPrice.trigger(
                        'updatePrice',
                        {
                            'prices': $widget._getPrices(result, $productPrice.priceBox('option').prices)
                        }
                    );

                    if (typeof result != 'undefined' && result.oldPrice.amount !== result.finalPrice.amount) {
                        $(this.options.slyOldPriceSelector).show();
                    } else {
                        $(this.options.slyOldPriceSelector).hide();
                    }

                    if (typeof result != 'undefined' && result.tierPrices.length) {
                        if (this.options.tierPriceTemplate) {
                            tierPriceHtml = mageTemplate(
                                this.options.tierPriceTemplate,
                                {
                                    'tierPrices': result.tierPrices,
                                    '$t': $t,
                                    'currencyFormat': this.options.jsonConfig.currencyFormat,
                                    'priceUtils': priceUtils
                                }
                            );
                            $(this.options.tierPriceBlockSelector).html(tierPriceHtml).show();
                        }
                    } else {
                        $(this.options.tierPriceBlockSelector).hide();
                    }

                    $(this.options.normalPriceLabelSelector).hide();

                    _.each($('.' + this.options.classes.attributeOptionsWrapper), function (attribute) {
                        if ($(attribute).find('.' + this.options.classes.optionClass + '.selected').length === 0) {
                            if ($(attribute).find('.' + this.options.classes.selectClass).length > 0) {
                                _.each($(attribute).find('.' + this.options.classes.selectClass), function (dropdown) {
                                    if ($(dropdown).val() === '0') {
                                        $(this.options.normalPriceLabelSelector).show();
                                    }
                                }.bind(this));
                            } else {
                                $(this.options.normalPriceLabelSelector).show();
                            }
                        }
                    }.bind(this));
                }
            }
        });
        return $.mage.SwatchRenderer;
    }
});
