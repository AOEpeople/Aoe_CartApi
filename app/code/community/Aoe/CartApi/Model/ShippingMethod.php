<?php

class Aoe_CartApi_Model_ShippingMethod extends Aoe_CartApi_Model_Resource
{
    /**
     * Hash of external/internal attribute codes
     *
     * @var string[]
     */
    protected $attributeMap = [
        'description' => 'method_description',
    ];

    /**
     * Hash of external attribute codes and their data type
     *
     * @var string[]
     */
    protected $attributeTypeMap = [
        'carrier'       => 'string',
        'carrier_title' => 'string',
        'method'        => 'string',
        'method_title'  => 'string',
        'description'   => 'string',
        'price'         => 'currency',
    ];

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
        if ($quote->isVirtual()) {
            return [];
        }

        // Store current state
        $actionType = $this->getActionType();
        $operation = $this->getOperation();

        // Change state
        $this->setActionType(self::ACTION_TYPE_COLLECTION);
        $this->setOperation(self::OPERATION_RETRIEVE);

        $data = [];
        // Load and prep shipping address
        $address = $quote->getShippingAddress();
        $address->setCollectShippingRates(true);
        $address->collectShippingRates();
        $address->save();

        // Load rates
        /** @var Mage_Sales_Model_Resource_Quote_Address_Rate_Collection $rateCollection */
        $rateCollection = $address->getShippingRatesCollection();
        $rates = [];
        foreach ($rateCollection as $rate) {
            /** @var Mage_Sales_Model_Quote_Address_Rate $rate */
            if (!$rate->isDeleted() && $rate->getCarrierInstance()) {
                $rates[] = $rate;
            }
        }
        uasort($rates, [$this, 'sortRates']);

        // Get filter
        $filter = $this->getFilter();

        // Prepare rates
        foreach ($rates as $rate) {
            /** @var Mage_Sales_Model_Quote_Address_Rate $rate */
            $data[] = $this->prepareRate($rate, $filter);
        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return prepared outbound data
        return $data;
    }

    protected function prepareRate(Mage_Sales_Model_Quote_Address_Rate $rate, Mage_Api2_Model_Acl_Filter $filter)
    {
        // Get raw outbound data
        $data = [];
        $attributes = $filter->getAttributesToInclude();
        $attributes = array_combine($attributes, $attributes);
        $attributes = array_merge($attributes, array_intersect_key($this->attributeMap, $attributes));
        foreach ($attributes as $externalKey => $internalKey) {
            $data[$externalKey] = $rate->getDataUsingMethod($internalKey);
        }

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_shipping_method_prepare', ['data' => $data, 'filter' => $filter, 'resource' => $rate]);
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

    protected function sortRates(Mage_Sales_Model_Quote_Address_Rate $a, Mage_Sales_Model_Quote_Address_Rate $b)
    {
        // Sort by price (lowest first)
        // This is a crappy solution and should be rewritten
        $aSort = intval(round(floatval($a->getPrice()) * 10000));
        $bSort = intval(round(floatval($b->getPrice()) * 10000));
        if ($aSort < $bSort) {
            return -1;
        } elseif ($aSort > $bSort) {
            return 1;
        }

        // Sory by carrier order (lowest first)
        $aSort = $a->getCarrierInstance()->getSortOrder();
        $bSort = $b->getCarrierInstance()->getSortOrder();
        if ($aSort < $bSort) {
            return -1;
        } elseif ($aSort > $bSort) {
            return 1;
        }

        // Sort my method order (lowest first)
        $aSort = intval($a->getSortOrder());
        $bSort = intval($b->getSortOrder());
        if ($aSort < $bSort) {
            return -1;
        } elseif ($aSort > $bSort) {
            return 1;
        }

        return 0;
    }
}
