<?php

class Aoe_CartApi_Model_Cart extends Aoe_CartApi_Model_Resource
{
    /**
     * Hash of external attribute codes and their data type
     *
     * @var string[]
     */
    protected $attributeTypeMap = [
        'coupon_code'     => 'string',
        'has_error'       => 'bool',
        'shipping_method' => 'string',
        'qty'             => 'float',
    ];

    /**
     * Array of external attribute codes that are manually generated
     *
     * @var string[]
     */
    protected $manualAttributes = [
        'shipping_method',
        'qty',
        'totals',
        'messages',
    ];

    /**
     * Hash of default embed codes
     *
     * @var string[]
     */
    protected $defaultEmbeds = [
        'items',
        'billing_address',
        'shipping_address',
        'payment',
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

        // Get a filter instance
        $filter = $this->getFilter();

        // Initialize outbound data array
        $data = [];

        // Get raw outbound data
        $attributes = array_diff($filter->getAttributesToInclude(), $this->manualAttributes);
        $attributes = array_combine($attributes, $attributes);
        $attributes = array_merge($attributes, array_intersect_key($this->attributeMap, $attributes));
        foreach ($attributes as $externalKey => $internalKey) {
            $data[$externalKey] = $resource->getDataUsingMethod($internalKey);
        }

        // =========================
        // BEGIN - Manual attributes
        // =========================

        // Shipping method
        $data['shipping_method'] = $resource->getShippingAddress()->getShippingMethod();

        // Cart qty summary
        $data['qty'] = (Mage::getStoreConfig('checkout/cart_link/use_qty') ? $resource->getItemsQty() : $resource->getItemsCount());

        // Add in totals
        if (in_array('totals', $filter->getAttributesToInclude())) {
            $totalsValues = [];
            $totalsTypeMap = [];
            $totalsTitles = [];
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
            $data['messages'] = [];
            foreach ($resource->getMessages() as $message) {
                /** @var Mage_Core_Model_Message_Abstract $message */
                $data['messages'][$message->getType()][] = $message->getText();
            }
        }

        // =========================
        // END - Manual attributes
        // =========================

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_cart_prepare', ['data' => $data, 'filter' => $filter, 'resource' => $resource]);
        $data = $data->getData();

        // Filter outbound data
        $data = $filter->out($data);

        // Handle embeds - This happens after output filtering on purpose
        foreach ($this->parseEmbeds($this->getRequest()->getParam('embed')) as $embed) {
            switch ($embed) {
                case 'items':
                    if ($this->_isSubCallAllowed('aoe_cartapi_item')) {
                        /** @var Aoe_CartApi_Model_Item $subModel */
                        $subModel = $this->_getSubModel('aoe_cartapi_item', ['embed' => false]);
                        $data['items'] = $subModel->prepareCollection($resource);
                    }
                    break;
                case 'billing_address':
                    if ($this->_isSubCallAllowed('aoe_cartapi_billing_address')) {
                        /** @var Aoe_CartApi_Model_BillingAddress $subModel */
                        $subModel = $this->_getSubModel('aoe_cartapi_billing_address', []);
                        $data['billing_address'] = $subModel->prepareResource($resource->getBillingAddress());
                    }
                    break;
                case 'shipping_address':
                    if ($this->_isSubCallAllowed('aoe_cartapi_shipping_address')) {
                        /** @var Aoe_CartApi_Model_ShippingAddress $subModel */
                        $subModel = $this->_getSubModel('aoe_cartapi_shipping_address', []);
                        $data['shipping_address'] = $subModel->prepareResource($resource->getShippingAddress());
                    }
                    break;
                case 'shipping_methods':
                    if ($this->_isSubCallAllowed('aoe_cartapi_shipping_method')) {
                        /** @var Aoe_CartApi_Model_ShippingMethod $subModel */
                        $subModel = $this->_getSubModel('aoe_cartapi_shipping_method', ['embed' => false]);
                        $data['shipping_methods'] = $subModel->prepareCollection($resource);
                    }
                    break;
                case 'crosssells':
                    if ($this->_isSubCallAllowed('aoe_cartapi_crosssell')) {
                        /** @var Aoe_CartApi_Model_Crosssell $subModel */
                        $subModel = $this->_getSubModel('aoe_cartapi_crosssell', ['embed' => false]);
                        $data['crosssells'] = $subModel->prepareCollection($resource);
                    }
                    break;
                case 'payment':
                    // TODO
                    break;
            }
        }

        // Fix data types
        $data = $this->fixTypes($data);

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
