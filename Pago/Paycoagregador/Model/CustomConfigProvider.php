<?php

	namespace Pago\Paycoagregador\Model;

	use Magento\Checkout\Model\ConfigProviderInterface;
	use Magento\Framework\App\Config\ScopeConfigInterface;
	use Magento\Framework\App\ObjectManager;

	class CustomConfigProvider implements ConfigProviderInterface {
		const CODE = 'epaycoagregador';

    	private const SEARCH_ENGINE_PATH = 'payment/epaycoagregador/';


		public function getConfig() {
			$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        	$scopeConfig = ObjectManager::getInstance()->get(ScopeConfigInterface::class);
			$config = [
				'payment' => [
					self::CODE => [
						'payco_title'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'payco_title',
							$storeScope
						),
						'payco_merchant'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'payco_title',
							$storeScope
						),
						'payco_merchant'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'payco_merchant',
							$storeScope
						),
						'payco_key'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'payco_key',
							$storeScope
						),
						'payco_public_key'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'payco_public_key',
							$storeScope
						),
						'payco_private_key'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'payco_private_key',
							$storeScope
						),
						'payco_callback'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'payco_callback',
							$storeScope
						),
						'payco_test'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'payco_test',
							$storeScope
						),
						'vertical_cs'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'vertical_cs',
							$storeScope
						),
						'language_cs'=> $scopeConfig->getValue(
							self::SEARCH_ENGINE_PATH.'language_cs',
							$storeScope
						),
						'getQuoteData'=> $this->getQuoteData(),
						'getSessionId'=> $this->getSessionId(),
						'getQuoteId'=> $this->getQuoteId(),
						'getLanguage'=> $this->getLanguage(),
						'getCustomerIp'=> $this->getCustomerIp()
					]
				]
			];

			return $config;
		}

		public function getQuoteData(){
        $objectManager = ObjectManager::getInstance();
        /** @var $session \Magento\Checkout\Model\Session  */
        $session = $objectManager->create(\Magento\Checkout\Model\Session::class);
        return $session->getQuote()->getData();
    }

    public function getSessionId(){
        $objectManager = ObjectManager::getInstance();
        /** @var $session \Magento\Checkout\Model\Session  */
        $session = $objectManager->create(\Magento\Checkout\Model\Session::class);
        return $session->getSessionId();
    }

    public function getQuoteId(){
        $objectManager = ObjectManager::getInstance();
        /** @var $session \Magento\Checkout\Model\Session  */
        $session = $objectManager->create(\Magento\Checkout\Model\Session::class);
        return $session->getQuoteId();
    }

    public function getLanguage(){
        $objectManager = ObjectManager::getInstance();
        $store = $objectManager->get('Magento\Framework\Locale\Resolver');
        return $store->getLocale();
    }

    public function getCustomerIp(){
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        $ipaddress = '181.134.248.46';
        return $ipaddress;
    }
}
