<?php

class Aoe_CartApi_Model_Cart extends Aoe_CartApi_Model_Resource
{
    /**
     * Hash of external/internal attribute codes
     *
     * @var string[]
     */
    protected $attributeMap = [
        'email' => 'customer_email',
    ];

    /**
     * Hash of external attribute codes and their data type
     *
     * @var string[]
     */
    protected $attributeTypeMap = [
        'email'           => 'string',
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
                $this->getResponse()->setHttpResponseCode(204);
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

        // Get the embeds list
        $embeds = $this->parseEmbeds($this->getRequest()->getParam('embed'));

        // Check for the validation embed and validate the quote if needed
        if (in_array('validation', $embeds)) {
            $errors = $this->getHelper()->validateQuote($resource);
            $resource->setData('__validation_errors__', $errors);
            // Save the validation results (since address normalization happens here)
            $this->saveQuote();
        }

        // Get raw outbound data
        $data = $this->loadResourceAttributes($resource, $filter->getAttributesToInclude());

        // =========================
        // BEGIN - Manual attributes
        // =========================

        // Shipping method
        if (in_array('shipping_method', $filter->getAttributesToInclude())) {
            $data['shipping_method'] = $resource->getShippingAddress()->getShippingMethod();
        }

        // Cart qty summary
        if (in_array('qty', $filter->getAttributesToInclude())) {
            $data['qty'] = (Mage::getStoreConfig('checkout/cart_link/use_qty') ? $resource->getItemsQty() : $resource->getItemsCount());
        }

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
            $data['messages'] = new ArrayObject();
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
        foreach ($embeds as $embed) {
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
                    if ($this->_isSubCallAllowed('aoe_cartapi_payment')) {
                        /** @var Aoe_CartApi_Model_Payment $subModel */
                        $subModel = $this->_getSubModel('aoe_cartapi_payment', ['embed' => false]);
                        $data['payment'] = $subModel->prepareResource($resource->getPayment());
                    }
                    break;
                case 'payment_methods':
                    if ($this->_isSubCallAllowed('aoe_cartapi_payment_methods')) {
                        $subModel = $this->_getSubModel('aoe_cartapi_payment_methods', ['embed' => false]);
                        $data['payment_methods'] = $subModel->prepareResource($resource);
                    }
                    break;
                case 'validation':
                    $validationErrors = $resource->getData('__validation_errors__');
                    $data['validation'] = (is_array($validationErrors) ? $validationErrors : []);
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

        // Get a filter instance
        $filter = $this->getFilter();

        // Fire event - before filter
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_cart_update_prefilter', ['data' => $data, 'filter' => $filter, 'resource' => $resource]);
        $data = $data->getData();

        // Get allowed attributes
        $allowedAttributes = $filter->getAllowedAttributes(Mage_Api2_Model_Resource::OPERATION_ATTRIBUTE_WRITE);

        // Update model
        $this->saveResourceAttributes($resource, $allowedAttributes, $data);

        // =========================
        // BEGIN - Manual attributes
        // =========================

        // Shipping Method
        if (in_array('shipping_method', $allowedAttributes) && array_key_exists('shipping_method', $data)) {
            $resource->getShippingAddress()->setShippingMethod($data['shipping_method']);
        }

        // =========================
        // END - Manual attributes
        // =========================

        // Handle embeds - This is a subset of possible embeds
        foreach ($this->parseEmbeds($this->getRequest()->getParam('embed')) as $embed) {
            switch ($embed) {
                case 'billing_address':
                    if (array_key_exists('billing_address', $data) && $this->_isSubCallAllowed('aoe_cartapi_billing_address')) {
                        /** @var Aoe_CartApi_Model_BillingAddress $subModel */
                        $subModel = $this->_getSubModel('aoe_cartapi_billing_address', []);
                        $subModel->updateResource($resource->getBillingAddress(), $data['billing_address']);
                    }
                    break;
                case 'shipping_address':
                    if (array_key_exists('shipping_address', $data) && $this->_isSubCallAllowed('aoe_cartapi_shipping_address')) {
                        /** @var Aoe_CartApi_Model_ShippingAddress $subModel */
                        $subModel = $this->_getSubModel('aoe_cartapi_shipping_address', []);
                        $subModel->updateResource($resource->getShippingAddress(), $data['shipping_address']);
                    }
                    break;
                case 'payment':
                    if (array_key_exists('payment', $data) && $this->_isSubCallAllowed('aoe_cartapi_payment')) {
                        /** @var Aoe_CartApi_Model_Payment $subModel */
                        $subModel = $this->_getSubModel('aoe_cartapi_payment', []);
                        $subModel->updateResource($resource->getPayment(), $data['payment']);
                    }
                    break;
                case 'payment_methods':
                    if ($this->_isSubCallAllowed('aoe_cartapi_payment_methods')) {
                        $subModel = $this->_getSubModel('aoe_cartapi_payment_methods', []);
                        $subModel->updateResource($resource->getPayment(), $data['payment_methods']);
                    }
                    break;
            }
        }

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_cart_update', ['data' => $data, 'filter' => $filter, 'resource' => $resource]);
        $data = $data->getData();

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
