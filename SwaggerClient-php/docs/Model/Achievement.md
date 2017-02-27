# Achievement

## Properties
Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional] 
**parent_id** | **int** | If this achievement was inherited from another achievement, this is the parent achievement id; otherwise 0.  Inheritance is used for overriding achievements on individual sites. | [optional] 
**site_id** | **int** | Internal ID assigned from site_key; this field is ignored in data from the client. | [optional] 
**site_key** | **string** | If this achievement is specific to a site, this is the site key; otherwise empty. | [optional] 
**name** | [**\Swagger\Client\Model\LocalizedString**](LocalizedString.md) |  | [optional] 
**description** | [**\Swagger\Client\Model\LocalizedString**](LocalizedString.md) |  | [optional] 
**image** | **string** | This is the name of an image in the File: namespace of the commons or the local wiki; e.g. \&quot;achievement-star.png\&quot; | [optional] 
**category** | [**\Swagger\Client\Model\AchievementCategory**](AchievementCategory.md) |  | [optional] 
**points** | **int** |  | [optional] 
**global** | **bool** | When true, this achievement is awarded based on progress across all sites. | [optional] 
**protected** | **bool** | When true, this achievement is protected from modifications. | [optional] 
**secret** | **bool** | When true, this achievement is not shown for the user until the user has earned it. | [optional] 
**created_at** | **int** | Unix time in seconds when this achievement was created. | [optional] 
**updated_at** | **int** | Unix time in seconds when this achievement was last updated. | [optional] 
**created_by** | **int** | User id of the original author of this achievement. | [optional] 
**updated_by** | **int** | User id of the most recent author of this achievement. | [optional] 
**criteria** | [**\Swagger\Client\Model\AchievementCriteria**](AchievementCriteria.md) |  | [optional] 

[[Back to Model list]](../README.md#documentation-for-models) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to README]](../README.md)


