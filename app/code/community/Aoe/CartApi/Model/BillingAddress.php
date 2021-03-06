<?php

class Aoe_CartApi_Model_BillingAddress extends Aoe_CartApi_Model_Resource
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
        'save_in_address_book' => 'bool',
        'customer_address_id'  => 'int',
    ];

    /**
     * Array of external attribute codes that are manually generated
     *
     * @var string[]
     */
    protected $manualAttributes = [
        'validation_errors',
    ];

    /**
     * Dispatch API call
     */
    public function dispatch()
    {
        $address = $this->loadQuote()->getBillingAddress();

        switch ($this->getActionType() . $this->getOperation()) {
            case self::ACTION_TYPE_ENTITY . self::OPERATION_RETRIEVE:
                $this->_render($this->prepareResource($address));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_CREATE:
                $this->updateResource($address, $this->getRequest()->getBodyParams());
                $this->saveQuote();
                $this->_render($this->prepareResource($address));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_UPDATE:
                $this->updateResource($address, $this->getRequest()->getBodyParams());
                $this->saveQuote();
                $this->_render($this->prepareResource($address));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_DELETE:
                $address->delete();
                $this->getResponse()->setMimeType($this->getRenderer()->getMimeType());
                $this->getResponse()->setHttpResponseCode(204);
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

        if (in_array('formatted_html', $filter->getAttributesToInclude())) {
            $data['formatted_html'] = $resource->format('html');
        }

        if (in_array('formatted_text', $filter->getAttributesToInclude())) {
            $data['formatted_text'] = $resource->format('text');
        }

        if (in_array('validation_errors', $filter->getAttributesToInclude())) {
            $data['validation_errors'] = array_filter(array_map('trim', (array)$resource->getData('validation_errors')));
        }

        // =========================
        // END - Manual attributes
        // =========================

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_billingaddress_prepare', ['data' => $data, 'filter' => $filter, 'resource' => $resource]);
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
        if (array_key_exists('customer_address_id', $data) && $data['customer_address_id']) {
            /** @var Mage_Customer_Model_Address $customerAddress */
            $customerAddress = Mage::getModel('customer/address')->load($data['customer_address_id']);
            if ($customerAddress->getId()) {
                if ($customerAddress->getCustomerId() != $resource->getQuote()->getCustomerId()) {
                    $this->_critical(Mage::helper('checkout')->__('Customer Address is not valid.'), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
                }
                $resource->importCustomerAddress($customerAddress);
                $resource->setSaveInAddressBook(0);
            }
        } else {
            // Fix region/country data
            $data = $this->getHelper()->fixAddressData($data, $resource->getCountryId(), $resource->getRegionId());

            // Get allowed attributes
            $allowedAttributes = $filter->getAllowedAttributes(Mage_Api2_Model_Resource::OPERATION_ATTRIBUTE_WRITE);

            // Update model
            $this->saveResourceAttributes($resource, array_merge($allowedAttributes, ['region_id']), $data);
        }

        // Update the shipping address if it is meant to match the billing address
        if ($resource->getQuote()->getShippingAddress()->getSameAsBilling()) {
            $shippingAddress = $resource->getQuote()->getShippingAddress();
            $shippingAddress->importCustomerAddress($resource->exportCustomerAddress());
            $shippingAddress->setSameAsBilling(1);
        }

        // Validate address
        $addressErrors = $this->getHelper()->validateQuoteAddress($resource);
        if (!empty($addressErrors)) {
            $resource->setData('validation_errors', $addressErrors);
        }

        // Fire event - after
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_billingaddress_update_after', ['data' => $data, 'filter' => $filter, 'resource' => $resource]);

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return updated resource
        return $resource;
    }
}
