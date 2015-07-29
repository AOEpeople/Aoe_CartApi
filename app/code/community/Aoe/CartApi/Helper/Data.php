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

    public function fixAddressData(array $data, $oldCountryId, $oldRegionId)
    {
        if (array_key_exists('country_id', $data) && !array_key_exists('region', $data)) {
            $data['region'] = $oldRegionId;
        }

        if (array_key_exists('region', $data)) {
            // Clear previous region_id
            $data['region_id'] = null;

            // Grab country_id
            $countryId = (array_key_exists('country_id', $data) ? $data['country_id'] : $oldCountryId);

            /** @var Mage_Directory_Model_Region $regionModel */
            $regionModel = Mage::getModel('directory/region');
            if (is_numeric($data['region'])) {
                $regionModel->load($data['region']);
                if ($regionModel->getId() && (empty($countryId) || $regionModel->getCountryId() == $countryId)) {
                    $data['region'] = $regionModel->getName();
                    $data['region_id'] = $regionModel->getId();
                    $data['country_id'] = $regionModel->getCountryId();
                }
            } elseif (!empty($countryId)) {
                $regionModel->loadByCode($data['region'], $countryId);
                if (!$regionModel->getId()) {
                    $regionModel->loadByName($data['region'], $countryId);
                }
                if ($regionModel->getId()) {
                    $data['region'] = $regionModel->getName();
                    $data['region_id'] = $regionModel->getId();
                }
            }
        }

        return $data;
    }
}
