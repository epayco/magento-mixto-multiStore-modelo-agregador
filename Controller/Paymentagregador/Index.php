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
use Magento\Sales\Model\Order;

class Index extends \Magento\Framework\App\Action\Action
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
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
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
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $orderId = $_REQUEST['order_id'];

        if(isset($orderId)){
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $orderId_= $orderId;
                $sql = "SELECT * FROM sales_order WHERE quote_id = '$orderId_'";
                $result_ = $connection->fetchAll($sql);
                if($result_ != null){
                    return $result->setData($result_[0]);
                }else{
                    return $result->setData('warning' );
                }

        } else {
                return $result->setData('error');
        }

    }

}
