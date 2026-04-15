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
        'https://checkout.epayco.co/checkout-v2.js',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/modal/alert',
    ],
    function ($,Component,url,quote,checkoutData,messageContainer, urlBuilder, customer, placeOrderService, ePayco, fullScreenLoader, alert) {
        'use strict';
        return Component.extend({
            defaults: {
                self:this,
                template: 'Pago_Paycoagregador/payment/epaycoagregador'
            },
            redirectAfterPlaceOrder: false,
            renderCheckout: function() {
                try {
                    fullScreenLoader.startLoader();
                    var getQuoteId = this.getQuoteId();
                    var _this = this;

                    if (getQuoteId) {
                        var storedQuoteId = localStorage.getItem("epaycoagregador_quote_id");
                        if (storedQuoteId == getQuoteId) {
                            localStorage.setItem("epaycoagregador_quote_id", getQuoteId);
                            var data = localStorage.getItem("epaycoagregador_invoice");
                            if (data) {
                                _this.onEpaycoSuccess(data, _this, getQuoteId);
                            } else {
                                fullScreenLoader.stopLoader();
                                alert({
                                    content: $.mage.__('Sorry, something went wrong. Please try again later.')
                                });
                            }
                        } else {
                            $.ajax({
                                url: url.build("responseAgregador/paymentagregador/index"),
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                method: 'POST',
                                async: false,
                                data:  { "order_id": getQuoteId },
                                success: function(data) {
                                    _this.onEpaycoSuccess(data, _this, getQuoteId);
                                },
                                error: function(error) {
                                    fullScreenLoader.stopLoader();
                                    alert({
                                        content: $.mage.__('Sorry, something went wrong. Please try again later.')
                                    });
                                    console.log('error: ' + error);
                                }
                            });
                        }
                    } else {
                        fullScreenLoader.stopLoader();
                        alert({
                            content: $.mage.__('Sorry, something went wrong. Please try again later.')
                        });
                    }
                } catch (error) {
                    fullScreenLoader.stopLoader();
                    alert({
                        content: $.mage.__('Sorry, something went wrong. Please try again later.')
                    });
                    console.log('error: ' + error);
                }
            },
            onEpaycoSuccess: function(data, _this, getQuoteId){
                //$('#loader-agregador').trigger('processStart');
                if(data.success){
                    var ip = this.getCustomerIp();
                    var checkoutConfig= window.checkoutConfig;
                    let stringNumber = "000000000";
                    let increment_id = data.increment_id;
                    let number = parseInt(stringNumber, 10);
                    let result = number + data.order_id;
                    //let invoice = result.toString().padStart(9, '0');
                    let invoice = increment_id;
                    localStorage.setItem("epaycoagregador_invoice", JSON.stringify(data));
                    var shippingAddress = quote.shippingAddress();
                    var billingAddress = quote.billingAddress();
                    var docType='';
                    var mobile = shippingAddress.telephone??billingAddress.telephone;
                    var doc= '';
                    var country = shippingAddress.countryId??billingAddress.countryId;
                    var email = quote.guestEmail;
                    var name_billing = shippingAddress.firstname??billingAddress.firstname+" "+shippingAddress.lastname??billingAddress.lastname;
                    var address_billing = shippingAddress.street[0]??billingAddress.street[0];
                    var currency = checkoutConfig.quoteData.store_currency_code;
                    var totals = quote.getTotals();
                    var amount = 0;
                    amount = totals._latestValue.base_grand_total;
                    var taxes = 0;
                    taxes = totals._latestValue.base_tax_amount;
                    var tax_base = 0;
                    tax_base = amount - taxes;
                    var items = '';
                    var test = false;
                    for(var i = 0; i < window.checkoutConfig.quoteItemData.length; i++){
                        if(window.checkoutConfig.totalsData.items.length==1){
                            items=window.checkoutConfig.quoteItemData[i].product.name;
                        }else{
                            items += window.checkoutConfig.quoteItemData[i].product.name+',';
                        }
                    }
                    if(window.checkoutConfig.payment.epaycoagregador.payco_test === "1"){
                        var test = true;
                    }
                    let typeCheckout = window.checkoutConfig.payment.epaycoagregador.vertical_cs === 'true' ? 'standard' : 'onepage';
                    var lang = checkoutConfig.payment.epaycoagregador.language_cs;
                    //let date_ = new Date().getTime();
                    var data={
                        //Parametros compra (obligatorio)
                        name: items,
                        description: items,
                        invoice: invoice,
                        currency: currency,
                        amount: parseFloat(amount),
                        taxBase: parseFloat(tax_base),
                        tax: parseFloat(taxes),
                        country: country,
                        lang: lang,
                        extras:{
                            extra1: data.order_id,
                            extra2: getQuoteId
                        },
                        confirmation:url.build("confirmationAgregador/epaycoagregador/index"),
                        response: url.build("confirmationAgregador/epaycoagregador/index"),
                        forceResponse:false,//no mostrar el detalle de la transaccion
                        noRedirectOnClose: false,
                        uniqueTransactionPerBill:false,
                        //Atributos cliente
                        billing:{
                            email: email,
                            name: name_billing,
                            address: address_billing,
                            mobilePhone: mobile,
                            typeDoc: docType,
                            numberDoc: doc,
                        },
                        method: "POST",
                        autoClick:true,
                        ip: ip,
                        test: test,
                        checkout_version:"2",
                        extrasEpayco:{
                            extra5:"P29"
                        }
                    };
                    const apiKey = window.checkoutConfig.payment.epaycoagregador.payco_public_key.trim();
                    const privateKey = window.checkoutConfig.payment.epaycoagregador.payco_private_key.trim();
                    _this.makePayment(privateKey,apiKey,data, typeCheckout, test)
                }else{
                    fullScreenLoader.stopLoader();
                    alert({
                        content: $.mage.__('Sorry, something went wrong. Please try again later.')
                    });
                }
            },
            getCode: function() {
                return 'epaycoagregador';
            },
            getQuoteData: function(){
                return window.checkoutConfig.payment.epaycoagregador.getQuoteData;
            },
            getQuoteIdData: function(){
                return window.checkoutConfig.payment.epaycoagregador.getQuoteIdData;
            },
            getQuoteId: function(){
                return window.checkoutConfig.payment.epaycoagregador.getQuoteId;
            },
            getdisplayTitle: function () {
                return window.checkoutConfig.payment.epaycoagregador.payco_title;
            },
            text: function(){
                return window.checkoutConfig.payment.epaycoagregador.text;
            },
            getCustomerIp: function(){
                return window.checkoutConfig.payment.epaycoagregador.getCustomerIp;
            },
            loadScript: function (url,callback){
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = url;
                script.onload = function() {
                    if (callback) {
                        callback();
                    }
                };
                script.onerror = function() {
                    console.error('Error loading script:', url);
                };
                document.head.appendChild(script);
            },
            makePayment:  function (privatekey, apikey, info, external, test) {
                const _this = this;
                const headers = { "Content-Type": "application/json" };
                const payment = function () {
                    return fetch("https://apify.epayco.co/payment/session/create", {
                        method: "POST",
                        body: JSON.stringify(info),
                        headers
                    })
                    .then(res => res.json());
                };
                return _this.getBearerToken(privatekey, apikey)
                    .then(token => {
                        headers["Authorization"] = "Bearer " + token;
                        return payment();
                    })
                    .then(session => {
                        if (session.data && session.data.sessionId) {
                            localStorage.removeItem("sessionPaymentAgregador");
                            localStorage.setItem("sessionPaymentAgregador", session.data.sessionId);
                            const handlerNew = window.ePayco.checkout.configure({
                                sessionId: session.data.sessionId,
                                type: external,
                                test: test,
                            });
                            fullScreenLoader.stopLoader();
                            handlerNew.open();
                        } else {
                            fullScreenLoader.stopLoader();
                            alert({
                                content: $.mage.__('Sorry, something went wrong. Please try again later.')
                            });
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        fullScreenLoader.stopLoader();
                        alert({
                            content: $.mage.__('Sorry, something went wrong. Please try again later.')
                        });
                    });
            },
            getBearerToken: function (priv,pub) {
                const cacheKey = 'epaycoBearer';
                const expKey = cacheKey + ':exp';
                const cached = localStorage.getItem(cacheKey);
                const exp = parseInt(localStorage.getItem(expKey) || '0', 10);
                if (cached && Date.now() < exp) return Promise.resolve(cached);

                return fetch("https://apify.epayco.co/login", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Authorization": "Basic " + btoa(`${pub}:${priv}`)
                    }
                })
                .then(r => r.json())
                .then(json => {
                    const token = json.token || json.access_token;
                    if (!token) throw new Error("No se recibió token");
                    const ttlMs = (14 * 60 * 1000) - 15000;
                    localStorage.setItem(cacheKey, token);
                    localStorage.setItem(expKey, String(Date.now() + ttlMs));
                    return token;
                });
            },
            afterPlaceOrder: function () {
                this.renderCheckout();
            },
        });
    }
);
