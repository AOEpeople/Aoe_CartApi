<?php

class Aoe_CartApi_Model_Cart_Rest_V1 extends Aoe_CartApi_Model_Resource
{
    /**
     * Hash of external/internal attribute codes
     *
     * @var string[]
     */
    protected $attributeMap = [];

    /**
     * Hash of external attribute codes and their data type
     *
     * @var string[]
     */
    protected $attributeTypeMap = [
        'qty'   => 'float',
        'total' => 'currency',
    ];

    /**
     * Dispatch API call
     */
    public function dispatch()
    {
        $quote = $this->loadQuote();

        switch ($this->getActionType() . $this->getOperation()) {
            case self::ACTION_TYPE_ENTITY . self::OPERATION_RETRIEVE:
                $this->_render($this->prepareResource($quote));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_CREATE:
                $this->updateResource($quote, $this->getRequest()->getBodyParams());
                $this->saveQuote();
                $this->_render($this->prepareResource($quote));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_UPDATE:
                $this->updateResource($quote, $this->getRequest()->getBodyParams());
                $this->saveQuote();
                $this->_render($this->prepareResource($quote));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_DELETE:
                if ($quote->getId()) {
                    $quote->setIsActive(false);
                    $this->saveQuote();
                    //$quote->delete();
                }
                $this->getResponse()->setMimeType($this->getRenderer()->getMimeType());
                break;
            default:
                $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
        }
    }

    /**
     * @param Mage_Sales_Model_Quote $resource
     *
     * @return array
     */
    public function prepareResource(Mage_Sales_Model_Quote $resource)
    {
        // Store current state
        $actionType = $this->getActionType();
        $operation = $this->getOperation();

        // Change state
        $this->setActionType(self::ACTION_TYPE_ENTITY);
        $this->setOperation(self::OPERATION_RETRIEVE);

        // Get raw outbound data
        $data = $resource->toArray();

        // Map data keys
        $data = $this->unmapAttributes($data);

        // Shipping method - REF
        $data['shipping_method'] = $resource->getShippingAddress()->getShippingMethod();

        // Cart qty summary - REF
        $data['qty'] = (Mage::getStoreConfig('checkout/cart_link/use_qty') ? $resource->getItemsQty() : $resource->getItemsCount());

        // Get a filter instance
        $filter = $this->getFilter();

        // Filter raw outbound data
        $data = $filter->out($data);

        // Fix data types
        $data = $this->fixTypes($data);

        // Add in cart items
        if (in_array('items', $filter->getAttributesToInclude()) && $this->_isSubCallAllowed('aoe_cartapi_item')) {
            /** @var Aoe_CartApi_Model_Item_Rest_V1 $subModel */
            $subModel = $this->_getSubModel('aoe_cartapi_item', array());
            $data['items'] = $subModel->prepareCollection($resource);
        }

        // Add in billing address
        if (in_array('billing_address', $filter->getAttributesToInclude()) && $this->_isSubCallAllowed('aoe_cartapi_billing_address')) {
            /** @var Aoe_CartApi_Model_BillingAddress_Rest_V1 $subModel */
            $subModel = $this->_getSubModel('aoe_cartapi_billing_address', array());
            $data['billing_address'] = $subModel->prepareResource($resource->getBillingAddress());
        }

        // Add in shipping address
        if (in_array('shipping_address', $filter->getAttributesToInclude()) && $this->_isSubCallAllowed('aoe_cartapi_shipping_address')) {
            /** @var Aoe_CartApi_Model_ShippingAddress_Rest_V1 $subModel */
            $subModel = $this->_getSubModel('aoe_cartapi_shipping_address', array());
            $data['shipping_address'] = $subModel->prepareResource($resource->getShippingAddress());
        }

        // Add in payment
        //TODO

        // Add in totals
        if (in_array('totals', $filter->getAttributesToInclude())) {
            $totalsValues = array();
            $totalsTypeMap = array();
            $totalsTitles = array();
            foreach ($resource->getTotals() as $code => $total) {
                /* @var Mage_Sales_Model_Quote_Address_Total_Abstract $total */
                $totalsValues[$code] = $total->getValue();
                $totalsTypeMap[$code] = 'currency';
                $totalsTitles[$code] = $total->getTitle();
            }
            $data['totals'] = $this->fixTypes($totalsValues, $totalsTypeMap);
            foreach ($data['totals'] as $code => $total) {
                $data['totals'][$code]['title'] = $totalsTitles[$code];
            }
        }

        // Add in validation/error messages
        if (in_array('messages', $filter->getAttributesToInclude())) {
            $data['messages'] = array();
            foreach ($resource->getMessages() as $message) {
                /** @var Mage_Core_Model_Message_Abstract $message */
                $data['messages'][$message->getType()][] = $message->getText();
            }
        }

        // Add null values for missing data
        foreach ($filter->getAttributesToInclude() as $code) {
            if (!array_key_exists($code, $data)) {
                $data[$code] = null;
            }
        }

        // Sort the result by key
        ksort($data);

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return prepared outbound data
        return $data;
    }

    /**
     * Update the resource model
     *
     * @param Mage_Sales_Model_Quote $resource
     * @param array                  $data
     *
     * @return Mage_Sales_Model_Quote
     */
    public function updateResource(Mage_Sales_Model_Quote $resource, array $data)
    {
        // Store current state
        $actionType = $this->getActionType();
        $operation = $this->getOperation();

        // Change state
        $this->setActionType(self::ACTION_TYPE_ENTITY);
        $this->setOperation(self::OPERATION_UPDATE);

        // Filter raw incoming data
        $data = $this->getFilter()->in($data);

        // Map data keys
        $data = $this->mapAttributes($data);

        // Update model
        foreach ($data as $key => $value) {
            $resource->setDataUsingMethod($key, $value);
        }

        $failedValidation = false;

        $resource->collectTotals();
        if (isset($data['coupon_code']) && $resource->getCouponCode() != $data['coupon_code']) {
            $failedValidation = true;
            $message = Mage::helper('checkout')->__('Coupon code "%s" is not valid.', $data['coupon_code']);
            $this->getResponse()->setException(new Mage_Api2_Exception($message, Mage_Api2_Model_Server::HTTP_BAD_REQUEST));
        }

        if ($failedValidation) {
            $this->_critical('Failed validation', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return updated resource
        return $resource;
    }
}
