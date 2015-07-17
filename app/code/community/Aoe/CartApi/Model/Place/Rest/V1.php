<?php

class Aoe_CartApi_Model_Place_Rest_V1 extends Aoe_CartApi_Model_Resource
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

        switch ($this->getActionType() . $this->getOperation() . $this->getApiUser()->getType()) {
            case self::ACTION_TYPE_ENTITY . self::OPERATION_CREATE . Mage_Api2_Model_Auth_User_Guest::USER_TYPE:
                $this->placeGuestOrder($quote);
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_CREATE . Mage_Api2_Model_Auth_User_Customer::USER_TYPE:
                /** @var Mage_Customer_Model_Customer $customer */
                $this->placeCustomerOrder($quote);
                break;
            default:
                $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
        }
    }

    protected function placeGuestOrder(Mage_Sales_Model_Quote $quote)
    {
        $quote->setCustomerId(null);
        $quote->setCustomerEmail($quote->getBillingAddress()->getEmail());
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    }

    protected function placeCustomerOrder(Mage_Sales_Model_Quote $quote)
    {
        $customer = $quote->getCustomer();
        $billing = $quote->getBillingAddress();

        $shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = $this->getCustomerSession()->getCustomer();
        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }
        if ($shipping && !$shipping->getSameAsBilling()
            && (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())
        ) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }
        if ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        } else {
            if (isset($customerBilling) && !$customer->getDefaultShipping()) {
                $customerBilling->setIsDefaultShipping(true);
            }
        }
        $quote->setCustomer($customer);
    }
}
