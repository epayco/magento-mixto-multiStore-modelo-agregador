<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Pago\Paycoagregador\Model\Config\Source\Helper;

/**
 * Order Status source model
 */

class Language implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'es', 'label' => __('Español')],
            ['value' => 'en', 'label' => __('Ingles')],

        ];
    }
}
