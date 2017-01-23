# Achievement

## Properties
Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional] 
**parent_id** | **int** | If this achievement was inherited from another achievement, this is the parent achievement id; otherwise 0.  Inheritance is used for overriding achievements on individual sites. | [optional] 
**site_id** | **int** | If this achievement is specific to a site, this is the site id; otherwise 0. | [optional] 
**name** | [**\Swagger\Client\Model\LocalizedString**](LocalizedString.md) |  | [optional] 
**description** | [**\Swagger\Client\Model\LocalizedString**](LocalizedString.md) |  | [optional] 
**image** | **string** | This is the name of an image in the File: namespace of the commons or the local wiki; e.g. \&quot;achievement-star.png\&quot; | [optional] 
**category** | [**\Swagger\Client\Model\AchievementCategory**](AchievementCategory.md) |  | [optional] 
**points** | **int** |  | [optional] 
**global** | **bool** | When true, this achievement is awarded based on progress across all sites. | [optional] 
**criteria** | [**\Swagger\Client\Model\AchievementCriteria**](AchievementCriteria.md) |  | [optional] 

[[Back to Model list]](../README.md#documentation-for-models) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to README]](../README.md)


