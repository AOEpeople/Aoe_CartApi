[![AOE](aoe-logo.png)](http://www.aoe.com)

# Aoe_CartApi Magento Module [![Build Status](https://travis-ci.org/AOEpeople/Aoe_CartApi.svg?branch=master)](https://travis-ci.org/AOEpeople/Aoe_CartApi)

NOTE: This module is NOT ready for public consuption. Once it is ready we will tag a 1.0.0 version.

NOTE: Following "documentation" is just a dump of some notes while planning this API.

## Cart

### GET /api/rest/cart
Return the current users cart with all subentities
```
{
	messages: [], -  review for later
	id: "",
	items: [{
		sku: "",
		quantity: 5
	}, {
	}],
	billing_address: {
	},
	shipping_address: {
	},
	payment: { <- spearate resource
	},
	shipping_method: "", <- string attribute
	coupon: "",
	totals: {
	}
}
```

### POST /api/rest/cart
- modify direct attributes of the cart
- shipping_method
- coupon
```
{
	"shipping_method": "s_ground_ground"
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

### POST /api/rest/cart/items
create new cart item

### GET /api/rest/cart/items/:item_id
get single item

### POST /api/rest/cart/items/:item_id (implictly delete with qty=0)
remove/update item

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
