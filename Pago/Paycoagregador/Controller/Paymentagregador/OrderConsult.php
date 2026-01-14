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

namespace Pago\Paycoagregador\Controller\Paymentagregador;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Pago\Paycoagregador\Model\ResourceModel\OrderEpaycoagregador\CollectionFactory;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OrderConsult extends Action
{
    protected $collectionFactory;
    protected $logger;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        try{
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            /** @var \Magento\Sales\Api\OrderRepositoryInterface $orderRepository */
            $orderRepository = $objectManager->create(\Magento\Sales\Api\OrderRepositoryInterface::class);
            /** @var \Magento\Framework\HTTP\Client\Curl $curl */
            $curl = $objectManager->create(\Magento\Framework\HTTP\Client\Curl::class); 
            // Crear la colecci贸n
            $collection = $this->collectionFactory->create();

            // Ejemplo: SELECT * FROM epayco_custom_table WHERE status = 'pending'
            $collection->addFieldToFilter('status', 'pending');

            foreach ($collection as $item) {
                $retry = (int)$item->getData('retry');
                //$item->delete(); 
                $orderId = (int)$item->getData('order');
                $refpayco = $item->getData('ref_payco');
               // echo 'ID: ' . $item->getId() . ' - ref_payco: ' . $refpayco .'<br>'; 
                if($orderId && $refpayco){
                    //$order = $orderRepository->get($orderId);
                    $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id', (Integer)$orderId);
                    $url = "http://eks-cms-backend-platforms-service.epayco.io/transaction/" .$refpayco;
                    $curl->setOption(CURLOPT_FOLLOWLOCATION, true);
                    $curl->get($url);
                    $response = $curl->getBody();
                    $dataTransaction = json_decode($response);
                    if(isset($dataTransaction) && isset($dataTransaction->success) && $dataTransaction->success){
                        $transactionData = $dataTransaction->data; 
                        $x_ref_payco = $transactionData->refPayco;
                        $status = $transactionData->status;
                        $this->logger->info('ePaycoAgregador: Respuesta válida para RefPayco: ' . $x_ref_payco . 
                        ' con estado: ' . $status .
                        ' invoice: ' . $transactionData->invoice
                        );
                        $pendingOrderState = Order::STATE_PENDING_PAYMENT;
                        if($status == 'Aceptada' || $status == 'aceptada'){
                            if($order->getState() != "canceled"  ){
                                $order->setState(Order::STATE_PROCESSING, true);
                                $order->setStatus(Order::STATE_PROCESSING, true);
                                $orderRepository->save($order);
                                $item->delete();
                            }
                        } else if($status == 'Pendiente' || $status == 'pending'){
                            $order->setState($pendingOrderState, true);
                            $order->setStatus($pendingOrderState, true);
                            $item->setData('ref_payco', $x_ref_payco);
                            $item->setData('status', 'pending');
                            $item->save(); 
                            $orderRepository->save($order);
                        } else if($status == 'Rechazada' ||
                            $status == 'Fallida' ||
                            $status == 'caducada' ||
                            $status == 'abandonada' ||
                            $status == 'Cancelada'
                        ){
                            if($retry<=0){
                                if($order->getState() == "pending" || 
                                    $order->getState() == "pending_payment" || 
                                    $order->getState() == "new" ){
                                    $order->setState(Order::STATE_CANCELED, true);
                                    $order->setStatus(Order::STATE_CANCELED, true);
                                    $this->uploadInventory($objectManager,$orderId);
                                    $orderRepository->save($order);
                                    $item->delete();
                                    echo 'ID: ' . $item->getId() . ' - ref_payco: ' . $x_ref_payco.' - order_status: ' . $order->getState() . ' - response '. $status .'<br>';
                                }
                            }else{
                                $retry -= 1;
                                $item->setData('retry', $retry);
                                $item->save(); 
                            }
                        } else if($status == 12)  {
                            if($order->getState() == "pending" || 
                                $order->getState() == "pending_payment" || 
                                $order->getState() == "new" ){
                                $order->setState(Order::STATUS_FRAUD, true);
                                $order->setStatus(Order::STATUS_FRAUD, true);
                                $this->uploadInventory($objectManager,$orderId);
                                $orderRepository->save($order);
                                $item->delete();
                                echo 'ID: ' . $item->getId() . ' - ref_payco: ' . $x_ref_payco.' - order_status: ' . $order->getState() . ' - response '. $status .'<br>';
                            }
                        }
                        
                    }
                }
            }
            exit;
        }catch(\Exception $e){
           var_dump($e->getMessage());
        }
            
    }

    public function uploadInventory($objectManager, $orderId){
        try{
            $stockRegistry = $objectManager->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
            $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByAttribute('quote_id', (Integer)$orderId);
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
            // $sql = "SELECT sku FROM quote_item WHERE quote_id = '$orderId'";
            // $result = $connection->fetchAll($sql);
            // if($result != null){
            //     foreach($result as $sku){
            //         $sku  = $sku["sku"];
            //         $sql_ = "SELECT MAX(reservation_id),sku,quantity FROM inventory_reservation WHERE sku = '$sku' ORDER BY reservation_id ASC";
            //         $query = $connection->fetchAll($sql_);
            //         if($query != null){
            //             foreach($query as $productInventory){
            //                 $connection->update(
            //                     'inventory_reservation',
            //                     ['quantity' => '0.0000'],
            //                     ['reservation_id = ?' => $productInventory["MAX(reservation_id)"]]
            //                 );
            //             }
            //         }
            //     }
            // }
        } catch(\Exception $e){
            //return $result->setData([$e->getMessage()]);
             $this->logger->error('OrderEpaycoAgregador Error: ' . $e->getMessage());
        }
    }

}