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
                $this->saveQuote();
                if ($data['status'] === 'success') {
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

        // Run the validation code
        $errors = $this->getHelper()->validateQuote($quote);

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
