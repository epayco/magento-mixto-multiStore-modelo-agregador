<?php
/**
 * Module for payment provide by ePayco
 * Copyright (C) 2017
 *
 * This file is part of EPayco/EPaycoPayment.
 *
 * EPayco/EPaycoPayment is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Pago\Paycoagregador\Controller\Epaycoagregador;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Model\Order;

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $resultPageFactory;
    protected $resultJsonFactory;
    protected $checkoutSession;
    protected $orderFactory;
    protected $cartManagement;
    protected $quote;
    protected $resultRedirect;
    protected $_curl;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\App\Helper\Context $contextApp,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Pago\Paycoagregador\Controller\PaymentController $payment_controller,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Quote\Model\QuoteFactory $quoteFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->cartManagement = $cartManagement;
        $this->quote = $quote;
        $this->_curl = $curl;
        $this->contextApp = $contextApp;
        $this->scopeConfig = $scopeConfig;
        $this->paymentController = $payment_controller;
        $this->orderRepository = $orderRepository;
        $this->quoteFactory = $quoteFactory;

        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $url = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
        $server_name = str_replace('/confirmationAgregador/epaycoagregador/index','/checkout/onepage/success/',$url);
        $new_url = $server_name;
        $result = $this->resultJsonFactory->create();
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $urlRedirect = trim($this->scopeConfig->getValue('payment/epaycoagregador/payco_callback',$storeScope));
        $pendingOrderState = "pending";

        if(isset($_GET['ref_payco'])){
            $ref_payco = $_GET['ref_payco'];

            $this->_curl->get("https://secure.epayco.co/validation/v1/reference/" . $ref_payco);
            $response = $this->_curl->getBody();
            $dataTransaction = json_decode($response);

            if(isset($dataTransaction) && isset($dataTransaction->success) && $dataTransaction->success){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $orderId = (Integer)$dataTransaction->data->x_extra1;
                $code = $dataTransaction->data->x_cod_response;
                $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id',$orderId);

                if($code == 1){
                    $order->setState(Order::STATE_PROCESSING, true);
                    $order->setStatus(Order::STATE_PROCESSING, true);
                } else if($code == 3){
                    $order->setState($pendingOrderState, true);
                    $order->setStatus($pendingOrderState, true);
                } else if($code == 2 ||
                    $code == 4 ||
                    $code == 6 ||
                    $code == 9 ||
                    $code == 10 ||
                    $code == 11
                ){
                    $order->setState(Order::STATE_CANCELED, true);
                    $order->setStatus(Order::STATE_CANCELED, true);
                } else if($code == 12)  {
                    $order->setState(Order::STATUS_FRAUD, true);
                    $order->setStatus(Order::STATUS_FRAUD, true);
                }

                try{
                    $this->orderRepository->save($order);
                } catch(\Exception $e){
                    if($urlRedirect != ''){
                        return $this->resultRedirectFactory->create()->setUrl($urlRedirect);
                    } else {
                        return $this->resultRedirectFactory->create()->setUrl($new_url);
                    }
                }

                if($urlRedirect != ''){
                    return $this->resultRedirectFactory->create()->setUrl($urlRedirect);
                } else {
                    return $this->resultRedirectFactory->create()->setUrl($new_url);
                }
            } else {
                if($urlRedirect != ''){
                    return $this->resultRedirectFactory->create()->setUrl($urlRedirect);
                } else {
                    return $this->resultRedirectFactory->create()->setUrl($new_url);
                }
            }
        } else if(isset($_REQUEST['x_ref_payco'])){
            $x_ref_payco = trim($_REQUEST['x_ref_payco']);
            $x_amount = trim($_REQUEST['x_amount']);
            $x_signature = trim($_REQUEST['x_signature']);
            $x_extra1 = trim($_REQUEST['x_extra1']);
            $x_currency_code = trim($_REQUEST['x_currency_code']);
            $x_transaction_id = trim($_REQUEST['x_transaction_id']);
            $p_cust_id_cliente = trim($this->scopeConfig->getValue('payment/epaycoagregador/payco_merchant',$storeScope));
            $p_key = trim($this->scopeConfig->getValue('payment/epaycoagregador/payco_key',$storeScope));
            $signature  = hash('sha256', $p_cust_id_cliente . '^' . $p_key . '^' . $x_ref_payco . '^' . $x_transaction_id . '^' . $x_amount . '^' . $x_currency_code);

            if($x_signature == $signature){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $x_cod_transaction_state =trim($_REQUEST['x_cod_transaction_state']);
                $code = (Integer)$x_cod_transaction_state;
                $orderId = (Integer)$x_extra1;
                $order = $objectManager->create('Magento\Sales\Model\Order')->loadByAttribute('quote_id',$orderId);

                if($code == 1){
                    $order->setState(Order::STATE_PROCESSING, true);
                    $order->setStatus(Order::STATE_PROCESSING, true);
                } else if($code == 3){
                    $order->setState($pendingOrderState, true);
                    $order->setStatus($pendingOrderState, true);
                } else if($code == 2 ||
                        $code == 4 ||
                        $code == 6 ||
                        $code == 9 ||
                        $code == 10 ||
                        $code == 11
                ){
                    $order->setState(Order::STATE_CANCELED, true);
                    $order->setStatus(Order::STATE_CANCELED, true);
                } else if($code == 12)  {
                    $order->setState(Order::STATUS_FRAUD, true);
                    $order->setStatus(Order::STATUS_FRAUD, true);
                }

                try{
                    $order->save();
                } catch(\Exception $e){
                    return $result->setData('Error No se creo la orden');
                }

                return $result->setData('confirmed order');
            }
            else{
                return $result->setData('no entro a la signature');
            }
        } else {
            return $result->setData('No se creo la orden');
        }
    }

    public function getRealOrderId()
    {
        $lastorderId = $this->checkoutSession->getLastOrderId();
        return $lastorderId;
    }

    public function getOrder()
    {
        if ($this->checkoutSession->getLastRealOrderId()) {
            $order = $this->orderFactory->create()->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
            return $order;
        }
        return false;
    }

    public function getShippingInfo()
    {
        $order = $this->getOrder();
        if($order) {
            $address = $order->getShippingAddress();
            return $address;
        }
        return false;
    }
}
