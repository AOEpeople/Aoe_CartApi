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
        $resource = $this->loadQuote();

        switch ($this->getActionType() . $this->getOperation()) {
            case self::ACTION_TYPE_ENTITY . self::OPERATION_RETRIEVE:
                $this->_render($this->prepareResource($resource));
                break;
            default:
                $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
        }
    }

    /**
     * Prepare resource and return results
     *
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

        // Fire event
        $data = new Varien_Object();
        Mage::dispatchEvent('aoe_cartapi_payment_methods_prepare', ['data' => $data, 'resource' => $resource]);

        $store = $resource->getStoreId();
        $total = $resource->getBaseSubtotal();

        $methodsResult = [];
        $methods = Mage::helper('payment')->getStoreMethods($store, $resource);

        foreach ($methods as $method) {
            /** @var $method Mage_Payment_Model_Method_Abstract */
            if ($this->_canUsePaymentMethod($method, $resource)) {
                $isRecurring = $resource->hasRecurringItems() && $method->canManageRecurringProfiles();

                if ($total != 0 || $method->getCode() == 'free' || $isRecurring) {
                    $methodsResult[] = [
                        'code'       => $method->getCode(),
                        'title'      => $method->getTitle(),
                        'cc_types'   => $this->_getPaymentMethodAvailableCcTypes($method),
                        'block_type' => $method->getFormBlockType(),
                    ];
                }
            }
        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return prepared outbound data
        return $methodsResult;
    }

    /**
     * Check to see if payment method can be used according to magento standard defintions
     *
     * @param  $method
     * @param  $quote
     * @return bool
     */
    protected function _canUsePaymentMethod($method, $quote)
    {
        if (!($method->isGateway() || $method->canUseInternal())) {
            return false;
        }

        if (!$method->canUseForCountry($quote->getBillingAddress()->getCountry())) {
            return false;
        }

        if (!$method->canUseForCurrency(Mage::app()->getStore($quote->getStoreId())->getBaseCurrencyCode())) {
            return false;
        }

        /**
         * Checking for min/max order total for assigned payment method
         */
        $total = $quote->getBaseGrandTotal();
        $minTotal = $method->getConfigData('min_order_total');
        $maxTotal = $method->getConfigData('max_order_total');

        if ((!empty($minTotal) && ($total < $minTotal)) || (!empty($maxTotal) && ($total > $maxTotal))) {
            return false;
        }

        return true;
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
