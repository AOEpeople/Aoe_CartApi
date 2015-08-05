<?php

class Aoe_CartApi_Model_Validate extends Aoe_CartApi_Model_Resource
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
            case self::ACTION_TYPE_ENTITY . self::OPERATION_CREATE:
                $data = $this->validateQuote($quote);
                if($data['status'] === 'success') {
                    $this->getResponse()->setHttpResponseCode(Mage_Api2_Model_Server::HTTP_OK);
                } else {
                    $this->getResponse()->setHttpResponseCode(422);
                }
                $this->_render($data);
                break;
            default:
                $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
        }
    }

    protected function validateQuote(Mage_Sales_Model_Quote $quote)
    {
        // Get a filter instance
        $filter = $this->getFilter();

        // Fire event - before place
        Mage::dispatchEvent('aoe_cartapi_cart_validate_before', ['filter' => $filter, 'quote' => $quote]);

        if ($quote->getCustomerId()) {
            $customer = $quote->getCustomer();
            $billing = $quote->getBillingAddress();

            if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
                $customerBilling = $billing->exportCustomerAddress();
                $customer->addAddress($customerBilling);
                $billing->setCustomerAddress($customerBilling);
            }

            if (!$quote->isVirtual()) {
                $shipping = $quote->getShippingAddress();
                if ($shipping->getSameAsBilling()) {
                    // Copy data from billing address
                    $shipping->importCustomerAddress($quote->getBillingAddress()->exportCustomerAddress());
                    $shipping->setSameAsBilling(1);
                } elseif (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook()) {
                    $customerShipping = $shipping->exportCustomerAddress();
                    $customer->addAddress($customerShipping);
                    $shipping->setCustomerAddress($customerShipping);
                }
            }
        } else {
            $quote->setCustomerId(null);
            $quote->setCustomerEmail($quote->getBillingAddress()->getEmail());
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);

            if (!$quote->isVirtual() && $quote->getShippingAddress()->getSameAsBilling()) {
                // Copy data from billing address
                $quote->getShippingAddress()->importCustomerAddress($quote->getBillingAddress()->exportCustomerAddress());
                $quote->getShippingAddress()->setSameAsBilling(1);
            }
        }

        $errors = [];
        
        if (!$quote->isVirtual()) {
            $address = $quote->getShippingAddress();
            $addressValidation = $address->validate();
            if ($addressValidation !== true) {
                $errors['shipping_address'] = $addressValidation;
            }
            $method= $address->getShippingMethod();
            $rate  = $address->getShippingRateByCode($method);
            if (!$quote->isVirtual() && (!$method || !$rate)) {
                $errors['shipping_method'] = [$this->__('Please specify a valid shipping method.')];
            }
        }

        $addressValidation = $quote->getBillingAddress()->validate();
        if ($addressValidation !== true) {
            $errors['billing_address'] = $addressValidation;
        }

        try {
            if (!$quote->getPayment()->getMethod() || !$quote->getPayment()->getMethodInstance()) {
                $errors['payment'] = [$this->__('Please select a valid payment method.')];
            }
        } catch(Mage_Core_Exception $e) {
            $errors['payment'] = [$this->__('Please select a valid payment method.')];
        }

        // Generate response
        $data = new Varien_Object(['status' => (empty($errors) ? 'success' : 'error'), 'errors' => $errors]);

        // Fire event - after place
        Mage::dispatchEvent('aoe_cartapi_cart_validate_after', ['filter' => $filter, 'quote' => $quote, 'data' => $data]);

        // Get response data
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

        return new ArrayObject($data);
    }
}
