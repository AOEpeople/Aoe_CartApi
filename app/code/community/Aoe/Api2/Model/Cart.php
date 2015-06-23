<?php
/**
 * Cart
 *
 * @author Fabrizio Branca
 * @since 2015-06-18
 */
class Aoe_Api2_Model_Cart extends Mage_Api2_Model_Resource
{

    public function _retrieve() {
        $tmp = new Varien_Object();
        $tmp->setMessage('Hello World');
        return $tmp;
    }
}