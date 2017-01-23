# AchievementCriteria

## Properties
Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**stats** | [**\Swagger\Client\Model\Stat[]**](Stat.md) | These stats count for progress towards the achievement. | [optional] 
**value** | **int** | The total value required of the stats for this achievement for the achievement to be awarded. | [optional] 
**streak** | **string** | If not \&quot;none\&quot;, progress towards this achievement can only be made once every streak period, and progress will be reset if progress is not made for an entire streak period. | [optional] 
**streak_progress_required** | **int** | If streak is not \&quot;none\&quot;, progress towards this achievement during each streak period may be made up to this value.  If progress is only partially made to this value, it will be reset in the next streak period.  For example, to implement an achievement described \&quot;Edit a wiki 5 times a day for 5 days\&quot;, this would be 5 and value would be 25. | [optional] 
**streak_reset_to_zero** | **bool** | If true, progress towards the streak will be reset to zero when the streak period expires without any progress.  If false, progress will only be reset when partial progress is made as described under streak_progress_required. | [optional] 
**per_site_progress_maximum** | **int** | If not 0, progress towards this achievement can only be made up to this value on each individual site.  For an achievement described \&quot;Contributed to 50 different wikis\&quot;, this would be 1 and &#x60;value&#x60; would be 50.  Note that this only makes sense for achievements which are marked global. | [optional] 
**category_id** | **int** | If not 0, this achievement is awarded when all other achievements in the category are complete. | [optional] 
**achievement_ids** | **int[]** | If not empty, this achievement is awared when all of the achievements specified by the ids in this list are complete. | [optional] 

[[Back to Model list]](../README.md#documentation-for-models) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to README]](../README.md)


