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
    'uiComponent',
    'ko',
    'Magento_Ui/js/modal/alert',
    'jquery',
    'underscore',
    'mage/template',
    'matchMedia',
    'priceUtils',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-rate-processor/new-address',
    'Magento_Checkout/js/model/shipping-rate-processor/customer-address',
    'Magento_Checkout/js/model/shipping-rate-registry',
    'Magento_Ui/js/modal/modal',
    'jquery/ui',
    'mage/translate',
    'Magento_Customer/js/model/authentication-popup',
    'mage/mage'
], function (
    Component,
    ko,
    alert,
    $,
    _,
    mageTemplate,
    mediaCheck,
    utils,
    customerData,
    quote,
    addressProcessor,
    customerAddressProcessor,
    shippingRateRegistry,
    modal,
    ui,
    $t,
    authenticationPopup
) {
    'use strict';

    /**
     * Check whether the incoming string is not empty or if doesn't consist of spaces.
     *
     * @param {String} value - Value to check.
     * @returns {Boolean}
     */
    function isEmpty(value)
    {
        return value.length === 0 || value == null || /^\s+$/.test(value);
    }

    var searchAjaxRequest = null;

    $('body').click(function(event) {
        if ($(event.target).parents('div[id^=wk_search_autocomplete]').length == 0
        && !$(event.target).is('input[id^=wk_search_box]')
        ) {
            $('div[id^=wk_search_autocomplete]').hide();
        }
    });

    $('form#wk-quick-order-form').on('submit', function(e){
        e.preventDefault();
        $(location).attr('href', $('form#wk-quick-order-form').attr('action'));
    });

    return Component.extend({
        defaults: {
            formSelector: '#wk-quick-order-form',
            fieldSelector: '.wk-product-search-box',
            destinationSelector: '.search-autocomplete',
            autocompleteVal: 'off',
            minSearchLength: 3,
            index: 1,
            responseFieldElements: 'ul li',
            selectClass: 'selected',
            template: '<li class="<%- data.row_class %>" id="qs-option-<%- data.index %><%- data.entity_id %>" data-id="<%- data.entity_id %>" data-type="<%- data.type_id %>" role="option" data-option-available="<%- data.isOption %>">' +
                '<span class="qs-option-name" data-id="<%- data.entity_id %>" data-type="<%- data.type_id %>">' +
                ' <%- data.name %>' +
                '</span>' +
                '<span aria-hidden="true" class="amount" data-id="<%- data.entity_id %>" data-type="<%- data.type_id %>" data-price-amount="<%- data.final_price %>" id="product-amount-<%- data.index %><%- data.entity_id %>">' +
                '<%- data.final_price %>' +
                '</span>' +
                '</li>',
            submitBtnSelector: 'button[id="proceed-checkout"]',
            isExpandable: null
        },

        isAjaxLoader: ko.observable(false),
        isQuoteExist: ko.observable(false),

        initialize: function () {
            this._super();
            this.addCartItemRow();
            this.responseList = {
                wk_search_autocomplete1: {
                    indexList: null,
                    optionsListHtml: {},
                    selected: null
                }
            };
            this.searchField = $(this.fieldSelector);
            this.autoComplete = $(this.destinationSelector);
            this.searchForm = $(this.formSelector);
            this.submitBtn = this.searchForm.find(this.submitBtnSelector)[0];
            this.isExpandable = this.isExpandable;
            this.isQuoteExist(this.isQuoteExistFlag);

            if (!Object.keys(this.cartItems).length) {
                this.submitBtn.disabled = true;
            }

            this.searchField.attr('autocomplete', this.autocompleteVal);

            mediaCheck({
                media: '(max-width: 768px)',
                entry: function () {
                    this.isExpandable = true;
                }.bind(this),
                exit: function () {
                    this.isExpandable = false;
                    this.searchField.removeAttr('aria-expanded');
                }.bind(this)
            });
        },

        onBlur: function (item, event) {
            var thisSelector = $(event.target);
            var autoCompleteSelector = thisSelector.parents('td').find(this.destinationSelector);
            // setTimeout($.proxy(function () {
                if (!autoCompleteSelector.is(':hidden')) {
                    this.setActiveState(event, false);
                } else {
                    thisSelector.trigger('focus');
                }
                autoCompleteSelector.hide();
                this._updateAriaHasPopup(event, false);
            // }, this), 250);
        },

        /**
         * Sets state of the search field to provided value.
         *
         * @param {Boolean} isActive
         */
        onFocus: function (item, event) {
            this.setActiveState(event, true);
        },

        /**
         * Sets state of the search field to provided value.
         *
         * @param {Boolean} isActive
         */
        setActiveState: function (event, isActive) {
            if (!window.shippingBlockSts) {
                this.searchForm.toggleClass('active', isActive);
            }
            if (this.isExpandable) {
                $(event.target).attr('aria-expanded', isActive);
            }
            if (isActive) {
                $(event.target).parents('td').find(this.destinationSelector).show();
            } else {
                $(event.target).parents('td').find(this.destinationSelector).hide();
            }
        },

        /**
         * @private
         * @param {Event} event - The selected event
         * @return {Element} The first element in the suggestion list.
         */
        _getFirstVisibleElement: function (event) {
            var indexId = $(event.target).parents('td').find('.search-autocomplete').attr('id');
            return this.responseList[indexId].indexList ? this.responseList[indexId].indexList.first() : false;
        },

        /**
         * @private
         * @param {Event} event - The selected event
         * @return {Element} The last element in the suggestion list.
         */
        _getLastElement: function (event) {
            var indexId = $(event.target).parents('td').find('.search-autocomplete').attr('id');
            return this.responseList[indexId].indexList ? this.responseList[indexId].indexList.last() : false;
        },

        /**
         * @private
         * @param {Boolean} show - Set attribute aria-haspopup to "true/false" for element.
         */
        _updateAriaHasPopup: function (event, show) {
            if (show) {
                $(event.target).attr('aria-haspopup', 'true');
            } else {
                $(event.target).attr('aria-haspopup', 'false');
            }
        },

        /**
         * Clears the item selected from the suggestion list and resets the suggestion list.
         * @private
         * @param {Event} event - The selected event
         * @param {Boolean} all - Controls whether to clear the suggestion list.
         */
        _resetResponseList: function (event, all) {
            var indexId = $(event.target).parents('td').find('.search-autocomplete').attr('id');
            this.responseList[indexId].selected = null;

            if (all === true) {
                this.responseList[indexId].indexList = null;
            }
        },

        /**
         * Executes when keys are pressed in the search input field. Performs specific actions
         * depending on which keys are pressed.
         * @private
         * @param {Event} event - The key down event
         * @return {Boolean} Default return type for any unhandled keys
         */
        onKeyDown: function (item, event) {
            var keyCode = event.keyCode || event.which;
            var thisSelector = $(event.target);
            var indexId = thisSelector.parents('td').find('.search-autocomplete').attr('id');

            switch (keyCode) {
                case $.ui.keyCode.HOME:
                    this._getFirstVisibleElement(event).addClass(this.selectClass);
                    this.responseList[indexId].selected = this._getFirstVisibleElement(event);
                    break;

                case $.ui.keyCode.END:
                    this._getLastElement(event).addClass(this.selectClass);
                    this.responseList[indexId].selected = this._getLastElement(event);
                    break;

                case $.ui.keyCode.ESCAPE:
                    this._resetResponseList(event, true);
                    thisSelector.parents('td').find(this.destinationSelector).hide();
                    break;

                case $.ui.keyCode.ENTER:
                    if (this.responseList[indexId].selected) {
                        thisSelector.val(this.responseList[indexId].selected.find('.qs-option-name').text());
                    }
                    this._updateAriaHasPopup(this, false);
                    break;

                case $.ui.keyCode.DOWN:
                    if (this.responseList[indexId].indexList) {
                        if (!this.responseList[indexId].selected) { //eslint-disable-line max-depth
                            this._getFirstVisibleElement(event).addClass(this.selectClass);
                            this.responseList[indexId].selected = this._getFirstVisibleElement(event);
                        } else if (!this._getLastElement(event).hasClass(this.selectClass)) {
                            this.responseList[indexId].selected = this.responseList[indexId].selected
                                .removeClass(this.selectClass).next().addClass(this.selectClass);
                        } else {
                            this.responseList[indexId].selected.removeClass(this.selectClass);
                            this._getFirstVisibleElement(event).addClass(this.selectClass);
                            this.responseList[indexId].selected = this._getFirstVisibleElement(event);
                        }
                        thisSelector.val(this.responseList[indexId].selected.find('.qs-option-name').text());
                        thisSelector.parents('td').find('.wk_product_item').val(
                            this.responseList[indexId].selected.attr('data-id')
                        );
                        thisSelector.attr('aria-activedescendant', this.responseList[indexId].selected.attr('id'));
                    }
                    break;

                case $.ui.keyCode.UP:
                    if (this.responseList[indexId].indexList !== null) {
                        if (!this._getFirstVisibleElement(event).hasClass(this.selectClass)) {
                            this.responseList[indexId].selected = this.responseList[indexId].selected
                                .removeClass(this.selectClass).prev().addClass(this.selectClass);
                        } else {
                            this.responseList[indexId].selected.removeClass(this.selectClass);
                            this._getLastElement(event).addClass(this.selectClass);
                            this.responseList[indexId].selected = this._getLastElement(event);
                        }
                        thisSelector.val(this.responseList[indexId].selected.find('.qs-option-name').text());
                        thisSelector.parents('td').find('.wk_product_item').val(this.responseList[indexId].selected.attr('data-id'));
                        thisSelector.attr('aria-activedescendant', this.responseList[indexId].selected.attr('id'));
                    }
                    break;
                default:
                    return true;
            }
        },

        /**
         * Executes when the value of the search input field changes. Executes a GET request
         * to populate a suggestion list based on entered text. Handles click (select), hover,
         * and mouseout events on the populated suggestion list dropdown.
         * @private
         */
        onPropertyChange: function (item, event) {
            var searchField = $(event.target),
                clonePosition = {
                    position: 'absolute',
                    width: searchField.outerWidth()
                },
                source = this.template,
                template = mageTemplate(source),
                dropdown = $('<ul role="listbox"></ul>'),
                value = searchField.val(),
                indexId = searchField.parents('td').find('.search-autocomplete').attr('id'),
                formKey = $('#wk-qo-form-key').val(),
                searchFieldIndexId = searchField.attr('data-index');

            $('#' + indexId).html('');
            this.responseList[indexId] = {
                indexList: null,
                optionsListHtml: {},
                selected: null
            }
            var responseListArr = this.responseList[indexId];

            this.submitBtn.disabled = isEmpty(value);

            if (value.length >= parseInt(this.minSearchLength, 10)) {
                this.isAjaxLoader(true);
                if (searchAjaxRequest) {
                    searchAjaxRequest.abort();
                }
                searchAjaxRequest = $.getJSON(this.url, {
                    q: value
                }, $.proxy(function (data) {
                    searchAjaxRequest = null;
                    if (data.size > 0) {
                        $.each(data.products, function (index, element) {
                            var html;

                            element.index = searchFieldIndexId;
                            element.isOption = element.is_options;
                            html = template({
                                data: element
                            });
                            dropdown.append(html);
                            dropdown.find('#qs-option-' + element.index + element.entity_id).find('.qs-option-name').html(element.name);
                            dropdown.find('#product-amount-' + element.index + element.entity_id).html(element.price_html);
                        });

                        this.isAjaxLoader(false);

                        this.responseList[indexId].indexList = searchField.parents('td')
                            .find(this.destinationSelector)
                            .html(dropdown)
                            .css(clonePosition)
                            .show()
                            .find(this.responseFieldElements + ':visible');

                        this._resetResponseList(event, false);
                        searchField.parents('td').find('.wk_product_item').val('');
                        searchField.removeAttr('aria-activedescendant');

                        if (this.responseList[indexId].indexList.length) {
                            this._updateAriaHasPopup(this, true);
                        } else {
                            this._updateAriaHasPopup(this, false);
                        }

                        this.responseList[indexId].indexList
                            .on('click', function (e) {
                                var productId = $(e.currentTarget).attr('data-id'),
                                    typeId = $(e.currentTarget).attr('data-type');
                                var thisthis = this;
                                var price = $(e.currentTarget).find('.amount').attr('data-price-amount');
                                thisthis.responseList[indexId].selected = $(e.currentTarget);
                                searchField.parents('td').find('.wk_product_item').val(productId);
                                searchField.val(thisthis.responseList[indexId].selected.find('.qs-option-name').text());
                                searchField.parents('tr').find('.wk-product-qty-box').val(1);
                                searchField.parents('tr').find('.wk_product_quote_item_qty').val(1);
                                if ($(e.currentTarget).attr('data-option-available') == '1') {
                                    // getting product options data
                                    this.isAjaxLoader(true);
                                    if (typeId == 'configurable') {
                                        this.productOptionsUrl = this.configurableProductOptionsUrl;
                                    } else if (typeId == 'giftcard') {
                                        this.productOptionsUrl = this.giftcardproductOptionsUrl;
                                    } else {
                                        this.productOptionsUrl = this.productBundDownGroupProductOptionsUrl;
                                    }
                                    $.when(
                                        // get selected product options
                                        $.getJSON(this.productOptionsUrl, {
                                            product: productId,
                                            typeId: typeId,
                                            form_key: formKey
                                        }, $.proxy(function (data) {
                                            this.isAjaxLoader(false);
                                            if (data.error) {
                                                alert({
                                                    content: data.message
                                                });
                                            } else {
                                                this.responseList[indexId].optionsListHtml[searchFieldIndexId + productId] = data.detail_html + data.options_html;
                                            }
                                        }, this))
                                    ).then(function (data, textStatus, jqXHR) {
                                        thisthis.isAjaxLoader(false);
                                        var currentRowIdArr = indexId.split('wk_search_autocomplete');
                                        var currentRowId = '';
                                        if (currentRowIdArr[1]) {
                                            currentRowId = currentRowIdArr[1];
                                        }
                                        $('.wk-product-detail-section-wrap').parents('.modal-popup').remove();
                                        if (thisthis.responseList[indexId].optionsListHtml[currentRowId + productId]) {
                                            $('#wk-quick-order-form').append(
                                                $('<form>')
                                                .addClass('wk-product-detail-section-wrap')
                                                .attr('name', 'wk-product-detail-form-' + currentRowId + productId)
                                                .attr('id', 'wk-product-detail-form-' + currentRowId + productId)
                                                .attr('method', 'post')
                                                .attr('enctype', 'multipart/form-data')
                                                .html(
                                                    '<div id="product_addtocart_form">' + thisthis.responseList[indexId].optionsListHtml[currentRowId + productId] + '</div>'
                                                )
                                            );
                                            $('body').trigger('contentUpdated');
                                            $('#wk-product-detail-form-' + currentRowId + productId).mage('validation', {});
                                            var thisObj = thisthis;
                                            var options = {
                                                type: 'popup',
                                                responsive: true,
                                                innerScroll: true,
                                                width: '200px',
                                                title: $t('Please select required options.'),
                                                buttons: [{
                                                    text: $.mage.__('Add to Cart'),
                                                    class: 'wk-add-to-cart',
                                                    click: function () {
                                                        var formId = "wk-product-detail-form-" + currentRowId + productId;
                                                        if ($("#" + formId).valid() !== false) {
                                                            var formData = new FormData($("#" + formId)[0]);
                                                            formData.append('product', productId);
                                                            formData.append('form_key', formKey);
                                                            formData.append('current_url', thisObj.currentUrl);

                                                            thisObj.methodProductAddToQuote(
                                                                thisObj,
                                                                searchField,
                                                                formData,
                                                                formId
                                                            );

                                                        }
                                                    }
                                                }]
                                            };
                                            var productDetailModel = $('#wk-product-detail-form-' + currentRowId + productId);
                                            modal(options, productDetailModel);
                                            productDetailModel.modal('openModal');
                                        } else {
                                            var formData = {
                                                product: productId,
                                                form_key: $('#wk-qo-form-key').val(),
                                                current_url: thisthis.currentUrl
                                            }
                                            thisthis.ProductAddToQuote(
                                                thisthis,
                                                searchField,
                                                formData
                                            );
                                        }
                                    });
                                } else {
                                    var formData = {
                                        product: productId,
                                        form_key: $('#wk-qo-form-key').val(),
                                        current_url: thisthis.currentUrl
                                    }
                                    thisthis.ProductAddToQuote(
                                        thisthis,
                                        searchField,
                                        formData
                                    );
                                }
                                // hide list
                                this.setActiveState(event,false);
                                // $($(this).parents('td').find(this.destinationSelector)).hide();
                            }.bind(this))
                            .on('mouseenter mouseleave', function (e) {
                                this.responseList[indexId].indexList.removeClass(this.selectClass);
                                $(e.target).addClass(this.selectClass);
                                this.responseList[indexId].selected = $(e.target);
                                searchField.parents('td').find('.wk_product_item').val($(e.target).attr('data-id'));
                                searchField.attr('aria-activedescendant', $(e.target).attr('id'));
                            }.bind(this))
                            .on('mouseout', function (e) {
                                if (!this._getLastElement(event) &&
                                    this._getLastElement(event).hasClass(this.selectClass)) {
                                    $(e.target).removeClass(this.selectClass);
                                    this._resetResponseList(event, false);
                                }
                            }.bind(this));
                    } else {
                        this.isAjaxLoader(false);
                        alert({
                            content: this.emptyProductReturnLabel
                        });
                    }
                }, this));
            } else {
                this._resetResponseList(event, true);
                searchField.parents('td').find(this.destinationSelector).hide();
                this._updateAriaHasPopup(event, false);
                searchField.parents('td').find('.wk_product_item').val('');
                searchField.removeAttr('aria-activedescendant');
            }
        },

        onChangeProductQty: function (item, event) {
            var formKey = $('#wk-qo-form-key').val(),
                itemId = $(event.target).parents('tr').find('.wk_product_quote_item').val(),
                price = $(event.target).parents('tr').find('.wk_product_quote_item_price').val(),
                oldQty = $(event.target).parents('tr').find('.wk_product_quote_item_qty').val(),
                qty = $(event.target).val();
                if(qty == ''){
                    qty = 0;
                }
            if (itemId) {
                this.isAjaxLoader(true);
                // update product to cart
                $.getJSON(this.updateItemQtyUrl, {
                    item_id: itemId,
                    item_qty: qty,
                    form_key: formKey,
                    current_url: this.currentUrl
                }, $.proxy(function (data) {
                    this.isAjaxLoader(false);
                    if (data.error) {
                        alert({
                            content: data.message
                        });
                        $(event.target).val(oldQty);
                    } else {
                        if(qty == 0){
                            $(event.target).parents('tr').remove();
                        }
                        else{
                            $(event.target).parents('tr').find('.wk_product_quote_item_qty').val(qty);
                        }

                        customerData.reload(['cart'], true);
                        $(event.target).parents('tr').find('.wk-product-price-box').val(
                            utils.formatPrice(
                                qty * price,
                                this.priceFormat
                            )
                        );
                        var cartTotaldata = JSON.parse(data.checkout_data);
                        var shippingProcessors = [];
                        shippingRateRegistry.set(quote.shippingAddress().getCacheKey(), null);
                        shippingProcessors.default = addressProcessor;
                        shippingProcessors['customer-address'] = customerAddressProcessor;
                        var type = quote.shippingAddress().getType();
                        if (shippingProcessors[type]) {
                            shippingProcessors[type].getRates(quote.shippingAddress());
                        } else {
                            shippingProcessors.default.getRates(quote.shippingAddress());
                        }
                        quote.totals(cartTotaldata.totalsData);
                    }
                }, this));
            } else {
                $(event.target).parents('tr').remove();
            }
        },

        addCSVfile : function(){
            $(".wk-quickorder-csv-file").click();
        },

        proceedCheckout: function(event){
            var cart = customerData.get('cart'),
                customer = customerData.get('customer');

            event.preventDefault();

            if (!customer().firstname && cart().isGuestCheckoutAllowed === false) {
                authenticationPopup.showModal();

                return false;
            }
            $(event.target).attr('disabled', true);
            location.href = $('form#wk-quick-order-form').attr('action');
        },
        /**
         * add product to quick order from csv
         * @param {*} event
         */
        addProductCsv: function(event){
            var self = this;
            var fdata = new FormData();
            if($(".wk-quickorder-csv-file")[0].files.length>0)
            {fdata.append("file",$(".wk-quickorder-csv-file")[0].files[0])}
            if($('.wk-quick-order-csv-product-table-tbody tr').length>0){
                fdata.append("totalRows",$('.wk-quick-order-csv-product-table-tbody tr').length)
            }
            $.ajax({
                url: self.addProductCsvUrl,
                type: "POST",
                data: fdata,
                contentType: false,
                cache:false,
                enctype:'multipart/form-data',
                processData: false,
                success: function (response) {
                    if (response) {
                        $('body').trigger('processStop');
                        if(!response.error){
                            $.each(response, function(index,element){
                                var progressTmpl = mageTemplate('#wk-quick-order-csv-product-row-template'),
                                tmpl;

                                tmpl = progressTmpl({
                                    data: {
                                        index: index+1,
                                        item_id: element.item,
                                        name: element.name,
                                        product_id: element.product_id,
                                        qty: element.qty,
                                        sku:element.product_sku,
                                        typeId:element.typeId,
                                        has_options:element.has_options
                                    }
                                });

                                $(tmpl).appendTo('.wk-quick-order-csv-product-table-tbody');
                                $('.wk-quick-order-csv-product-table').css("display","table");
                                $('.wk-quick-order-add-tocart-btn').css("display","initial");
                                // apply binding for added
                                try {
                                    var isSaleable = true;
                                    $('#wk_csv_product_name_box' + (parseInt(index+1))).parents('tr').applyBindings();
                                    if(!element.is_saleable){
                                        $('#wk_csv_product_name_box' + (parseInt(index+1))).parents('tr').find('.quick_order_product_saleable').css('display','block');
                                        isSaleable = false;
                                    }
                                    if( isSaleable == true && element.has_options== '1'){
                                        $('#wk_csv_product_name_box' + (parseInt(index+1))).parents('tr').find('.wk-quick-order-configure-options').css('display','block');
                                    }

                                } catch (err) {
                                }

                            })
                            if($('.wk-quick-order-csv-product-table tbody').find('tr').length ==0){
                                $('.wk-quick-order-csv-product-table').css("display","none");
                                $('.wk-quick-order-add-tocart-btn').css("display","none");
                            }

                        } else {
                            customerData.set('messages', {
                                messages: [{
                                    type: 'error',
                                    text: $t('invalid skus are:'+response.error)
                                }]
                            });
                        }

                    }
                }
                , error: function (err) {
                }
            });

        },


        addCsvProductsToCart:function(){
            var self =this;
            var tableLastRow = $('.wk-quick-order-product-table tbody tr:last');
            var IndexId = tableLastRow.find('td .wk-product-search-box').data('index');
            if(!IndexId) IndexId=0;
           var table = $('.wk-quick-order-csv-product-table tbody');
           table.find('tr').each(function (i, el) {
                i = IndexId + i;
                var currentRow = $(this);
                var productName = $.trim(currentRow.find("td:eq(0)").text()),
                qty = currentRow.find("td:eq(2)").find('.wk-product-qty-box').val(),
                productId = currentRow.find("td:eq(3)").find('.wk-quick-order-configure-options').data('productid'),
                typeId = currentRow.find("td:eq(3)").find('.wk-quick-order-configure-options').data('typeid'),
                has_options = currentRow.find("td:eq(3)").find('.wk-quick-order-configure-options').data('hasoptions');
                var currentId = currentRow.find("td:eq(3)").find('.wk-quick-order-configure-options').data('index')

                if(productId !='' || typeof productId !='undefined'){
                    try{
                        if (has_options =='1' ) {
                            var formId = "wk-product-detail-form-" + currentId + productId,
                            form_key= $('#wk-qo-form-key').val();
                            $('#wk-product-detail-form-' + currentId + productId).mage('validation', {});

                                if ($("#" + formId).valid() !== false) {
                                    var formData = new FormData($("#" + formId)[0]);
                                    formData.append('product', productId);
                                    formData.append('form_key', form_key);
                                    formData.append('current_url', '');
                                    formData.append('qty', qty);

                                    self.isAjaxLoader(true);
                                    $.ajax({
                                        url: self.addProductToQuoteUrl,
                                        data: formData,
                                        type: 'post',
                                        dataType: 'json',
                                        cache: false,
                                        contentType: false,
                                        processData: false,
                                        success: function (data) {

                                            if(typeof data.grouped_items != 'undefined'){
                                                $.each(data.grouped_items, function(index, element){
                                                        var dataGrouped = data.grouped_items[index];
                                                        var dataItem = dataGrouped.item;
                                                        var IndexId = $('.wk-quick-order-product-table tbody tr:last').find('td .wk-product-search-box').data('index');

                                                        if($('.wk-quick-order-product-table tbody tr').length==0){
                                                            index = 1;
                                                        } else {
                                                            index = IndexId + 1;
                                                        }

                                                        var progressTmpl = mageTemplate('#wk-quick-order-quote-item-row-template'),
                                                        tmpl;
                                                        tmpl = progressTmpl({
                                                            data: {
                                                                index: IndexId+1,
                                                                item_id: element.item,
                                                                name: element.name,
                                                                price: element.price,
                                                                row_total: utils.formatPrice(element.price*element.qty, self.priceFormat),
                                                                product_id: element.product_id,
                                                                qty: element.qty
                                                            }
                                                        });
                                                        var quoteItemId = 'wk_quote_item' + dataItem;
                                                        if($('#' + quoteItemId).length){
                                                            var qty = parseInt($('#' + quoteItemId).parents('tr').find('.wk-product-qty-box').val()) + parseInt(dataGrouped.qty);
                                                            $('#' + quoteItemId).parents('tr').find('.wk-product-qty-box').val(qty);
                                                            $('#' + quoteItemId).parents('tr')
                                                            .find('.wk-product-price-box')
                                                            .val(
                                                                utils.formatPrice(
                                                                    qty*dataGrouped.price,
                                                                    self.priceFormat
                                                                )
                                                            ).attr('disabled', 'disabled');
                                                        }else{
                                                            $(tmpl).appendTo('.wk-quick-order-product-table-tbody');
                                                        }


                                                        // apply binding for added
                                                        try {
                                                            $('#wk_search_box' + parseInt(IndexId+1)).parents('tr').applyBindings();
                                                        } catch (err) {
                                                        }

                                                })
                                                var searchField = $('#wk_search_box' + (parseInt(IndexId+1)));
                                                el.remove();
                                                self.afterCsvOperation(self, searchField, data, formId);

                                                if(table.find('.wk-quick-order-csv-row').length == 0){
                                                    $('.wk-quick-order-csv-product-table').css("display","none");
                                                    $('.wk-quick-order-add-tocart-btn').css("display","none");
                                                }
                                            } else {
                                                if(!data.error){
                                                    var quoteItemId = 'wk_quote_item' + data.item;
                                                    if(!$('#'+quoteItemId).length){
                                                        var IndexId = $('.wk-quick-order-product-table tbody tr').length;
                                                        self.addProductRowFromCsv(IndexId,productName,productId,qty,data.price);
                                                    }

                                                    var searchField = $('#wk_search_box' + (parseInt(IndexId+1)));
                                                    el.remove();
                                                    self.afterCsvOperation(self, searchField, data, formId);

                                                    if(table.find('.wk-quick-order-csv-row').length == 0){
                                                        $('.wk-quick-order-csv-product-table').css("display","none");
                                                        $('.wk-quick-order-add-tocart-btn').css("display","none");
                                                    }
                                                } else {
                                                    self.isAjaxLoader(false);
                                                    el.remove();

                                                    if(table.find('.wk-quick-order-csv-row').length == 0){
                                                        $('.wk-quick-order-csv-product-table').css("display","none");
                                                        $('.wk-quick-order-add-tocart-btn').css("display","none");
                                                    }
                                                }
                                            }
                                        }
                                    });

                                } else{
                                    customerData.set('messages',{
                                        message:[{
                                            type:'error',
                                            text:$('please select options')

                                        }]

                                    })
                                }
                        } else {
                            var formData = {
                                product: productId,
                                form_key: $('#wk-qo-form-key').val(),
                                current_url: "",
                                qty:qty,
                                typeId:typeId
                            };
                            self.isAjaxLoader(true);
                            $.post(self.addProductToQuoteUrl, formData, $.proxy(function (data) {
                                if(!data.error){
                                    var quoteItemId = 'wk_quote_item' + data.item;
                                    if(!$('#'+quoteItemId).length){
                                        var IndexId = $('.wk-quick-order-product-table tbody tr').length;
                                        self.addProductRowFromCsv(IndexId,productName,productId,qty,data.price);
                                    }

                                    var searchField = $('#wk_search_box' + (parseInt(IndexId+1)));
                                    el.remove();
                                    self.afterCsvOperation(self, searchField, data, formId);

                                    if(table.find('.wk-quick-order-csv-row').length == 0){
                                        $('.wk-quick-order-csv-product-table').css("display","none");
                                        $('.wk-quick-order-add-tocart-btn').css("display","none");
                                    }
                                } else {
                                    self.isAjaxLoader(false);
                                    el.remove();

                                    if(table.find('.wk-quick-order-csv-row').length == 0){
                                        $('.wk-quick-order-csv-product-table').css("display","none");
                                        $('.wk-quick-order-add-tocart-btn').css("display","none");
                                    }
                                }


                            }),'json');

                        }
                    } catch(err){

                    }
                }
            })
            if(table.find('.wk-quick-order-csv-row').length == 0){
                $('.wk-quick-order-csv-product-table').css("display","none");
                $('.wk-quick-order-add-tocart-btn').css("display","none");
            }
        },

        addProductRowFromCsv: function(index,productName,productId,qty,price){
            var progressTmpl = mageTemplate('#wk-quick-order-quote-item-row-template'),
            tmpl;
            tmpl = progressTmpl({
                data: {
                    index: index+1,
                    name: productName,
                    product_id: productId,
                    qty: qty,
                    price:price,
                    row_total: utils.formatPrice(price*qty, self.priceFormat),

                }
            });
            $(tmpl).appendTo('.wk-quick-order-product-table-tbody');
            // apply binding for added
            try {
                $('#wk_search_box' + (parseInt(index+1))).parents('tr').applyBindings();
            } catch (err) {
            }
        },

        addConfigurableOptions: function(event){
            var self = this;
            var field = $(event.target),
                index = field.data("index"),
                product_id = field.data("productid"),
                typeId = field.data("typeid");

            var indexId =index,
                formKey = $('#wk-qo-form-key').val();
                var optionsListHtml;

            if (typeId == 'configurable') {
                self.productOptionsUrl = self.configurableProductOptionsUrl;
            } else if (typeId == 'giftcard') {
                self.productOptionsUrl = self.giftcardproductOptionsUrl;
            } else {
                self.productOptionsUrl = self.productBundDownGroupProductOptionsUrl;
            }
            $.when(
                // get selected product options
                $.getJSON(self.productOptionsUrl, {
                    product: product_id,
                    typeId: typeId,
                    form_key: formKey
                }, $.proxy(function (data) {
                    self.isAjaxLoader(false);
                    if (data.error) {
                        alert({
                            content: data.message
                        });
                    } else {
                        optionsListHtml = data.detail_html + data.options_html;
                    }
                }, this))
            ).then(function (data, textStatus, jqXHR) {
                self.isAjaxLoader(false);
                var currentRowId = index;
                    if (optionsListHtml) {
                            $('#config_span_form').append(
                                $('<form>')
                                .addClass('wk-product-detail-section-wrap')
                                .attr('name', 'wk-product-detail-form-' + currentRowId +product_id)
                                .attr('id', 'wk-product-detail-form-' + currentRowId + product_id)
                                .attr('method', 'post')
                                .attr('enctype', 'multipart/form-data')
                                .html(
                                    '<div id="product_addtocart_form">' + optionsListHtml + '</div>'
                                )
                            );
                            var qtyVal = field.closest("tr").find("td:eq(2)").find('.wk-product-qty-box').val();
                            if(typeId == "grouped") {
                                $("#wk-product-detail-form-" + currentRowId + product_id + " input").attr("value",qtyVal);
                            }
                            $('body').trigger('contentUpdated');
                            $('#wk-product-detail-form-' + currentRowId  + product_id).mage('validation', {});

                            var productDetailModel = $('#wk-product-detail-form-' + currentRowId  + product_id);
                            $('#wk_csv_product_name_box' + (parseInt(index))).parents('tr').find('.wk-quick-order-custom-options-details').html(productDetailModel);

                    }

            })
        },


        /**
         * Add quick order items row
         * @private
         */
        addCartItemRow: function () {
            var thisthis = this;
            if (Object.keys(this.cartItems).length) {
                $.each(thisthis.cartItems, function (index, element) {
                    var progressTmpl = mageTemplate('#wk-quick-order-quote-item-row-template'),
                        tmpl;
                    thisthis.index = index;
                    tmpl = progressTmpl({
                        data: {
                            index: index,
                            item_id: element.item_id,
                            name: element.name,
                            price: element.price,
                            row_total: utils.formatPrice(element.row_total, thisthis.priceFormat),
                            product_id: element.product_id,
                            qty: element.qty
                        }
                    });
                    $(tmpl).appendTo('.wk-quick-order-product-table-tbody');
                    // apply binding for added
                    try {
                        $('#wk_search_box' + index).parents('tr').applyBindings();
                    } catch (err) {
                    }
                });
            } else {
                thisthis.index = 0;
                thisthis.addProductRow();
            }
        },

        /**
         * Add quick order product row
         * @private
         */
        addProductRow: function () {
            var progressTmpl = mageTemplate('#wk-quick-order-product-row-template'),
                tmpl;
            var tableLastRow = $('.wk-quick-order-product-table tbody tr:last');
            var IndexId = tableLastRow.find('td .wk-product-search-box').data('index');
            if(IndexId != undefined || IndexId != null){
                this.index = IndexId+1
            } else {
                this.index = this.index + 1;
            }

            tmpl = progressTmpl({
                data: {
                    index: this.index
                }
            });
            $(tmpl).appendTo('.wk-quick-order-product-table-tbody');
            // apply binding for added
            try {
                $('#wk_search_box' + this.index).parents('tr').applyBindings();
            } catch (err) {
            }
        },

        /**
         * Remove quick order product row
         * @private
         */
        removeProductRow: function (item, event) {
            var formKey = $('#wk-qo-form-key').val(),
                itemId = $(event.target).parents('tr').find('.wk_product_quote_item').val();
            if (itemId) {
                this.isAjaxLoader(true);
                // add selected product to cart
                $.getJSON(this.removeProductFromQuoteUrl, {
                    id: itemId,
                    form_key: formKey,
                    current_url: this.currentUrl
                }, $.proxy(function (data) {
                    $(event.target).parents('tr').remove();
                    this.isAjaxLoader(false);
                    if (data.error) {
                        alert({
                            content: data.message
                        });
                    } else {
                        customerData.reload(['cart'], true);
                        var cartTotaldata = JSON.parse(data.checkout_data);
                        var shippingProcessors = [];
                        shippingRateRegistry.set(quote.shippingAddress().getCacheKey(), null);
                        shippingProcessors.default = addressProcessor;
                        shippingProcessors['customer-address'] = customerAddressProcessor;
                        var type = quote.shippingAddress().getType();
                        if (shippingProcessors[type]) {
                            shippingProcessors[type].getRates(quote.shippingAddress());
                        } else {
                            shippingProcessors.default.getRates(quote.shippingAddress());
                        }
                        quote.totals(cartTotaldata.totalsData);
                        this.submitBtn.disabled = isEmpty(cartTotaldata.quoteItemData);
                    }
                }, this));
            } else {
                $(event.target).parents('tr').remove();
            }
        },

        methodProductAddToQuote: function (thisthis, searchField, formData, formId = null) {
            thisthis.isAjaxLoader(true);
            // add selected product to cart
            $.ajax({
                url: thisthis.addProductToQuoteUrl,
                data: formData,
                type: 'post',
                dataType: 'json',
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {

                    if(typeof data.grouped_items != 'undefined'){
                        var group_product_id  = searchField.parents('td').find('.wk_product_item').val();
                        if(group_product_id == data.group_product_id){
                            searchField.parents('tr').find('.wk-quick-order-product-remove').click();
                        }
                        $.each(data.grouped_items, function(index, element){
                                var progressTmpl = mageTemplate('#wk-quick-order-quote-item-row-template'),
                                tmpl;

                                tmpl = progressTmpl({
                                    data: {
                                        index: index+1,
                                        item_id: element.item,
                                        name: element.name,
                                        price: element.price,
                                        row_total: utils.formatPrice(element.price*element.qty, thisthis.priceFormat),
                                        product_id: element.product_id,
                                        qty: element.qty
                                    }
                                });
                                $(tmpl).appendTo('.wk-quick-order-product-table-tbody');
                                // apply binding for added
                                try {
                                    $('#wk_search_box' + parseInt(index+1)).parents('tr').applyBindings();
                                } catch (err) {
                                }

                        })
                        thisthis.afterOperation(thisthis, searchField, data, formId);
                    }
                    else{
                        thisthis.afterOperation(thisthis, searchField, data, formId);
                    }
                    $('body').trigger('contentUpdated');
                }
            });
        },

        ProductAddToQuote: function (thisthis, searchField, formData, formId = null) {
            thisthis.isAjaxLoader(true);
            // add selected product to cart
            $.post(thisthis.addProductToQuoteUrl, formData, $.proxy(function (data) {
                thisthis.afterOperation(thisthis, searchField, data, formId);

            }),'json');
        },

        afterCsvOperation: function (thisthis, searchField, data, formId = null) {
            if (formId) {
                $("#" + formId).parents('.modal-inner-wrap')
                    .find('.action-close')
                    .trigger('click');
            }
            thisthis.isAjaxLoader(false);

            if (data.error) {
                alert({
                    content: data.message
                });
                searchField.val('');
                searchField.parents('tr').find('.wk-product-qty-box').val('');
                searchField.parents('tr').find('.wk-product-price-box').val('N/A');
                searchField.trigger('focus');
            } else {
                if (data.item) {
                    var quoteItemId = 'wk_quote_item' + data.item;
                    if ($('#' + quoteItemId).length) {

                    } else {
                        searchField.parents('tr')
                            .find('.wk_product_quote_item')
                            .val(data.item)
                            .attr('id', quoteItemId);
                        searchField.parents('tr')
                            .find('.wk_product_quote_item_price')
                            .val(data.price);
                        searchField.attr('disabled', 'disabled');
                    }
                    if (data.price) {
                        $('#' + quoteItemId).parents('tr')
                            .find('.wk-product-price-box')
                            .val(
                                utils.formatPrice(
                                    data.price,
                                    thisthis.priceFormat
                                )
                            ).attr('disabled', 'disabled');
                    }
                    if (data.qty) {
                        $('#' + quoteItemId).parents('tr')
                            .find('.wk-product-qty-box')
                            .val(data.qty);
                    }
                } else {
                    if (data.qty) {
                        searchField.parents('tr')
                            .find('.wk-product-qty-box')
                            .val(data.qty);
                    }
                    if (data.price) {
                        searchField.parents('tr').find('.wk-product-price-box').val(
                            utils.formatPrice(
                                data.price,
                                thisthis.priceFormat
                            )
                        ).attr('disabled', 'disabled');
                    }
                }
                // update mini cart
                customerData.reload(['cart'], true);
                // get updated totals
                //$('.wk-quick-order-product-table-tbody').load();
                var cartTotaldata = JSON.parse(data.checkout_data);
                // get updated shipping rates array data
                var shippingProcessors = [];
                if (quote.shippingAddress()) {
                    shippingRateRegistry.set(quote.shippingAddress().getCacheKey(), null);
                    shippingProcessors.default = addressProcessor;
                    shippingProcessors['customer-address'] = customerAddressProcessor;
                    var type = quote.shippingAddress().getType();
                    if (shippingProcessors[type]) {
                        shippingProcessors[type].getRates(quote.shippingAddress());
                    } else {
                        shippingProcessors.default.getRates(quote.shippingAddress());
                    }
                } else {
                    shippingProcessors.default = addressProcessor;
                    shippingProcessors['customer-address'] = customerAddressProcessor;
                    shippingProcessors.default.getRates(quote.shippingAddress());
                }
                this.submitBtn.disabled = isEmpty(cartTotaldata.quoteItemData);
                // update total
                quote.totals(cartTotaldata.totalsData);
                thisthis.isQuoteExist(true);
            }
        },

        afterOperation: function (thisthis, searchField, data, formId = null) {
            if (formId) {
                $("#" + formId).parents('.modal-inner-wrap')
                    .find('.action-close')
                    .trigger('click');
            }
            thisthis.isAjaxLoader(false);
            if (data.error) {
                alert({
                    content: data.message
                });
                searchField.val('');
                searchField.parents('tr').find('.wk-product-qty-box').val('');
                searchField.parents('tr').find('.wk-product-price-box').val('N/A');
                searchField.trigger('focus');
            } else {
                if (data.item) {
                    var quoteItemId = 'wk_quote_item' + data.item;
                    if ($('#' + quoteItemId).length) {
                        searchField.parents('tr')
                            .find('.wk-product-search-box')
                            .val('');
                        searchField.parents('tr')
                            .find('.wk-product-qty-box')
                            .val('');
                        searchField.parents('tr')
                            .find('.wk-product-price-box')
                            .val('N/A')
                            .attr('disabled', 'disabled');
                    } else {
                        searchField.parents('tr')
                            .find('.wk_product_quote_item')
                            .val(data.item)
                            .attr('id', quoteItemId);
                        searchField.parents('tr')
                            .find('.wk_product_quote_item_price')
                            .val(data.price);
                        searchField.attr('disabled', 'disabled');
                    }
                    if (data.price) {
                        $('#' + quoteItemId).parents('tr')
                            .find('.wk-product-price-box')
                            .val(
                                utils.formatPrice(
                                    data.price,
                                    thisthis.priceFormat
                                )
                            ).attr('disabled', 'disabled');
                    }
                    if (data.qty) {
                        $('#' + quoteItemId).parents('tr')
                            .find('.wk-product-qty-box')
                            .val(data.qty);
                    }
                } else {
                    if (data.qty) {
                        searchField.parents('tr')
                            .find('.wk-product-qty-box')
                            .val(data.qty);
                    }
                    if (data.price) {
                        searchField.parents('tr').find('.wk-product-price-box').val(
                            utils.formatPrice(
                                data.price,
                                thisthis.priceFormat
                            )
                        ).attr('disabled', 'disabled');
                    }
                }
                // update mini cart
                customerData.reload(['cart'], true);
                // get updated totals
                $('.wk-quick-order-product-table-tbody').load();
                var cartTotaldata = JSON.parse(data.checkout_data);
                // get updated shipping rates array data
                var shippingProcessors = [];
                if (quote.shippingAddress()) {
                    shippingRateRegistry.set(quote.shippingAddress().getCacheKey(), null);
                    shippingProcessors.default = addressProcessor;
                    shippingProcessors['customer-address'] = customerAddressProcessor;
                    var type = quote.shippingAddress().getType();
                    if (shippingProcessors[type]) {
                        shippingProcessors[type].getRates(quote.shippingAddress());
                    } else {
                        shippingProcessors.default.getRates(quote.shippingAddress());
                    }
                } else {
                    shippingProcessors.default = addressProcessor;
                    shippingProcessors['customer-address'] = customerAddressProcessor;
                    shippingProcessors.default.getRates(quote.shippingAddress());
                }
                // update total
                quote.totals(cartTotaldata.totalsData);
                thisthis.isQuoteExist(true);
            }
        }
    });
});
