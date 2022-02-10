/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        "jquery",
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/authentication-messages',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/place-order',
        'https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod.js?version=1643645084821'
    ],
    function ($,Component,url,quote,checkoutData,messageContainer, urlBuilder, customer, placeOrderService) {
        'use strict';
        return Component.extend({
            defaults: {
                self:this,
                template: 'Pago_Paycoagregador/payment/epaycoagregador'
            },
            redirectAfterPlaceOrder: false,
            renderCheckout: async function() {
                var button0 = document.getElementsByClassName('action primary checkout')[0];
                var button1 = document.getElementsByClassName('action primary checkout')[1];
                button0.style.disabled = true;
                button1.style.disabled = true;
                button0.disabled = true;
                button1.disabled = true;
                var countryBllg = quote.shippingAddress();
                var customerData = checkoutData.getShippingAddressFromData();
                var paymentData = {
                    method: 'epaycoagregador'
                };
                var serviceUrl, payload;
                payload = {
                    cartId: quote.getQuoteId(),
                    billingAddress: quote.billingAddress(),
                    paymentMethod: paymentData
                };

                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/carts/mine/payment-information', {});
                } else {
                    serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/payment-information', {
                        quoteId: quote.getQuoteId()
                    });
                    payload.email = quote.guestEmail;
                }
                 placeOrderService(serviceUrl, payload, messageContainer);
                var orderId = this.getOrderId();
                var getQuoteIncrement = this.getQuoteIncrementId();
                var totals = quote.getTotals();
                var quoteIdData = this.getQuoteIdData();
                var invoice;
                var settings = {
                    "url": url.build("responseAgregador/paymentagregador/index"),
                    "method": "POST",
                    "timeout": 120,
                    "async":false,
                    "headers": {
                        "X-Requested-With": "XMLHttpRequest",
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    "data": {
                        "order_id": quoteIdData
                    }
                }
                 await $.ajax({
                    url: url.build("responseAgregador/paymentagregador/index"),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    method: 'POST',
                    async: false,
                    data:  {
                        "order_id": quoteIdData
                    },
                    success: function(data){

                        console.log('processing...');
                        if(data == "warning" || data.length == 0 || data == "error" ) {
                            $.ajax(settings).done(function (response) {
                                if( response.increment_id){
                                    invoice = response.increment_id;
                                }
                            });
                        }else{
                            invoice = data.increment_id;
                        }

                       if(invoice){
                           if(window.checkoutConfig.payment.epaycoagregador.payco_test == "1"){
                               window.checkoutConfig.payment.epaycoagregador.payco_test= "true";
                               var test2 = true;
                           } else {
                               window.checkoutConfig.payment.epaycoagregador.payco_test = "false";
                               var test2 = false;
                           }
                           var handler = ePayco.checkout.configure({
                               key: window.checkoutConfig.payment.epaycoagregador.payco_public_key,
                               test:test2
                           })
                           var taxes = 0;
                           taxes = totals._latestValue.base_tax_amount
                           taxes = ''+taxes;
                           var items = '';
                           for(var i = 0; i <  window.checkoutConfig.quoteItemData.length; i++){
                               if(window.checkoutConfig.totalsData.items.length==1){
                                   items=window.checkoutConfig.quoteItemData[i].product.name;
                               }else{
                                   items += window.checkoutConfig.quoteItemData[i].product.name+',';
                               }

                           }
                           var docType='';
                           var mobile = '';
                           var doc= '';
                           var country = '';
                           //calcular base iva
                           var tax_base = 0;
                           tax_base = totals._latestValue.base_subtotal_with_discount;
                           tax_base = ''+tax_base;
                           // fin calcular base iva
                           if(!window.checkoutConfig.isCustomerLoggedIn){
                               if(customerData){
                                   var name_billing =  customerData.firstname + ' ' + customerData.lastname;
                                   var address_billing =  customerData.street[0]+ ' ' + customerData.street[1];
                                   country = customerData.country_id;
                               }else{
                                   country = 'CO';
                               }
                           } else {
                               var  name_billing = window.checkoutConfig.customerData.firstname + ' '+ window.checkoutConfig.customerData.lastname;
                               mobile = countryBllg.telephone;
                               var address_billing = countryBllg.street[0];
                               country = countryBllg.countryId;
                           }
                           var lang = '';
                           var temp = window.checkoutConfig.payment.epaycoagregador.language.split("_");
                           lang = temp[0];
                           var amount = '';
                           amount = totals._latestValue.base_grand_total;

                           var data={
                               //Parametros compra (obligatorio)
                               name: items,
                               description: items,
                               invoice: invoice,
                               currency: window.checkoutConfig.quoteData.store_currency_code,
                               amount: amount,
                               tax_base: tax_base.replace('.',','),
                               tax: taxes.replace('.',','),
                               country: country,
                               lang: lang,
                               //Onpage='false' - Standard='true'
                               external: window.checkoutConfig.payment.epaycoagregador.vertical_cs,
                               //Atributos opcionales
                               extra1: orderId,
                               extra2: invoice,
                               confirmation:url.build("confirmationAgregador/epaycoagregador/index"),
                               response: url.build("confirmationAgregador/epaycoagregador/index"),
                               //Atributos cliente
                               name_billing: name_billing,
                               address_billing: address_billing,
                               type_doc_billing: docType,
                               mobilephone_billing: mobile,
                               number_doc_billing: doc
                           };
                           button0.disabled = false;
                           button1.disabled = false;
                           button0.style.disabled = false;
                           button1.style.disabled = false;
                           handler.open(data);
                       }
                    },
                    error :function(error){

                        console.log('error: '+error);
                    }
                });

            },
            getOrderId: function(){
                return window.checkoutConfig.payment.epaycoagregador.getOrderId;
            },
            getQuoteData: function(){
                return window.checkoutConfig.payment.epaycoagregador.getQuoteData;
            },
            getStoreData: function(){
                return window.checkoutConfig.payment.epaycoagregador.getStoreData;
            },
            getOrderIncrementId: function(){
                return window.checkoutConfig.payment.epaycoagregador.getOrderIncrementId;
            },
            getQuoteIncrementId: function(){
                return window.checkoutConfig.payment.epaycoagregador.getQuoteIncrementId;
            },
            getQuoteIdData: function(){
                return window.checkoutConfig.payment.epaycoagregador.getQuoteIdData;
            },
            getdisplayTitle: function () {
                return window.checkoutConfig.payment.epaycoagregador.payco_title;
            },
            text: function(){
                return window.checkoutConfig.payment.epaycoagregador.text;
            },
            getMailingAddress: function() {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            responseAction: function(){
                return window.checkoutConfig.payment.epaycoagregador.responseAction;
            },
        });
    }
);
