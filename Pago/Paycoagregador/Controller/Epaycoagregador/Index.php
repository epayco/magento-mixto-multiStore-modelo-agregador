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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Model\Order;

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $resultPageFactory;
    protected $resultJsonFactory;
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
        \Magento\Framework\HTTP\Client\Curl $curl,
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_curl = $curl;
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
        try{
            $scopeConfig = ObjectManager::getInstance()->get(ScopeConfigInterface::class);
            $url = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
            $server_name = str_replace('/confirmationAgregador/epaycoagregador/index','/checkout/onepage/success/',$url);
            $new_url = $server_name;
            $result = $this->resultJsonFactory->create();
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            //$urlRedirect = trim($scopeConfig->getValue('payment/epayco/payco_callback',$storeScope));
            $urlRedirect = '';
            if($urlRedirect != ''){
                if(isset($_GET['ref_payco'])){
                    $urlRedirect = $urlRedirect."?ref_payco=".$_GET['ref_payco'];
                }
            }
            $pendingOrderState = Order::STATE_PENDING_PAYMENT;
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            /** @var \Magento\Sales\Api\OrderRepositoryInterface $orderRepository */
            $orderRepository = $objectManager->create(\Magento\Sales\Api\OrderRepositoryInterface::class);
            $stockRegistry = $objectManager->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
            $connection = $resource->getConnection();

            $collectionFactory = $objectManager->get(\Pago\Paycoagregador\Model\ResourceModel\OrderEpaycoAgregador\CollectionFactory::class);
            $orderEpayco = $collectionFactory->create();

            if(isset($_GET['ref_payco'])){
                $ref_payco = $_GET['ref_payco'];

                $this->_curl->get("https://secure.epayco.co/validation/v1/reference/" . $ref_payco);
                $response = $this->_curl->getBody();
                $dataTransaction = json_decode($response);

                if(isset($dataTransaction) && isset($dataTransaction->success) && $dataTransaction->success){
                    try{
                        $orderId = (Integer)$dataTransaction->data->x_extra1;
                        
                        // Validar que el orderId sea válido
                        if (!$orderId || $orderId <= 0) {
                            error_log("ePayco Agregador: ID de orden inválido: " . $orderId);
                            if($urlRedirect != ''){
                                return $this->resultRedirectFactory->create()->setUrl($urlRedirect);
                            } else {
                                return $this->resultRedirectFactory->create()->setUrl($new_url);
                            }
                        }

                        $transaction = $orderEpayco->addFieldToFilter('order', $orderId);
                        $code = $dataTransaction->data->x_cod_response;
                        $x_ref_payco = $dataTransaction->data->x_ref_payco;

                        // Validar que la orden existe antes de intentar obtenerla
                        try {
                            //$order = $orderRepository->get($orderId);
                            $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id', (Integer)$orderId);
                        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                            // La orden no existe, registrar error y continuar
                            error_log("ePayco Agregador: Orden no encontrada con ID: " . $orderId . " - Error: " . $e->getMessage());
                            if($urlRedirect != ''){
                                return $this->resultRedirectFactory->create()->setUrl($urlRedirect);
                            } else {
                                return $this->resultRedirectFactory->create()->setUrl($new_url);
                            }
                        }
                        if($code == 1){
                            if($order->getState() != "canceled"  ){
                                $order->setState(Order::STATE_PROCESSING, true);
                                $order->setStatus(Order::STATE_PROCESSING, true);
                                foreach ($transaction as $item) {
                                    $item->delete();
                                } 
                                $orderRepository->save($order);
                            }
                        } else if($code == 3){
                            $order->setState($pendingOrderState, true);
                            $order->setStatus($pendingOrderState, true);
                            foreach ($transaction as $item) {
                                $item->setData('ref_payco', $x_ref_payco);
                                $item->setData('status', 'pending');
                                $item->save(); 
                            }
                            $orderRepository->save($order);
                        } else if($code == 2 ||
                            $code == 4 ||
                            $code == 6 ||
                            $code == 9 ||
                            $code == 10 ||
                            $code == 11
                        ){
                            if($order->getState() == "pending" || 
                                $order->getState() == "pending_payment" || 
                                $order->getState() == "new" ){
                                $validate = $this->uploadStatusOrder($objectManager,$orderId);
                                if($validate){
                                    $order->setState(Order::STATE_CANCELED, true);
                                    $order->setStatus(Order::STATE_CANCELED, true);
                                    $this->uploadInventory($objectManager,$stockRegistry,$order,$orderId);
                                    $orderRepository->save($order);
                                }
                            }
                        } else if($code == 12)  {
                            if($order->getState() == "pending" || 
                                $order->getState() == "pending_payment" || 
                                $order->getState() == "new" ){
                                $validate = $this->uploadStatusOrder($objectManager,$orderId);
                                if($validate){
                                    $order->setState(Order::STATUS_FRAUD, true);
                                    $order->setStatus(Order::STATUS_FRAUD, true);
                                    $this->uploadInventory($objectManager,$stockRegistry,$order,$orderId);
                                    $orderRepository->save($order);
                                }
                            }
                        }  
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
                $x_extra2 = trim($_REQUEST['x_extra2']);
                $x_currency_code = trim($_REQUEST['x_currency_code']);
                $x_transaction_id = trim($_REQUEST['x_transaction_id']);
                $x_approval_code = trim($_REQUEST['x_approval_code']);
                $x_cod_transaction_state = trim($_REQUEST['x_cod_transaction_state']);
                $p_cust_id_cliente = trim($scopeConfig->getValue('payment/epaycoagregador/payco_merchant',$storeScope));
                $p_key = trim($scopeConfig->getValue('payment/epaycoagregador/payco_key',$storeScope));
                $signature  = hash('sha256', $p_cust_id_cliente . '^' . $p_key . '^' . $x_ref_payco . '^' . $x_transaction_id . '^' . $x_amount . '^' . $x_currency_code);
                $orderId = (Integer)$x_extra1;
                
                // Validar que el orderId sea válido
                if (!$orderId || $orderId <= 0) {
                    error_log("ePayco Agregador:ID de orden inválido: " . $orderId);
                    return $result->setData(['ePayco Agregador: Error: ID de orden inválido ' . $orderId]);
                }
                
                // Validar que la orden existe antes de intentar obtenerla
                try {
                    //$order = $orderRepository->get($orderId);
                    $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id', (Integer)$orderId);
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    // La orden no existe, registrar error y retornar respuesta de error
                    error_log("ePayco Agregador: Orden no encontrada con ID: " . $orderId . " - Error: " . $e->getMessage());
                    return $result->setData(['Error: Orden no encontrada con ID ' . $orderId]);
                }
                $x_test_request = trim($_REQUEST['x_test_request']);
                $isTestTransaction = $x_test_request == 'TRUE' ? "yes" : "no";
                $isTestMode = $isTestTransaction == "yes" ? "true" : "false";

                if(trim($scopeConfig->getValue('payment/epaycoagregador/payco_test',$storeScope)) == "1"){
                    $isTestPluginMode = "yes";
                }else{
                    $isTestPluginMode = "no";
                }
                if("yes" == $isTestPluginMode){
                    $validation = true;
                }
                if("no" == $isTestPluginMode ){
                    if($x_approval_code != "000000" && $x_cod_transaction_state == 1){
                        $validation = true;
                    }else{
                        if($x_cod_transaction_state != 1){
                            $validation = true;
                        }else{
                            $validation = false;
                        }
                    }

                }
                if($x_signature == $signature && $validation){
                    try{
                        $x_cod_transaction_state =trim($_REQUEST['x_cod_transaction_state']);
                        $code = (Integer)$x_cod_transaction_state;
                        $transaction = $orderEpayco->addFieldToFilter('order', $orderId);
                        if($code == 1){
                            if($order->getState() != "canceled"  ){
                                $order->setState(Order::STATE_PROCESSING, true);
                                $order->setStatus(Order::STATE_PROCESSING, true);
                                foreach ($transaction as $item) {
                                $item->delete();
                                } 
                                $orderRepository->save($order);
                            }
                        } else if($code == 3){
                            $order->setState($pendingOrderState, true);
                            $order->setStatus($pendingOrderState, true);
                            foreach ($transaction as $item) {
                                $item->setData('ref_payco', $x_ref_payco);
                                $item->setData('status', 'pending');
                                $item->save(); 
                            }
                            $orderRepository->save($order);
                        } else if($code == 2 ||
                            $code == 4 ||
                            $code == 6 ||
                            $code == 9 ||
                            $code == 10 ||
                            $code == 11
                        ){
                            if($order->getState() == "pending" || 
                                $order->getState() == "pending_payment" || 
                                $order->getState() == "new" ){
                                $validate = $this->uploadStatusOrder($objectManager,$orderId);
                                if($validate){
                                    $order->setState(Order::STATE_CANCELED, true);
                                    $order->setStatus(Order::STATE_CANCELED, true);
                                    $this->uploadInventory($objectManager,$stockRegistry,$order,$orderId);
                                    $orderRepository->save($order);
                                }
                            }
                        } else if($code == 12)  {
                            if($order->getState() == "pending" || 
                                $order->getState() == "pending_payment" || 
                                $order->getState() == "new" ){
                                $validate = $this->uploadStatusOrder($objectManager,$orderId);
                                if($validate){
                                    $order->setState(Order::STATUS_FRAUD, true);
                                    $order->setStatus(Order::STATUS_FRAUD, true);
                                    $this->uploadInventory($objectManager,$stockRegistry,$order,$orderId);
                                    $orderRepository->save($order);
                                }
                            }
                        }
                    } catch(\Exception $e){
                        return $result->setData(['Error No se creo la orden'+ $e->getMessage()]);
                    }

                    return $result->setData(['confirmed order']);
                }else{
                    try{
                        if($order->getState() != "canceled" ){
                            $order->setState(Order::STATE_CANCELED, true);
                            $order->setStatus(Order::STATE_CANCELED, true);
                            $this->uploadStatusOrder($objectManager,$orderId);
                            $this->uploadInventory($objectManager,$stockRegistry,$order,$orderId);
                            $orderRepository->save($order);
                        }
                    } catch(\Exception $e){
                        return $result->setData(['Error No se creo la orden '+ $e->getMessage()]);
                    }
                    return $result->setData(['no entro a la signature']);
                }
            } else {
                return $result->setData(['No se creo la orden']);
            }
        }catch(\Exception $e){
            return $result->setData([$e->getMessage()]);
        }
    }

    public function uploadInventory($objectManager,$stockRegistry,$order, $orderId){
        try{
            foreach ($order->getAllItems() as $item) {
                $sku = $item->getSku();
                $qty = $item->getQtyOrdered();
                $qty_ = $item->getQtyCanceled();
                $stockItem = $stockRegistry->getStockItemBySku($sku);
                $stockItem->setQty($stockItem->getQty() + $qty);
                $stockItem->setIsInStock(true);

                $stockRegistry->updateStockItemBySku($sku, $stockItem);
                break;
            }
            // $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            // $connection = $resource->getConnection();
            // $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id',$orderId);
            // $sql = "SELECT sku FROM quote_item WHERE quote_id = '$orderId'";
            // $result = $connection->fetchAll($sql);
            // if($result != null){
            //     foreach($result as $sku){
            //         $sku  = $sku["sku"];
            //         $sql_ = "SELECT MAX(reservation_id),sku,quantity FROM inventory_reservation WHERE sku = '$sku' ORDER BY reservation_id ASC";
            //         $query = $connection->fetchAll($sql_);
            //         if($query != null){
            //             foreach($query as $productInventory){
            //                 $queryUpload = $connection->update(
            //                     'inventory_reservation',
            //                     ['quantity' => '0.0000'],
            //                     ['reservation_id = ?' => $productInventory["MAX(reservation_id)"]]
            //                 );
            //             }
            //         }
            //     }
            // }
        } catch(\Exception $e){
          // return $result->setData([$e->getMessage()]);
          error_log('OrderEpaycoAgregador Error: ' . $e->getMessage());
        }
    }

    public function uploadStatusOrder($objectManager,$orderId){
        try{
            $result = $this->resultJsonFactory->create();
            $collectionFactory = $objectManager->get(\Pago\Paycoagregador\Model\ResourceModel\OrderEpaycoAgregador\CollectionFactory::class);
            $orderEpayco = $collectionFactory->create();
            $transaction = $orderEpayco->addFieldToFilter('order', $orderId);
            foreach ($transaction as $item) {
                $retry = (int)$item->getData('retry');
                if($retry<=0){
                    $item->delete(); 
                    return true;
                }else{
                    $retry -= 1;
                    $item->setData('retry', $retry);
                    $item->save(); 
                    return false;
                }
            } 
        } catch(\Exception $e){
            return $result->setData([$e->getMessage()]);
            error_log('OrderEpaycoAgregador Error: ' . $e->getMessage());
        }
    }




}
