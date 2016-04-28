<?php

class Aoe_CartApi_Model_Item extends Aoe_CartApi_Model_Resource
{
    /**
     * Hash of external/internal attribute codes
     *
     * @var string[]
     */
    protected $attributeMap = [
        'backorder_qty' => 'backorders',
        'error_info'    => 'error_infos',
    ];

    /**
     * Hash of external attribute codes and their data type
     *
     * @var string[]
     */
    protected $attributeTypeMap = [
        'item_id'        => 'int',
        'qty'            => 'float',
        'original_price' => 'currency',
        'price'          => 'currency',
        'row_total'      => 'currency',
        'backorder_qty'  => 'float',
    ];

    /**
     * Array of external attribute codes that are manually generated
     *
     * @var string[]
     */
    protected $manualAttributes = [
        'original_price',
        'url',
        'images',
        'children',
        'messages',
        'is_saleable',
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
            case self::ACTION_TYPE_COLLECTION . self::OPERATION_CREATE:
                $item = $this->createResource($quote, $this->getRequest()->getBodyParams());
                $new = $item->isObjectNew();
                if ($quote->isObjectNew()) {
                    $this->saveQuote();
                }
                $item->save();
                $this->saveQuote();
                if ($new) {
                    $this->getResponse()->setHttpResponseCode(Mage_Api2_Model_Server::HTTP_CREATED);
                    $this->getResponse()->setHeader('Location', $this->_getLocation($item));
                } else {
                    $this->getResponse()->setHttpResponseCode(Mage_Api2_Model_Server::HTTP_OK);
                    $this->getResponse()->setHeader('Content-Location', $this->_getLocation($item));
                }
                $this->_render($this->prepareResource($item));
                break;
            case self::ACTION_TYPE_COLLECTION . self::OPERATION_DELETE:
                foreach ($quote->getAllVisibleItems() as $item) {
                    /** @var Mage_Sales_Model_Quote_Item $item */
                    $quote->deleteItem($item);
                    $item->delete();
                }
                $this->saveQuote();
                $this->getResponse()->setMimeType($this->getRenderer()->getMimeType());
                $this->getResponse()->setHttpResponseCode(204);
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_RETRIEVE:
                $item = $this->loadItem($quote, $this->getRequest()->getParam('id'));
                $this->_render($this->prepareResource($item));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_CREATE:
                $item = $this->loadItem($quote, $this->getRequest()->getParam('id'));
                $this->updateResource($item, $this->getRequest()->getBodyParams());
                $this->saveQuote();
                $this->_render($this->prepareResource($item));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_UPDATE:
                $item = $this->loadItem($quote, $this->getRequest()->getParam('id'));
                $this->updateResource($item, $this->getRequest()->getBodyParams());
                $this->saveQuote();
                $this->_render($this->prepareResource($item));
                break;
            case self::ACTION_TYPE_ENTITY . self::OPERATION_DELETE:
                $item = $this->loadItem($quote, $this->getRequest()->getParam('id'));
                $quote->deleteItem($item);
                $item->delete();
                $this->saveQuote();
                $this->getResponse()->setMimeType($this->getRenderer()->getMimeType());
                $this->getResponse()->setHttpResponseCode(204);
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
        // Store current state
        $actionType = $this->getActionType();
        $operation = $this->getOperation();

        // Change state
        $this->setActionType(self::ACTION_TYPE_COLLECTION);
        $this->setOperation(self::OPERATION_RETRIEVE);

        $data = [];

        $filter = $this->getFilter();
        foreach ($quote->getAllVisibleItems() as $item) {
            /** @var Mage_Sales_Model_Quote_Item $item */
            // Add data to result
            $data[$item->getId()] = $this->prepareItem($item, $filter);
        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // This collection should always be a key/value hash and never a simple array
        $data = new ArrayObject($data);

        // Return prepared outbound data
        return $data;
    }

    /**
     * Convert the resource model to an array
     *
     * @param Mage_Sales_Model_Quote_Item $resource
     *
     * @return array
     */
    public function prepareResource(Mage_Sales_Model_Quote_Item $resource)
    {
        // Store current state
        $actionType = $this->getActionType();
        $operation = $this->getOperation();

        // Change state
        $this->setActionType(self::ACTION_TYPE_ENTITY);
        $this->setOperation(self::OPERATION_RETRIEVE);

        // Get raw outbound data
        $data = $this->prepareItem($resource, $this->getFilter());

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return prepared outbound data
        return $data;
    }

    protected function prepareItem(Mage_Sales_Model_Quote_Item $item, Mage_Api2_Model_Acl_Filter $filter)
    {
        // Get raw outbound data
        $data = $this->loadResourceAttributes($item, $filter->getAttributesToInclude());

        // =========================
        // BEGIN - Manual attributes
        // =========================

        /** @var Mage_Tax_Model_Config $taxConfig */
        $taxConfig = Mage::getModel('tax/config');

        // row_total - including tax
        if (in_array('row_total', $filter->getAttributesToInclude()) && $taxConfig->displayCartPricesInclTax($item->getStore())) {
            $data['row_total'] = $item->getRowTotalInclTax();
        }

        // original_price
        if (in_array('original_price', $filter->getAttributesToInclude())) {
            $product = $item->getProduct();
            $data['original_price'] = $product->getPriceModel()->getPrice($product);
        }

        // Product URL
        if (in_array('url', $filter->getAttributesToInclude())) {
            $data['url'] = $this->getProductUrl($item);
        }

        // image URLs
        if (in_array('images', $filter->getAttributesToInclude())) {
            $data['images'] = $this->getImageUrls($item->getProduct());
        }

        // child items
        if (!$item->getParentItemId() && in_array('children', $filter->getAttributesToInclude())) {
            $data['children'] = [];
            foreach ($item->getQuote()->getItemsCollection() as $quoteItem) {
                /** @var Mage_Sales_Model_Quote_Item $quoteItem */
                if (!$quoteItem->isDeleted() && $quoteItem->getParentItemId() == $item->getId()) {
                    $quoteItemData = $this->prepareItem($quoteItem, $filter);
                    // Remove the children entry from a child as that kind of nesting is not allowed anyway
                    unset($quoteItemData['children']);
                    $data['children'][] = $quoteItemData;
                }
            }
        }

        // messages
        if (in_array('messages', $filter->getAttributesToInclude())) {
            $data['messages'] = $item->getMessage(false);
        }

        // is_saleable flag
        if (in_array('is_saleable', $filter->getAttributesToInclude())) {
            $data['is_saleable'] = (bool)$item->getProduct()->getIsSalable();
        }

        // =========================
        // END - Manual attributes
        // =========================

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_item_prepare', ['data' => $data, 'filter' => $filter, 'resource' => $item]);
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
     * Create a resource model
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array                  $data
     *
     * @return Mage_Sales_Model_Quote_Item
     */
    public function createResource(Mage_Sales_Model_Quote $quote, array $data)
    {
        // Store current state
        $actionType = $this->getActionType();
        $operation = $this->getOperation();

        // Change state
        $this->setActionType(self::ACTION_TYPE_ENTITY);
        $this->setOperation(self::OPERATION_CREATE);

        // Filter raw incoming data
        $data = $this->getFilter()->in($data);

        // Map data keys
        $data = $this->mapAttributes($data);

        // Validate we have a SKU
        if (!isset($data['sku'])) {
            $this->_critical('Missing SKU', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }

        // Load product based on SKU

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load(Mage::getResourceModel('catalog/product')->getIdBySku($data['sku']));

        // If there is no product with that SKU, throw an error
        if (!$product->getId()) {
            $this->_critical('Invalid SKU ' . $data['sku'], Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }

        // If the SKU is not enabled, throw an error ("isInStock" is a badly named method)
        if (!$product->isInStock()) {
            $this->_critical('Invalid SKU ' . $data['sku'], Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }

        // If the SKU is not visible for the current website, throw an error
        if (!$product->isVisibleInSiteVisibility()) {
            $this->_critical('Invalid SKU ' . $data['sku'], Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }

        if (!Mage::app()->isSingleStoreMode()) {
            // If the SKU is not available for the current website, throw an error
            if (!is_array($product->getWebsiteIds()) || !in_array(Mage::app()->getStore()->getWebsiteId(), $product->getWebsiteIds())) {
                $this->_critical('Invalid SKU ' . $data['sku'], Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
            }
        }

        // Ensure we have a quantity
        if (!isset($data['qty'])) {
            $data['qty'] = 1;
        }
        $data['qty'] = floatval($data['qty']);

        // Ensure we have a min quantity if required
        if (!$quote->hasProductId($product->getId()) && $product->getStockItem()) {
            $minimumQty = floatval($product->getStockItem()->getMinSaleQty());
            if ($minimumQty > 0.0 && $data['qty'] < $minimumQty) {
                $data['qty'] = $minimumQty;
            }
        }

        // Add product to quote
        try {
            $product->setSkipCheckRequiredOption(true);
            $resource = $quote->addProduct($product, new Varien_Object($data));

            // This is to work around a bug in Mage_Sales_Model_Quote::addProductAdvanced
            // The method incorrectly returns $item when it SHOULD return $parentItem
            if ($resource->getParentItem()) {
                $resource = $resource->getParentItem();
            }
        } catch (Exception $e) {
            $resource = $e->getMessage();
        }

        // Check for errors
        if (is_string($resource)) {
            $this->_critical($resource, Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return updated resource
        return $resource;
    }

    /**
     * Update the resource model
     *
     * @param Mage_Sales_Model_Quote_Item $resource
     * @param array                       $data
     *
     * @return Mage_Sales_Model_Quote_Item
     */
    public function updateResource(Mage_Sales_Model_Quote_Item $resource, array $data)
    {
        // Store current state
        $actionType = $this->getActionType();
        $operation = $this->getOperation();

        // Change state
        $this->setActionType(self::ACTION_TYPE_ENTITY);
        $this->setOperation(self::OPERATION_UPDATE);

        // Get a filter instance
        $filter = $this->getFilter();

        // Fire event - before filter
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_item_update_prefilter', ['data' => $data, 'filter' => $filter, 'resource' => $resource]);
        $data = $data->getData();

        // Get allowed attributes
        $allowedAttributes = $filter->getAllowedAttributes(Mage_Api2_Model_Resource::OPERATION_ATTRIBUTE_WRITE);

        // Manually prevent SKU for updates
        $allowedAttributes = array_diff($allowedAttributes, ['sku']);

        // Update model
        $this->saveResourceAttributes($resource, $allowedAttributes, $data);

        // Fire event
        $data = new Varien_Object($data);
        Mage::dispatchEvent('aoe_cartapi_item_update', ['data' => $data, 'filter' => $filter, 'resource' => $resource]);
        //$data = $data->getData();

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

        // Return updated model
        return $resource;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param int                    $id
     *
     * @return Mage_Sales_Model_Quote_Item
     *
     * @throws Exception
     */
    protected function loadItem(Mage_Sales_Model_Quote $quote, $id)
    {
        $id = intval($id);
        if (!$id) {
            $this->_critical('Not Found', Mage_Api2_Model_Server::HTTP_NOT_FOUND);
        }

        /** @var Mage_Sales_Model_Quote_Item $item */
        $item = $quote->getItemById($id);
        if (!$item || $item->isDeleted() || $item->getParentItemId()) {
            $this->_critical('Not Found', Mage_Api2_Model_Server::HTTP_NOT_FOUND);
        }

        return $item;
    }

    protected function getProductUrl(Mage_Sales_Model_Quote_Item $item)
    {
        if ($item->getRedirectUrl()) {
            return $item->getRedirectUrl();
        }

        $product = $item->getProduct();
        $option = $item->getOptionByCode('product_type');
        if ($option) {
            $product = $option->getProduct();
        }

        return $product->getUrlModel()->getUrl($product);
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
