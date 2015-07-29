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
     * Array of external attribute codes that are manually generated
     *
     * @var string[]
     */
    protected $manualAttributes = [
        'validation_errors'
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
                // Grab shipping method code
                $shippingMethod = $resource->getShippingMethod();
                $resource->delete()->isDeleted(true);
                if ($shippingMethod) {
                    // Set shipping method code if it was originally set
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

        // Get a filter instance
        $filter = $this->getFilter();

        // Get raw outbound data
        $data = $this->loadResourceAttributes($resource, $filter->getAttributesToInclude());

        // =========================
        // BEGIN - Manual attributes
        // =========================

        if (in_array('validation_errors', $filter->getAttributesToInclude())) {
            $data['validation_errors'] = array_filter(array_map('trim', (array)$resource->getData('validation_errors')));
        }

        // =========================
        // END - Manual attributes
        // =========================

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_shippingaddress_prepare', ['data' => $data, 'filter' => $filter, 'resource' => $resource]);
        $data = $data->getData();

        // Filter outbound data
        $data = $this->getFilter()->out($data);

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

        // Get a filter instance
        $filter = $this->getFilter();

        // Filter raw incoming data
        $data = $filter->in($data);

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

            // Fix region/country data
            $data = $this->getHelper()->fixAddressData($data, $resource->getCountryId(), $resource->getRegionId());

            // Get allowed attributes
            $allowedAttributes = $filter->getAllowedAttributes(Mage_Api2_Model_Resource::OPERATION_ATTRIBUTE_WRITE);

            // Update model
            $this->saveResourceAttributes($resource, $allowedAttributes, $data);
        }

        // Validate address
        /* @var Mage_Customer_Model_Form $addressForm */
        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')->setEntityType('customer_address');
        $addressForm->setEntity($resource);
        $addressErrors = $addressForm->validateData($resource->getData());
        if ($addressErrors !== true) {
            $resource->setData('validation_errors', $addressErrors);
        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return updated resource
        return $resource;
    }
}
