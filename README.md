Wabel's Zoho-CRM Client Wrapper
====================

It's a migration and extraction from [zoho-crm-orm](https://github.com/Wabel/zoho-crm-orm/tree/1.2) for using the API v2 

What is this?
-------------

This project is a PHP wrapper for  ZOHO CRM Client ([zcrm-php-sdk](https://github.com/zoho/zcrm-php-sdk)). Use this connector to access ZohoCRM data from your PHP application.

Initialize the client?
-------------------------------------
```

Targetting the correct Zoho API
-------------------------------

Out of the box, the client will point to the `https://crm.zoho.com/crm/private` endpoint.
If your endpoint is different (some users are pointing to `https://crm.zoho.eu/crm/private`), you can
use the third parameter of the `Client` constructor:

```php
$zohoClient = new ZohoClient([
    'client_id' => 'xxxxxxxxxxxxxxxxxxxxxx',
     'client_secret' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'redirect_uri' => 'http://xxxxxxxxx.com/bakcxxxx',
    'currentUserEmail' => 'xxxxx@test.fr',
    'applicationLogFilePath' => '/xxx/xxx/',
    'sandbox' => true or false,
    'apiBaseUrl' => '',
    'apiVersion' => '',
    'access_type' => '',
    'accounts_url' => '',
    'persistence_handler_class' => '',
    'token_persistence_path' => ''
], 'Europe/Paris);
```  

Zoho CRM Commands
-------------------------------------
The project also comes with a Symfony Command.

The command's constructor takes in parameter the `ZohoClient`

Usage:

```sh
# Command to generate access token
$ console zohocrm:cmd generate-access-token xxxxxxx
```

Setting up unit tests
---------------------

Interested in contributing? You can easily set up the unit tests environment:
Read how to change the client configuration - read [Configuration](https://github.com/zoho/zcrm-php-sdk)
- copy the `phpunit.xml.dist` file into `phpunit.xml`
- change the stored environment variable `client_secret`
- change the stored environment variable `redirect_uri`
- change the stored environment variable `currentUserEmail`
- change the stored environment variable `applicationLogFilePath`
- change the stored environment variable `persistence_handler_class`
- change the stored environment variable `token_persistence_path`
- change the stored environment variable `userid_test`
- change the stored environment variable `timeZone`
- change the stored environment variable `deal_status`
- change the stored environment variable `campaign_type`
- change the stored environment variable `filepath_upload`
