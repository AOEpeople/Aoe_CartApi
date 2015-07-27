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
        $quote->getBillingAddress();
        $quote->getShippingAddress()->setCollectShippingRates(true);
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
     * Type cast array values
     *
     * @param array $data
     * @param array $typeMap
     *
     * @return array
     *
     * @throws Zend_Currency_Exception
     * @throws Zend_Locale_Exception
     */
    protected function fixTypes(array $data, array $typeMap = [])
    {
        // This makes me a bit nervous
        $currencyCode = $this->loadQuote()->getQuoteCurrencyCode();

        if (empty($typeMap)) {
            $typeMap = $this->attributeTypeMap;
        }

        foreach ($typeMap as $code => $type) {
            if (array_key_exists($code, $data) && (is_scalar($data[$code]) || is_null($data[$code]))) {
                switch ($type) {
                    case 'bool':
                        $data[$code] = (!empty($data[$code]) && strtolower($data[$code]) !== 'false');
                        break;
                    case 'int':
                        $data[$code] = intval($data[$code]);
                        break;
                    case 'float':
                        $data[$code] = floatval($data[$code]);
                        break;
                    case 'currency':
                        $amount = floatval($data[$code]);
                        $precision = Zend_Locale_Data::getContent(null, 'currencyfraction', $currencyCode);
                        if ($precision === false) {
                            $precision = Zend_Locale_Data::getContent(null, 'currencyfraction');
                        }
                        if ($precision !== false) {
                            $amount = round($amount, $precision);
                            $formatted = Mage::app()->getLocale()->currency($currencyCode)->toCurrency($amount, ['precision' => $precision]);
                        } else {
                            $formatted = Mage::app()->getLocale()->currency($currencyCode)->toCurrency($amount);
                        }
                        $data[$code] = ['currency' => $currencyCode, 'amount' => $amount, 'formatted' => $formatted];
                        break;
                    case 'string':
                    default:
                        $data[$code] = (string)$data[$code];
                        break;
                }
            }
        }

        return $data;
    }
}
