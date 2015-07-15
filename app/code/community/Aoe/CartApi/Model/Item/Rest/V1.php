<?php

class Aoe_CartApi_Model_Item_Rest_V1 extends Aoe_CartApi_Model_Resource
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
        'item_id'        => 'int',
        'qty'            => 'float',
        'original_price' => 'currency',
        'price'          => 'currency',
        'row_total'      => 'currency',
        'backorder_qty'  => 'float',
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
                $this->saveQuote();
                //$this->getResponse()->setHttpResponseCode(Mage_Api2_Model_Server::HTTP_CREATED);
                $this->getResponse()->setHeader('Location', $this->_getLocation($item));
                $this->_render($this->prepareResource($item));
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

        $data = array();

        $filter = $this->getFilter();
        foreach ($quote->getAllVisibleItems() as $item) {
            /** @var Mage_Sales_Model_Quote_Item $item */

            // Get raw outbound data
            $itemData = $item->toArray();

            // Manual data addition
            $itemData['original_price'] = $item->getProduct()->getPrice();
            $itemData['backorder_qty'] = floatval($item->getBackorders());
            $itemData['messages'] = $item->getMessage(false);

            // Map data keys
            $itemData = $this->unmapAttributes($itemData);

            // Filter raw outbound data
            $itemData = $filter->out($itemData);

            // Add image URLs
            $itemData['images'] = $this->getImageUrls($item->getProduct());

            // Fix data types
            $itemData = $this->fixTypes($itemData);

            // Add data to result
            $data[$item->getId()] = $itemData;
        }

        // Restore old state
        $this->setActionType($actionType);
        $this->setOperation($operation);

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
        $data = $resource->toArray();

        // Manual data addition
        $data['original_price'] = $resource->getProduct()->getPrice();
        $data['backorder_qty'] = floatval($resource->getBackorders());
        $data['messages'] = $resource->getMessage(false);

        // Map data keys
        $data = $this->unmapAttributes($data);

        // Filter raw outbound data
        $data = $this->getFilter()->out($data);

        // Add image URLs
        $data['images'] = $this->getImageUrls($resource->getProduct());

        // Fix data types
        $data = $this->fixTypes($data);

        // Add null values for missing data
        foreach ($this->getFilter()->getAttributesToInclude() as $code) {
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
        if (!$product->getId()) {
            $this->_critical('Invalid SKU', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }

        // Add product to quote
        try {
            $product->setSkipCheckRequiredOption(true);
            $resource = $quote->addProduct($product, new Varien_Object($data));
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

        // Filter raw incoming data
        $data = $this->getFilter()->in($data);

        // Map data keys
        $data = $this->mapAttributes($data);

        // Update model
        foreach ($data as $key => $value) {
            $resource->setDataUsingMethod($key, $value);
        }

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

    protected function getImageUrls(Mage_Catalog_Model_Product $product)
    {
        $data = array();

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
