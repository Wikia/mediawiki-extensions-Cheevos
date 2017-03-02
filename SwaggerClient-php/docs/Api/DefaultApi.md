# Swagger\Client\DefaultApi

All URIs are relative to *https://cheevos.cursetech.com/v1*

Method | HTTP request | Description
------------- | ------------- | -------------
[**achievementCategoriesAllGet**](DefaultApi.md#achievementCategoriesAllGet) | **GET** /achievement_categories/all | 
[**achievementCategoryIdDelete**](DefaultApi.md#achievementCategoryIdDelete) | **DELETE** /achievement_category/{id} | 
[**achievementCategoryIdGet**](DefaultApi.md#achievementCategoryIdGet) | **GET** /achievement_category/{id} | 
[**achievementCategoryIdPut**](DefaultApi.md#achievementCategoryIdPut) | **PUT** /achievement_category/{id} | 
[**achievementCategoryPut**](DefaultApi.md#achievementCategoryPut) | **PUT** /achievement_category | 
[**achievementIdDelete**](DefaultApi.md#achievementIdDelete) | **DELETE** /achievement/{id} | 
[**achievementIdGet**](DefaultApi.md#achievementIdGet) | **GET** /achievement/{id} | 
[**achievementIdPut**](DefaultApi.md#achievementIdPut) | **PUT** /achievement/{id} | 
[**achievementPut**](DefaultApi.md#achievementPut) | **PUT** /achievement | 
[**achievementsAllGet**](DefaultApi.md#achievementsAllGet) | **GET** /achievements/all | 
[**incrementPost**](DefaultApi.md#incrementPost) | **POST** /increment | 
[**statsGet**](DefaultApi.md#statsGet) | **GET** /stats | 


# **achievementCategoriesAllGet**
> \Swagger\Client\Model\InlineResponse2002 achievementCategoriesAllGet($limit, $offset)



List achievement categories.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$api_instance = new Swagger\Client\Api\DefaultApi();
$limit = 25; // int | Maximum number of items in the result.
$offset = 0; // int | Number of items to skip in the result.  Defaults to 0.

try {
    $result = $api_instance->achievementCategoriesAllGet($limit, $offset);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementCategoriesAllGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **limit** | **int**| Maximum number of items in the result. | [optional] [default to 25]
 **offset** | **int**| Number of items to skip in the result.  Defaults to 0. | [optional] [default to 0]

### Return type

[**\Swagger\Client\Model\InlineResponse2002**](../Model/InlineResponse2002.md)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **achievementCategoryIdDelete**
> \Swagger\Client\Model\SuccessResponse achievementCategoryIdDelete($id, $author_id)



Delete an achievement category.  Will fail if any achievements still belong to the category.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure API key authorization: client_id
Swagger\Client\Configuration::getDefaultConfiguration()->setApiKey('Client-ID', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// Swagger\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Client-ID', 'Bearer');

$api_instance = new Swagger\Client\Api\DefaultApi();
$id = 56; // int | Achievement category id
$author_id = 56; // int | User id who caused the delete request

try {
    $result = $api_instance->achievementCategoryIdDelete($id, $author_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementCategoryIdDelete: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **id** | **int**| Achievement category id |
 **author_id** | **int**| User id who caused the delete request |

### Return type

[**\Swagger\Client\Model\SuccessResponse**](../Model/SuccessResponse.md)

### Authorization

[client_id](../../README.md#client_id)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **achievementCategoryIdGet**
> \Swagger\Client\Model\AchievementCategory achievementCategoryIdGet($id)



Get an achievement category by ID.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$api_instance = new Swagger\Client\Api\DefaultApi();
$id = 56; // int | Achievement category id

try {
    $result = $api_instance->achievementCategoryIdGet($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementCategoryIdGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **id** | **int**| Achievement category id |

### Return type

[**\Swagger\Client\Model\AchievementCategory**](../Model/AchievementCategory.md)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **achievementCategoryIdPut**
> \Swagger\Client\Model\SuccessResponse achievementCategoryIdPut($id, $body)



Update an achievement category.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure API key authorization: client_id
Swagger\Client\Configuration::getDefaultConfiguration()->setApiKey('Client-ID', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// Swagger\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Client-ID', 'Bearer');

$api_instance = new Swagger\Client\Api\DefaultApi();
$id = 56; // int | Achievement category id
$body = new \Swagger\Client\Model\AchievementCategory(); // \Swagger\Client\Model\AchievementCategory | Achievement category

try {
    $result = $api_instance->achievementCategoryIdPut($id, $body);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementCategoryIdPut: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **id** | **int**| Achievement category id |
 **body** | [**\Swagger\Client\Model\AchievementCategory**](../Model/\Swagger\Client\Model\AchievementCategory.md)| Achievement category |

### Return type

[**\Swagger\Client\Model\SuccessResponse**](../Model/SuccessResponse.md)

### Authorization

[client_id](../../README.md#client_id)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **achievementCategoryPut**
> \Swagger\Client\Model\SuccessResponse achievementCategoryPut($body)



Create an achievement category.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure API key authorization: client_id
Swagger\Client\Configuration::getDefaultConfiguration()->setApiKey('Client-ID', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// Swagger\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Client-ID', 'Bearer');

$api_instance = new Swagger\Client\Api\DefaultApi();
$body = new \Swagger\Client\Model\AchievementCategory(); // \Swagger\Client\Model\AchievementCategory | 

try {
    $result = $api_instance->achievementCategoryPut($body);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementCategoryPut: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **body** | [**\Swagger\Client\Model\AchievementCategory**](../Model/\Swagger\Client\Model\AchievementCategory.md)|  |

### Return type

[**\Swagger\Client\Model\SuccessResponse**](../Model/SuccessResponse.md)

### Authorization

[client_id](../../README.md#client_id)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **achievementIdDelete**
> \Swagger\Client\Model\SuccessResponse achievementIdDelete($id, $author_id)



Delete an achievement.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure API key authorization: client_id
Swagger\Client\Configuration::getDefaultConfiguration()->setApiKey('Client-ID', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// Swagger\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Client-ID', 'Bearer');

$api_instance = new Swagger\Client\Api\DefaultApi();
$id = 56; // int | Achievement id
$author_id = 56; // int | User id who caused the delete request

try {
    $result = $api_instance->achievementIdDelete($id, $author_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementIdDelete: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **id** | **int**| Achievement id |
 **author_id** | **int**| User id who caused the delete request |

### Return type

[**\Swagger\Client\Model\SuccessResponse**](../Model/SuccessResponse.md)

### Authorization

[client_id](../../README.md#client_id)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **achievementIdGet**
> \Swagger\Client\Model\Achievement achievementIdGet($id)



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

[**\Swagger\Client\Model\Achievement**](../Model/Achievement.md)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **achievementIdPut**
> \Swagger\Client\Model\SuccessResponse achievementIdPut($id, $body)



Update an achievement.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure API key authorization: client_id
Swagger\Client\Configuration::getDefaultConfiguration()->setApiKey('Client-ID', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// Swagger\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Client-ID', 'Bearer');

$api_instance = new Swagger\Client\Api\DefaultApi();
$id = 56; // int | Achievement id
$body = new \Swagger\Client\Model\Achievement(); // \Swagger\Client\Model\Achievement | achievement

try {
    $result = $api_instance->achievementIdPut($id, $body);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementIdPut: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **id** | **int**| Achievement id |
 **body** | [**\Swagger\Client\Model\Achievement**](../Model/\Swagger\Client\Model\Achievement.md)| achievement |

### Return type

[**\Swagger\Client\Model\SuccessResponse**](../Model/SuccessResponse.md)

### Authorization

[client_id](../../README.md#client_id)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **achievementPut**
> \Swagger\Client\Model\SuccessResponse achievementPut($body)



Create an achievement.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure API key authorization: client_id
Swagger\Client\Configuration::getDefaultConfiguration()->setApiKey('Client-ID', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// Swagger\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Client-ID', 'Bearer');

$api_instance = new Swagger\Client\Api\DefaultApi();
$body = new \Swagger\Client\Model\Achievement(); // \Swagger\Client\Model\Achievement | 

try {
    $result = $api_instance->achievementPut($body);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->achievementPut: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **body** | [**\Swagger\Client\Model\Achievement**](../Model/\Swagger\Client\Model\Achievement.md)|  |

### Return type

[**\Swagger\Client\Model\SuccessResponse**](../Model/SuccessResponse.md)

### Authorization

[client_id](../../README.md#client_id)

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
$site_id = 0; // int | The site id to use for locally overridden achievements.
$limit = 25; // int | Maximum number of items in the result.
$offset = 0; // int | Number of items to skip in the result.  Defaults to 0.

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
 **site_id** | **int**| The site id to use for locally overridden achievements. | [optional] [default to 0]
 **limit** | **int**| Maximum number of items in the result. | [optional] [default to 25]
 **offset** | **int**| Number of items to skip in the result.  Defaults to 0. | [optional] [default to 0]

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

// Configure API key authorization: client_id
Swagger\Client\Configuration::getDefaultConfiguration()->setApiKey('Client-ID', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// Swagger\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('Client-ID', 'Bearer');

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

[client_id](../../README.md#client_id)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **statsGet**
> \Swagger\Client\Model\InlineResponse2003 statsGet($user_id, $site_id, $global, $stat, $limit, $offset)



Get stats

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$api_instance = new Swagger\Client\Api\DefaultApi();
$user_id = 56; // int | Filter stats by user id
$site_id = 56; // int | Filter stats by site id
$global = false; // bool | If true, stats will be aggregated across all sites, and site_id will be ignored.  Note that this is a potentially expensive operation if the result set is large (i.e. when not filtered by user id).  The results will not include per-site progress or streak progress.
$stat = "stat_example"; // string | Filter by stat name
$limit = 25; // int | Maximum number of items in the result.
$offset = 0; // int | Number of items to skip in the result.  Defaults to 0.

try {
    $result = $api_instance->statsGet($user_id, $site_id, $global, $stat, $limit, $offset);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->statsGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **user_id** | **int**| Filter stats by user id | [optional]
 **site_id** | **int**| Filter stats by site id | [optional]
 **global** | **bool**| If true, stats will be aggregated across all sites, and site_id will be ignored.  Note that this is a potentially expensive operation if the result set is large (i.e. when not filtered by user id).  The results will not include per-site progress or streak progress. | [optional] [default to false]
 **stat** | **string**| Filter by stat name | [optional]
 **limit** | **int**| Maximum number of items in the result. | [optional] [default to 25]
 **offset** | **int**| Number of items to skip in the result.  Defaults to 0. | [optional] [default to 0]

### Return type

[**\Swagger\Client\Model\InlineResponse2003**](../Model/InlineResponse2003.md)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

