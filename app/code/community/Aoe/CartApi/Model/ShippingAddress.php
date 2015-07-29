<?php

class Aoe_CartApi_Model_ShippingAddress extends Aoe_CartApi_Model_Resource
{
    /**
     * Hash of external/internal attribute codes
     *
     * @var string[]
     */
    protected $attributeMap = [
        'method' => 'shipping_method',
    ];

    /**
     * Hash of external attribute codes and their data type
     *
     * @var string[]
     */
    protected $attributeTypeMap = [
        'customer_address_id'  => 'int',
        'same_as_billing'      => 'bool',
        'save_in_address_book' => 'bool',
    ];

    /**
     * Dispatch API call
     */
    public function dispatch()
    {
        $resource = $this->loadQuote()->getShippingAddress();

        switch ($this->getActionType() . $this->getOperation()) {
            case self::ACTION_TYPE_ENTITY . self::OPERATION_RETRIEVE:
                $this->_render($this->prepareResource($resource));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_CREATE:
                $this->updateResource($resource, $this->getRequest()->getBodyParams());
                $this->saveQuote();
                $this->_render($this->prepareResource($resource));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_UPDATE:
                $this->updateResource($resource, $this->getRequest()->getBodyParams());
                $this->saveQuote();
                $this->_render($this->prepareResource($resource));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_DELETE:
                $shippingMethod = $resource->getShippingMethod();
                $resource->delete()->isDeleted(true);
                if ($shippingMethod) {
                    $this->loadQuote()->getShippingAddress()->setShippingMethod($shippingMethod)->save();
                }
                $this->getResponse()->setMimeType($this->getRenderer()->getMimeType());
                break;
            default:
                $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
        }
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $resource
     *
     * @return array
     */
    public function prepareResource(Mage_Sales_Model_Quote_Address $resource)
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

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_shippingaddress_prepare', ['data' => $data, 'filter' => $this->getFilter(), 'resource' => $resource]);
        $data = $data->getData();

        // Filter outbound data
        $data = $this->getFilter()->out($data);

        // Fix data types
        $data = $this->fixTypes($data);

        // Add null values for missing data
        foreach ($this->getFilter()->getAttributesToInclude() as $code) {
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
     * @param Mage_Sales_Model_Quote_Address $resource
     * @param array                          $data
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function updateResource(Mage_Sales_Model_Quote_Address $resource, array $data)
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

        // Check if the update is setting a customer address ID to use
        if (array_key_exists('customer_address_id', $data)) {
            /** @var Mage_Customer_Model_Address $customerAddress */
            $customerAddress = Mage::getModel('customer/address')->load($data['id']);
            if ($customerAddress->getId()) {
                if ($customerAddress->getCustomerId() != $resource->getQuote()->getCustomerId()) {
                    $this->_critical(Mage::helper('checkout')->__('Customer Address is not valid.'), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
                }
                $resource->importCustomerAddress($customerAddress);
                $resource->setSaveInAddressBook(0);
            }
        } elseif (array_key_exists('same_as_billing', $data) && $data['same_as_billing']) {
            // Copy data from billing address
            $resource->importCustomerAddress($resource->getQuote()->getBillingAddress()->exportCustomerAddress());
            $resource->setSameAsBilling(1);
        } else {
            // Clear flag
            $data['same_as_billing'] = 0;

            // Update model
            $data = $this->getHelper()->fixAddressData($data, $resource->getCountryId(), $resource->getRegionId());
            foreach ($data as $key => $value) {
                $resource->setDataUsingMethod($key, $value);
            }
        }

        // Validate address
        /* @var Mage_Customer_Model_Form $addressForm */
//        $addressForm = Mage::getModel('customer/form');
//        $addressForm->setFormCode('customer_address_edit')->setEntityType('customer_address');
//        $addressForm->setEntity($resource);
//        $addressErrors = $addressForm->validateData($resource->getData());
//        if ($addressErrors !== true) {
//            foreach($addressErrors as $addressError) {
//                // This is a nasty hack
//                $this->getResponse()->setException(new Mage_Api2_Exception($addressError, Mage_Api2_Model_Server::HTTP_BAD_REQUEST));
//            }
//            $this->_critical('Failed Address Validation', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
//        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return updated resource
        return $resource;
    }
}
