<?php

/**
 * Data Helper
 */
class Aoe_CartApi_Helper_Data extends Mage_Core_Helper_Data
{
    /**
     * Remap attribute keys
     *
     * @param array $map
     * @param array $data
     *
     * @return array
     */
    public function mapAttributes(array $map, array &$data)
    {
        $out = [];

        foreach ($data as $key => &$value) {
            if (isset($map[$key])) {
                $key = $map[$key];
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Reverse remap the attribute keys
     *
     * @param array $map
     * @param array $data
     *
     * @return array
     */
    public function unmapAttributes(array $map, array &$data)
    {
        return $this->mapAttributes(array_flip($map), $data);
    }
}
