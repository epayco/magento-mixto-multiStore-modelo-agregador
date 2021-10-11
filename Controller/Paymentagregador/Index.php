<?php


namespace Pago\Paycoagregador\Controller\Paymentagregador;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
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
        \Pago\Paycoagregador\Controller\PaymentagregadorController $payment_controller,
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
        $data = $_REQUEST;
       
        if(isset($_REQUEST['order_id'])){
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $orderId_= $_REQUEST['order_id'];
            $sql = "SELECT * FROM sales_order WHERE quote_id = '$orderId_'";
            $result_ = $connection->fetchAll($sql);
            if($result_ != null){
                return $result->setData($result_[0]);
            }else{
                return $result->setData('No se creo la orden ' );
            }

        } else {
            return $result->setData('orden no enviada');
        }

    }

}
