<?php

	namespace Pago\Paycoagregador\Model;

	use Magento\Checkout\Model\ConfigProviderInterface;
	use Magento\ImportExport\Test\Unit\Model\Import\Entity\EavAbstractTest;
	use Pago\Paycoagregador\Controller\PaymentagregadorController;

	class PaycoagregadorConfigProvider implements ConfigProviderInterface {
		/**
		 * {@inheritdoc}
		 */
		protected $_scopeConfig;
		public function __construct(
			\Magento\Framework\App\Helper\Context $context,
			\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
			PaymentagregadorController $paycoagregador,
			\Magento\Store\Api\Data\StoreInterface $store
		)
		{
		    $this->_scopeConfig = $scopeConfig;
		    $this->paycoagregador = $paycoagregador;
		    $this->store = $store;
		}



		public function getConfig() {
			$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
			$config = [
				'payment' => [
					'Paycoagregador' => [
						'paycoagregador_title'=> $this->_scopeConfig->getValue('payment/paycoagregador/paycoagregador_title',$storeScope),
						'paycoagregador_merchant'=> $this->_scopeConfig->getValue('payment/paycoagregador/paycoagregador_merchant',$storeScope),
						'paycoagregador_key'=> $this->_scopeConfig->getValue('payment/paycoagregador/paycoagregador_key',$storeScope),
						'paycoagregador_public_key'=> $this->_scopeConfig->getValue('payment/paycoagregador/paycoagregador_public_key',$storeScope),
						'paycoagregador_callback'=> $this->_scopeConfig->getValue('payment/paycoagregador/paycoagregador_callback',$storeScope),
						'paycoagregador_test'=> $this->_scopeConfig->getValue('payment/paycoagregador/paycoagregador_test',$storeScope),
						'vertical_cs'=> $this->_scopeConfig->getValue('payment/paycoagregador/vertical_cs',$storeScope),
						'responseAction'=>$this->paycoagregador->responseActionPayment(),
						'getOrderId'=>$this->paycoagregador->getOrderIdData(),
                  		'getQuoteIncrementId'=>$this->paycoagregador->getQuoteIncrementId(),
						'language'=>$this->getLanguage()
					]
				]
			];

			return $config;
		}


		public function getLanguage(){
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$store = $objectManager->get('Magento\Framework\Locale\Resolver');
			return $store->getLocale();
		}
	}
