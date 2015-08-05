[![AOE](aoe-logo.png)](http://www.aoe.com)

# Aoe_CartApi Magento Module [![Build Status](https://travis-ci.org/AOEpeople/Aoe_CartApi.svg?branch=master)](https://travis-ci.org/AOEpeople/Aoe_CartApi)

**NOTE**: This module is NOT ready for public consuption. Once it is ready we will tag a 1.0.0 version.

**NOTE**: Following "documentation" is just a dump of some notes while planning this API.

## Primary cart API endpoints

### GET /api/rest/cart
Return the cart (quote) for the current frontend Magento session

    {
        "email": "fake@example.com",
        "coupon_code": "",
        "shipping_method": "flatrate_flatrate",
        "qty": 5,
        "totals": {
            "subtotal": {
                "title": "Subtotal",
                "formatted": "$0.00",
                "amount": 0,
                "currency": "USD"
            },
            "shipping": {
                "title": "Shipping",
                "formatted": "$0.00",
                "amount": 0,
                "currency": "USD"
            },
            "discount": {
                "title": "Discount"
                "formatted": "$0.00",
                "amount": 0,
                "currency": "USD"
            },
            "tax": {
                "title": "Tax",
                "formatted": "$0.00",
                "amount": 0,
                "currency": "USD"
            },
            "grand_total": {
                "title": "Grand Total",
                "formatted": "$0.00",
                "amount": 0,
                "currency": "USD"
            }
        },
        "messages": {
            "error": [
                "error message #1",
                "error message #2"
            ]
            "notice": [
                "notice message #1",
                "notice message #2"
            ]
            "success": [
                "success message #1",
                "success message #2"
            ]
        },
        "has_error": false
    }

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * email
        * coupon_code
        * shipping_method
        * qty
        * totals
        * messages
        * has_error
* embed
    * comma separated list of sub-resources to embed
        * items
        * billing_address
        * shipping_address
        * shipping_methods

### POST /api/rest/cart
Update attributes of the cart resource. Using the 'embed' query parameter will allow updating of a limited subset of sub-resources as well.

    {
        "coupon_code": "FREESTUFF"
    }
    
Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * email
        * coupon_code
        * shipping_method
        * qty
        * totals
        * messages
        * has_error
* embed
    * comma separated list of sub-resources to embed (R) and possibly update (W)
        * items (R)
        * billing_address (R/W)
        * shipping_address (R/W)
        * shipping_methods (R)

### DELETE /api/rest/cart
Reset the cart and all sub-resources

### GET /api/rest/cart/items
Get collection of cart items. This will always return a JS object as result, even if the collection is empty.

    {
        "139303": {
            "item_id": 139303,
            "sku": "ABC123",
            "name": "Thing #1",
            "images": {
                "normal": "<url>",
                "small": "<url>",
                "thumbnail": "<url>",
            },
            "children": []
            "qty": 5,
            "backorder_qty": 5,
            "original_price": {
                "formatted": "$0.00",
                "amount": 0,
                "currency": "USD"
            },
            "price": {
                "formatted": "$0.00",
                "amount": 0,
                "currency": "USD"
            },
            "row_total": {
                "formatted": "$0.00",
                "amount": 0,
                "currency": "USD"
            },
            "error_info": {},
            "is_saleable": true
        }
    }

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * item_id
        * sku
        * name
        * images
        * children
        * qty
        * backorder_qty
        * original_price
        * price
        * row_total
        * messages
        * error_info
        * is_saleable

### POST /api/rest/cart/items
Add an product to the cart. This will re-use existing items in the cart if possible. The qty attribute is optional and will default to a single unit.

    {
        "sku": "ABC123"
        "qty": 1
    }

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * item_id
        * sku
        * name
        * images
        * children
        * qty
        * backorder_qty
        * original_price
        * price
        * row_total
        * messages
        * error_info
        * is_saleable

### DELETE /api/rest/cart/items
Remove all items from the cart

### GET /api/rest/cart/items/:item_id
Get a specific cart item

    {
        "item_id": 139303,
        "sku": "ABC123",
        "name": "Thing #1",
        "images": {
            "normal": "<url>",
            "small": "<url>",
            "thumbnail": "<url>",
        },
        "children": []
        "qty": 5,
        "backorder_qty": 5,
        "original_price": {
            "formatted": "$0.00",
            "amount": 0,
            "currency": "USD"
        },
        "price": {
            "formatted": "$0.00",
            "amount": 0,
            "currency": "USD"
        },
        "row_total": {
            "formatted": "$0.00",
            "amount": 0,
            "currency": "USD"
        },
        "error_info": {},
        "is_saleable": true
    }

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * item_id
        * sku
        * name
        * images
        * children
        * qty
        * backorder_qty
        * original_price
        * price
        * row_total
        * messages
        * error_info
        * is_saleable

### PUT/POST /api/rest/cart/items/:item_id
Update the quantity for an item in the cart
    
    {
        "qty": 4
    }

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * item_id
        * sku
        * name
        * images
        * children
        * qty
        * backorder_qty
        * original_price
        * price
        * row_total
        * messages
        * error_info
        * is_saleable

### DELETE /api/rest/cart/items/:item_id
Remove an item from the cart

### GET /api/rest/cart/billing_address
Return the billing address linked to the cart

    {
        "firstname": "John",
        "middlename": "Quincy",
        "lastname": "Public",
        "prefix": "Mr.",
        "suffix": "Jr.",
        "company": "Acme Inc.",
        "street":[
            "Street 1",
            "Street 2"
        ],
        "city": "Burlingame",
        "region": "California",
        "postcode": "00000",
        "country_id": "US",
        "telephone": "000-000-0000",
        "validation_errors":[
            "Error Text",
            "Error Text"
        ]
    }

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * firstname
        * middlename
        * lastname
        * prefix
        * suffix
        * company
        * street
        * city
        * region
        * postcode
        * country_id
        * telephone
        * formatted_html
        * formatted_text
        * validation_errors - This will **only** be populated in response to a PUT/POST

### PUT/POST /api/rest/cart/billing_address
Update the billing address. All attributes are optional.

Regions are a bit of 'magic'. 
You can send the Mage_Directory DB ID, The region 'code', or the region 'name'. 
The code and name are looked up in relation to the currently selected country.
The stored region is either the valid region name (looked up by ID/Code/Name) or the value sent as-is.

    {
        "region": "FL"
    }

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * firstname
        * middlename
        * lastname
        * prefix
        * suffix
        * company
        * street
        * city
        * region
        * postcode
        * country_id
        * telephone
        * formatted_html
        * formatted_text
        * validation_errors - This will **only** be populated in response to a PUT/POST

### DELETE /api/rest/cart/billing_address
Reset the billing address

### GET /api/rest/cart/shipping_address
Return the shipping address linked to the cart

    {
        "firstname": "John",
        "middlename": "Quincy",
        "lastname": "Public",
        "prefix": "Mr.",
        "suffix": "Jr.",
        "company": "Acme Inc.",
        "street":[
            "Street 1",
            "Street 2"
        ],
        "city": "Burlingame",
        "region": "California",
        "postcode": "00000",
        "country_id": "US",
        "telephone": "000-000-0000",
        "validation_errors":[
            "Error Text",
            "Error Text"
        ]
    }

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * firstname
        * middlename
        * lastname
        * prefix
        * suffix
        * company
        * street
        * city
        * region
        * postcode
        * country_id
        * telephone
        * formatted_html
        * formatted_text
        * validation_errors - This will **only** be populated in response to a PUT/POST

### PUT/POST /api/rest/cart/shipping_address
Update the shipping address All attributes are optional.

Regions are a bit of 'magic'. 
You can send the Mage_Directory DB ID, The region 'code', or the region 'name'. 
The code and name are looked up in relation to the currently selected country.
The stored region is either the valid region name (looked up by ID/Code/Name) or the value sent as-is.

    {
        "region": "FL"
    }

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * firstname
        * middlename
        * lastname
        * prefix
        * suffix
        * company
        * street
        * city
        * region
        * postcode
        * country_id
        * telephone
        * formatted_html
        * formatted_text
        * validation_errors - This will **only** be populated in response to a PUT/POST

### DELETE /api/rest/cart/shipping_address
Reset the shipping address

## Additional cart related resources

### GET /api/rest/cart/shipping_methods
Return a collection of available shipping methods. 
**NOTE**: This collection changes as the cart data changes.

    [
        {
            "code": "flaterate_flaterate"
            "carrier": "flaterate"
            "carrier_title": "Flat Rate"
            "method": "flaterate"
            "method_title": "Flat Rate"
            "description": "Flat rate shipping"
            "price": {
                "formatted": "$0.00"
                "currency": "USD",
                "amount": 0,
            }
        }
    ]

Supported query parameters

* attrs
    * comma separated list of resource attributes you want returned 
        * code
        * carrier
        * carrier_title
        * method
        * method_title
        * description
        * price

## NOTES
* This module is currently being written for PHP 5.4+ and Magento CE 1.8+ support only.
* When PHP 5.4 hits EOL, the minimum requirements will be updated to reflect this.
* If/when Magento CE 1.10 is released then support for Magento CE 1.8 will be dropped.
