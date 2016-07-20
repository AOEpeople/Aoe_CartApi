<?php

class Aoe_CartApi_Model_PaymentMethods extends Aoe_CartApi_Model_Resource
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
    protected $attributeTypeMap = [];

    /**
     * Dispatch API call
     */
    public function dispatch()
    {
        $quote = $this->loadQuote();

        switch ($this->getActionType() . $this->getOperation()) {
            case self::ACTION_TYPE_COLLECTION . self::OPERATION_RETRIEVE:
                $this->_render($this->prepareCollection($quote));
                break;
            default:
                $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
        }
    }

    /**
     * Convert the resource model collection to an array
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return array
     */
    public function prepareCollection(Mage_Sales_Model_Quote $quote)
    {
        // Store current state
        $actionType = $this->getActionType();
        $operation = $this->getOperation();

        // Change state
        $this->setActionType(self::ACTION_TYPE_COLLECTION);
        $this->setOperation(self::OPERATION_RETRIEVE);

        $data = [];

        // Get store
        $store = $quote->getStoreId();

        // Get filter
        $filter = $this->getFilter();

        // Prepare methods
        foreach (Mage::helper('payment')->getStoreMethods($store, $quote) as $method) {
            /** @var $method Mage_Payment_Model_Method_Abstract */
            if ($this->_canUseMethod($method, $quote) && $method->isApplicableToQuote(
                $quote,
                Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL
            )) {
                $method->setInfoInstance($quote->getPayment());
                $data[] =  $this->prepareMethod($method, $filter);
            }
        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return prepared outbound data
        return $data;
    }

    /**
     * Prepare resource and return results
     *
     * @param Mage_Payment_Model_Method_Abstract $method
     * @param Mage_Api2_Model_Acl_Filter $filter
     *
     * @return array
     */
    public function prepareMethod(Mage_Payment_Model_Method_Abstract $method, Mage_Api2_Model_Acl_Filter $filter)
    {
        // Get raw outbound data
        $data = [];
        $attributes = $filter->getAttributesToInclude();
        $attributes = array_combine($attributes, $attributes);
        $attributes = array_merge($attributes, array_intersect_key($this->attributeMap, $attributes));
        foreach ($attributes as $externalKey => $internalKey) {
            if ($externalKey === 'cc_types') {
                $data[$externalKey] = $this->_getPaymentMethodAvailableCcTypes($method);
            } else {
                $data[$externalKey] = $method->getDataUsingMethod($internalKey);
            }
        }

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_payment_method_prepare', ['data' => $data, 'filter' => $filter, 'resource' => $method]);
        $data = $data->getData();

        // Filter outbound data
        $data = $filter->out($data);

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

        return $data;
    }

    /**
     * Check payment method model
     *
     * @param Mage_Payment_Model_Method_Abstract $method
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    protected function _canUseMethod($method, $quote)
    {
        return $method->isApplicableToQuote($quote, Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
            | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
            | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX);
    }

    /**
     * Get available CC types for payment method
     *
     * @param $method
     * @return null
     */
    protected function _getPaymentMethodAvailableCcTypes($method)
    {
        $ccTypes = Mage::getSingleton('payment/config')->getCcTypes();
        $methodCcTypes = explode(',', $method->getConfigData('cctypes'));
        foreach ($ccTypes as $code => $title) {
            if (!in_array($code, $methodCcTypes)) {
                unset($ccTypes[$code]);
            }
        }
        if (empty($ccTypes)) {
            return null;
        }

        return $ccTypes;
    }
}
