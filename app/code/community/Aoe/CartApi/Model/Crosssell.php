<?php

class Aoe_CartApi_Model_Crosssell extends Aoe_CartApi_Model_Resource
{
    const PAGE_SIZE_DEFAULT = 0;

    /**
     * Hash of external/internal attribute codes
     *
     * @var string[]
     */
    protected $attributeMap = [
        'url' => 'url_in_store',
    ];

    /**
     * Hash of external attribute codes and their data type
     *
     * @var string[]
     */
    protected $attributeTypeMap = [
        'sku'               => 'string',
        'name'              => 'string',
        'description'       => 'string',
        'short_description' => 'string',
        'url'               => 'string',
        'is_saleable'       => 'bool',
        'is_in_stock'       => 'bool',
        'qty'               => 'float',
        'min_sale_qty'      => 'float',
        'max_sale_qty'      => 'float',
        'price'             => 'currency',
        'final_price'       => 'currency',
    ];

    /**
     * Array of external attribute codes that are manually generated
     *
     * @var string[]
     */
    protected $manualAttributes = [
        'is_saleable',
        'is_in_stock',
        'qty',
        'min_sale_qty',
        'max_sale_qty',
        'images',
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
    public function prepareCollection(Mage_Sales_Model_Quote $quote, $applyCollectionModifiers = true)
    {
        // This collection should always be a key/value hash and never a simple array
        $data = new ArrayObject();

        if ($quote->isVirtual()) {
            return $data;
        }

        // Store current state
        $actionType = $this->getActionType();
        $operation = $this->getOperation();

        // Change state
        $this->setActionType(self::ACTION_TYPE_COLLECTION);
        $this->setOperation(self::OPERATION_RETRIEVE);

        // Get filter
        $filter = $this->getFilter();

        // Prepare collection
        foreach ($this->getCrosssellProducts($quote, $applyCollectionModifiers) as $product) {
            $data[$product->getSku()] = $this->prepareProduct($product, $filter);
        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return prepared outbound data
        return $data;
    }

    protected function prepareProduct(Mage_Catalog_Model_Product $product, Mage_Api2_Model_Acl_Filter $filter)
    {
        $data = [];

        // Get raw outbound data
        $attributes = array_diff($filter->getAttributesToInclude(), $this->manualAttributes);
        $attributes = array_combine($attributes, $attributes);
        $attributes = array_merge($attributes, array_intersect_key($this->attributeMap, $attributes));
        foreach ($attributes as $externalKey => $internalKey) {
            $data[$externalKey] = $product->getDataUsingMethod($internalKey);
        }

        // =========================
        // BEGIN - Manual attributes
        // =========================

        // Add stock data
        if (in_array('is_saleable', $attributes)) {
            $data['is_saleable'] = $product->isSaleable();
        }
        $stockItem = $product->getStockItem();
        if ($stockItem instanceof Mage_CatalogInventory_Model_Stock_Item) {
            if (in_array('is_in_stock', $attributes)) {
                $data['is_in_stock'] = $stockItem->getIsInStock();
            }
            if (in_array('qty', $attributes)) {
                $data['qty'] = $stockItem->getQty();
            }
            if (in_array('min_sale_qty', $attributes)) {
                $data['min_sale_qty'] = $stockItem->getMinSaleQty();
            }
            if (in_array('max_sale_qty', $attributes)) {
                $data['max_sale_qty'] = $stockItem->getMaxSaleQty();
            }
        }

        // Add images
        if (in_array('images', $filter->getAttributesToInclude())) {
            $data['images'] = $this->getImageUrls($product);
        }

        // =========================
        // END - Manual attributes
        // =========================

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_crosssell_prepare', ['data' => $data, 'filter' => $filter, 'resource' => $product]);
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

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param bool                   $applyCollectionModifiers
     *
     * @return Mage_Catalog_Model_Product[]
     */
    protected function getCrosssellProducts(Mage_Sales_Model_Quote $quote, $applyCollectionModifiers = true)
    {
        $cartProductIds = [];
        foreach ($quote->getAllItems() as $item) {
            /** @var Mage_Sales_Model_Quote_Item $item */
            if ($product = $item->getProduct()) {
                $cartProductIds[] = intval($product->getId());
            }
        }

        if ($cartProductIds) {
            /** @var Mage_Catalog_Model_Resource_Product_Link_Product_Collection $collection */
            $collection = Mage::getModel('catalog/product_link')->useCrossSellLinks()->getProductCollection();
            $collection->setStoreId($quote->getStoreId());
            $collection->addStoreFilter();
            //$collection->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes());
            $collection->addAttributeToSelect('*');
            $collection->addUrlRewrite();
            $collection->addAttributeToFilter('status', ['eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED]);
            Mage::getSingleton('catalog/product_visibility')->addVisibleInSiteFilterToCollection($collection);
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
            $collection->applyFrontendPriceLimitations();

            $collection->addProductFilter($cartProductIds);
            $collection->addExcludeProductFilter($cartProductIds);
            $collection->setGroupBy();
            $collection->setPositionOrder();

            if ($applyCollectionModifiers) {
                $this->_applyCollectionModifiers($collection);
            }

            /** @var Mage_CatalogInventory_Model_Stock $stock */
            $stock = Mage::getModel('cataloginventory/stock');
            $stock->addItemsToProducts($collection);

            return $collection->getItems();
        } else {
            return [];
        }
    }

    protected function getImageUrls(Mage_Catalog_Model_Product $product)
    {
        $data = [];

        /** @var Mage_Catalog_Helper_Image $helper */
        $helper = Mage::helper('catalog/image');

        // Add normal URL
        $helper->init($product, 'image');
        $size = Mage::getStoreConfig(Mage_Catalog_Helper_Image::XML_NODE_PRODUCT_BASE_IMAGE_WIDTH);
        if (is_numeric($size)) {
            $helper->constrainOnly(true)->resize($size);
        }
        $data['normal'] = $helper->__toString();

        // Add small URL
        $helper->init($product, 'small_image');
        $size = Mage::getStoreConfig(Mage_Catalog_Helper_Image::XML_NODE_PRODUCT_SMALL_IMAGE_WIDTH);
        if (is_numeric($size)) {
            $helper->constrainOnly(true)->resize($size);
        }
        $data['small'] = $helper->__toString();

        // Add thumbnail URL
        $helper->init($product, 'thumbnail');
        $size = Mage::getStoreConfig('catalog/product_image/thumbnail_width');
        if (is_numeric($size)) {
            $helper->constrainOnly(true)->resize($size);
        }
        $data['thumbnail'] = $helper->__toString();

        return $data;
    }
}
