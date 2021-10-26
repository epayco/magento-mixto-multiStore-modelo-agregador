<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Pago\Paycoagregador\Model;

/**
 * Pay In Store payment method model
 */
class Epaycoagregador extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'epaycoagregador';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;
	public function isAvailable(
		\Magento\Quote\Api\Data\CartInterface $quote = null
	) {
		return parent::isAvailable($quote);
	}







}
