<?php

abstract class Aoe_CartApi_Model_Resource extends Mage_Api2_Model_Resource
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

    public function dispatch()
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * @inheritdoc
     */
    public function getFilter()
    {
        $this->_filter = null;
        return parent::getFilter();
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    protected function loadQuote()
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        return $session->getQuote();
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    protected function saveQuote()
    {
        /** @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        $quote = $session->getQuote();
        $quote->collectTotals();
        $quote->save();
        $session->setQuoteId($quote->getId());
        return $quote;
    }

    /**
     * @return Aoe_CartApi_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('Aoe_CartApi');
    }

    /**
     * Remap attribute keys
     *
     * @param array $data
     *
     * @return array
     */
    protected function mapAttributes(array &$data)
    {
        return $this->getHelper()->mapAttributes($this->attributeMap, $data);
    }

    /**
     * Reverse remap the attribute keys
     *
     * @param array $data
     *
     * @return array
     */
    protected function unmapAttributes(array &$data)
    {
        return $this->getHelper()->unmapAttributes($this->attributeMap, $data);
    }

    /**
     * Reverse remap the attribute keys
     *
     * @param array $data
     *
     * @return array
     */
    protected function fixTypes(array $data)
    {
        foreach($this->attributeTypeMap as $code => $type) {
            if(array_key_exists($code, $data) && is_string($data[$code])) {
                switch($type) {
                    case 'int':
                        $data[$code] = intval($data[$code]);
                        break;
                    case 'float':
                        $data[$code] = floatval($data[$code]);
                        break;
                    case 'currency':
                        // TODO: add better processing to this
                        $data[$code] = floatval($data[$code]);
                        break;
                }
            }
        }

        return $data;
    }
}