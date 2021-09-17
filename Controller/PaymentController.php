<?php

	namespace Pago\Paycoagregador\Controller;

class PaymentController extends \Magento\Framework\App\Action\Action {

public function __construct(
	\Magento\Framework\App\Action\Context $context,

	\Magento\Checkout\Model\Session $checkoutSession,
	\Magento\Sales\Model\OrderFactory $orderFactory,
	\Magento\Quote\Model\QuoteManagement $quote_management,
	\Magento\Checkout\Model\Cart $cart
) {

	$this->checkoutSession = $checkoutSession;
	$this->orderFactory = $orderFactory;
	$this->quoteManagement = $quote_management;
	$this->cart = $cart;

	parent::__construct($context);
}



	public function execute(){}



	public function responseActionPayment($control = false)
	{
		if(!$control){
			return true;
		} else{
			$this->_redirect($this->_buildUrl('confirmation/index/index'));
			$x_respuesta=$_POST['x_response'];
			$x_cod_response=$_POST['x_cod_response'];
			$x_transaction_id=$_POST['x_transaction_id'];
			$x_approval_code=$_POST['x_approval_code'];
			$x_id_invoice=$_POST['x_id_invoice'];
			$x_ref_payco=$_POST['x_ref_payco'];
			$x_response_reason_text=$_POST['x_response_reason_text'];
			$order = Mage::getModel('sales/order')->loadByIncrementId($x_id_invoice);

			$order_comment = "";

			foreach($_POST as $key=>$value){
				$order_comment .= "<br/>$key: $value";
			}
			if($order->getStatus()=='complete'){
				echo 'Transacción ya procesada';
				exit;
			}

			if($x_respuesta=='Aceptada'  && $x_cod_response=='1'){

				$order->getPayment()->setTransactionId($x_ref_payco);
				$order->getPayment()->registerCaptureNotification($_POST['x_amount'] );
				$order->addStatusToHistory($order->getStatus(), $order_comment);
				$order->save();
				echo utf8_encode('Transacción Aceptada');

			} else {

				if($x_respuesta=='Pendiente'){
					$order->addStatusToHistory('pending', $order_comment);
					echo utf8_encode('Transacción Pendiente');
				}
				if($x_respuesta=='Rechazada' || $x_respuesta=='Fallida'){
					$order = Mage::getModel('sales/order')->loadByIncrementId($x_id_invoice);
					$order->cancel();
					$order->addStatusToHistory($order->getStatus(), $order_comment);
					$order->save();
					echo utf8_encode('Transacción Rechazada');
				}
			}
			exit;
		}

	}

	public function confirmAction()
	{
		$x_respuesta=$_POST['x_response'];
        $x_cod_response=$_POST['x_cod_response'];
        $x_transaction_id=$_POST['x_transaction_id'];
        $x_approval_code=$_POST['x_approval_code'];
        $x_id_invoice=$_POST['x_id_invoice'];
        $x_ref_payco=$_POST['x_ref_payco'];
		$x_response_reason_text=$_POST['x_response_reason_text'];
        $order = Mage::getModel('sales/order')->loadByIncrementId($x_id_invoice);

		$order_comment = "";

		foreach($_POST as $key=>$value){
			$order_comment .= "<br/>$key: $value";
		}
		if($order->getStatus()=='complete'){
			echo 'Transacción ya procesada';
			exit;
		}

		if($x_respuesta=='Aceptada'  && $x_cod_response=='1'){

				$order->getPayment()->setTransactionId($x_ref_payco);
				$order->getPayment()->registerCaptureNotification($_POST['x_amount'] );
				$order->addStatusToHistory($order->getStatus(), $order_comment);
				$order->save();
				echo utf8_encode('Transacción Aceptada');

		} else {

                if($x_respuesta=='Pendiente'){
                	$order->addStatusToHistory('pending', $order_comment);
                	echo utf8_encode('Transacción Pendiente');
                }
               	if($x_respuesta=='Rechazada' || $x_respuesta=='Fallida'){
               		$order = Mage::getModel('sales/order')->loadByIncrementId($x_id_invoice);
                	$order->cancel();
                	$order->addStatusToHistory($order->getStatus(), $order_comment);
                	$order->save();
                	echo utf8_encode('Transacción Rechazada');
               	}
		}
		exit;

	}

    public function getQuoteIncrementId(){
		  $this->checkoutSession->getQuote()->reserveOrderId(); 
		  $quoteReservedOrderId = $this->getOrderData();

		  return $quoteReservedOrderId;
    }

    public function getOrderIncrementId(){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $orderId = $this->checkoutSession->getQuote()->getId();
        if($orderId){
            $orderId_ = intval($orderId);
            $sql = "SELECT * FROM quote WHERE entity_id = '$orderId_'";
            $result = $connection->fetchAll($sql);
            if($result != null){
                return $result[0];
            }else{
                return 1;
            }

        }else{
            return 0;
        }
    }

    public function getOrderData(){
        $orderId = $this->checkoutSession->getQuote()->getReservedOrderId();
        return $orderId;
    }

	public function getOrderIdData(){
		$carrito = $this->checkoutSession->getQuote()->getId();
		return $carrito;

	}

}
