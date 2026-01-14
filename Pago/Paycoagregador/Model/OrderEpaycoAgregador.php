<?php
namespace Pago\Paycoagregador\Model;
use Magento\Framework\Model\AbstractModel;

class OrderEpaycoAgregador extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Pago\Paycoagregador\Model\ResourceModel\OrderEpaycoAgregador::class);
    }
}

