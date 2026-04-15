<?php
namespace Pago\Paycoagregador\Model\ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OrderEpaycoAgregador extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('epaycoagregador_order_table', 'id'); // nombre de tabla y PK
    }
}
