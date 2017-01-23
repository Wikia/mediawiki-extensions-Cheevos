# Swagger\Client\DefaultApi

All URIs are relative to *https://cheevos.cursetech.com/v1*

Method | HTTP request | Description
------------- | ------------- | -------------
[**achievementIdGet**](DefaultApi.md#achievementIdGet) | **GET** /achievement/{id} | 
[**achievementsAllGet**](DefaultApi.md#achievementsAllGet) | **GET** /achievements/all | 
[**incrementPost**](DefaultApi.md#incrementPost) | **POST** /increment | 


# **achievementIdGet**
> \Swagger\Client\Model\ErrorResponse achievementIdGet($id)



Get an achievement by ID.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$api_instance = new Swagger\Client\Api\DefaultApi();
$id = 56; // int | Achievement id

try {
    $result = $api_instance->achievementIdGet($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementIdGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **id** | **int**| Achievement id |

### Return type

[**\Swagger\Client\Model\ErrorResponse**](../Model/ErrorResponse.md)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **achievementsAllGet**
> \Swagger\Client\Model\InlineResponse2001 achievementsAllGet($site_id, $limit, $offset)



List achievements.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$api_instance = new Swagger\Client\Api\DefaultApi();
$site_id = 56; // int | The site id to use for locally overridden achievements.
$limit = 56; // int | Maximum number of items in the result.  Defaults to 25. Maximum is 200.
$offset = 56; // int | Number of items to skip in the result.  Defaults to 0.

try {
    $result = $api_instance->achievementsAllGet($site_id, $limit, $offset);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementsAllGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **site_id** | **int**| The site id to use for locally overridden achievements. | [optional]
 **limit** | **int**| Maximum number of items in the result.  Defaults to 25. Maximum is 200. | [optional]
 **offset** | **int**| Number of items to skip in the result.  Defaults to 0. | [optional]

### Return type

[**\Swagger\Client\Model\InlineResponse2001**](../Model/InlineResponse2001.md)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **incrementPost**
> \Swagger\Client\Model\InlineResponse200 incrementPost($body)



Increment one or more statistics for a user on a site.  If the user earns any achievements after this increment, they will be present in the response's `earned` array.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$api_instance = new Swagger\Client\Api\DefaultApi();
$body = new \Swagger\Client\Model\Body(); // \Swagger\Client\Model\Body | 

try {
    $result = $api_instance->incrementPost($body);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->incrementPost: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **body** | [**\Swagger\Client\Model\Body**](../Model/\Swagger\Client\Model\Body.md)|  |

### Return type

[**\Swagger\Client\Model\InlineResponse200**](../Model/InlineResponse200.md)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

