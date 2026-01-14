<?php
namespace Pago\Paycoagregador\Model\ResourceModel\OrderEpaycoAgregador;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Pago\Paycoagregador\Model\OrderEpaycoAgregador as Model;
use Pago\Paycoagregador\Model\ResourceModel\OrderEpaycoAgregador as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}