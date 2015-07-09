[![AOE](aoe-logo.png)](http://www.aoe.com)

# Aoe_CartApi Magento Module [![Build Status](https://travis-ci.org/AOEpeople/Aoe_CartApi.svg?branch=master)](https://travis-ci.org/AOEpeople/Aoe_CartApi)

NOTE: This module is NOT ready for public consuption. Once it is ready we will tag a 1.0.0 version.

NOTE: Following "documentation" is just a dump of some notes while planning this API.

## Cart

### GET /api/rest/cart
Return the current users cart with all subentities
```
{
    qty: 5,
    items: {
        "139303": {
            "item_id": 139303,
            "sku": "ABC123",
            "name": "Thing #1",
            "qty": 5,
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
            "images": {
                "thumbnail": "<url>",
                "small": "<url>",
                "normal": "<url>"
            }
        }
    },
    billing_address: {
        "customer_address_id": null,
        "prefix": null,
        "firstname": null,
        "lastname": null,
        "middlename": null
        "suffix": null,
        "company": null,
        "street": null,
        "city": null,
        "region": null,
        "postcode": null,
        "country_id": null,
        "telephone": null,
        "fax": null,
        "save_in_address_book": false,
    },
    shipping_address: {
        "customer_address_id": null,
        "prefix": null,
        "firstname": null,
        "middlename": null,
        "lastname": null,
        "suffix": null,
        "company": null,
        "street": null,
        "city": null,
        "region": null,
        "postcode": null,
        "country_id": null,
        "telephone": null,
        "fax": null,
        "same_as_billing": true,
        "save_in_address_book": false
        "method": "flatrate_flatrate"
    },
    payment: {},
    shipping_method: "flatrate_flatrate",
    coupon_code: "",
    totals: {
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
    }
}
```

### POST /api/rest/cart
- modify direct attributes of the cart
- coupon
```
{
	"coupon": "FREESTUFF"
}
```

## Other data

### GET /api/rest/cart/addresses
- returns addresses associated with current user
```
[{
	id: ...
	is_default_shipping: true;
	name:	
}, {
}]
```

### GET /api/rest/cart/shipping_methods
get collection of available shipping methods

### GET /api/rest/cart/payment_methods
get collection of available payment methods


## Payment

### GET /api/rest/cart/payment
Return the current selected payment (if any)

### POST /api/rest/cart/payment
Updating payment method:
```
{
	"method": "paypal"
	"data": {
		"token": "ssjklsfjlksjf",
		
	}
}
```


## Cart items

### GET /api/rest/cart/items
get collection of cart items
```
{
    "139303": {
        "item_id": 139303,
        "sku": "ABC123",
        "name": "Thing #1",
        "qty": 5,
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
        "images": {
            "thumbnail": "<url>",
            "small": "<url>",
            "normal": "<url>"
        }
    }
}
```
### POST /api/rest/cart/items (will try to re-use and existing item if possible, qty is optional)
create new cart item
```
{
	"sku": "ABC123"
	"qty": 1
}
```

### GET /api/rest/cart/items/:item_id
get single item
```
{
    "item_id": 139303,
    "sku": "ABC123",
    "name": "Thing #1",
    "qty": 5,
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
    "images": {
        "thumbnail": "<url>",
        "small": "<url>",
        "normal": "<url>"
    }
}
```

### POST /api/rest/cart/items/:item_id (implictly delete with qty=0)
remove/update item
```
{
	"qty": 4
}
```
```
{
	"qty": 0
}
```

### DELETE /api/rest/cart/items/:item_id
remove item


## Other sub-resources

### POST /api/rest/cart/billing_address
Add/update billing address

### POST /api/rest/cart/shipping_address
Add/update shipping address

## ACTIONS 

### POST /api/rest/cart/place
place order

## NOTES
* This module is currently being written for PHP 5.4+ and Magento CE 1.8+ support only.
* Once PHP 5.4 hits EOL, the minimum requirements will be updated to reflect this.
* Once/if Magento CE 1.10 is released then support for Magento CE 1.8 will be dropped.
