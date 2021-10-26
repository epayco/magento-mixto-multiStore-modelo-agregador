<?php

	namespace Pago\Paycoagregador\Model;

	use Magento\Checkout\Model\ConfigProviderInterface;
	use Magento\ImportExport\Test\Unit\Model\Import\Entity\EavAbstractTest;
	use Pago\Paycoagregador\Controller\PaymentController;

	class CustomConfigProvider implements ConfigProviderInterface {
		/**
		 * {@inheritdoc}
		 */
		protected $_scopeConfig;
		public function __construct(
			\Magento\Framework\App\Helper\Context $context,
			\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
			PaymentController $epayco,
			\Magento\Store\Api\Data\StoreInterface $store
		)
		{
		    $this->_scopeConfig = $scopeConfig;
		    $this->epayco = $epayco;
		    $this->store = $store;
		}



		public function getConfig() {
			$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
			$config = [
				'payment' => [
					'epaycoagregador' => [
						/**
						 * payco_title
						 * payco_description
						 * payco_merchant
						 * payco_key
						 * payco_public_key
						 * payco_callback
						 * payco_confirmation
						 * payco_test
						 * vertical_cs
						 */
						'payco_title'=> $this->_scopeConfig->getValue('payment/epaycoagregador/payco_title',$storeScope),
						'payco_merchant'=> $this->_scopeConfig->getValue('payment/epaycoagregador/payco_merchant',$storeScope),
						'payco_key'=> $this->_scopeConfig->getValue('payment/epayco/epaycoagregador',$storeScope),
						'payco_public_key'=> $this->_scopeConfig->getValue('payment/epaycoagregador/payco_public_key',$storeScope),
						'payco_callback'=> $this->_scopeConfig->getValue('payment/epaycoagregador/payco_callback',$storeScope),

						'payco_test'=> $this->_scopeConfig->getValue('payment/epaycoagregador/payco_test',$storeScope),
						'vertical_cs'=> $this->_scopeConfig->getValue('payment/epaycoagregador/vertical_cs',$storeScope),
						'responseAction'=>$this->epayco->responseAction(),
						'getOrderId'=>$this->epayco->getOrderId(),
                        'getQuoteData'=>$this->epayco->getQuoteData(),
                        'getStoreData'=>$this->epayco->getStoreData(),
                        'getOrderIncrementId'=>$this->epayco->getOrderIncrementId(),
                        'getQuoteIncrementId'=>$this->epayco->getQuoteIncrementId(),
                        'getQuoteIdData'=>$this->epayco->getQuoteIdData(),
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
