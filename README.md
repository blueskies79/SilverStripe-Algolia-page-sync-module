# SilverStripe DirectLease Algolia page sync module

This module packs a solution to sync Pages to algolia via SilverStripes BuildTask.
It keeps track of the Sitetree to sync only the changes.

## Working
Via a Task and some ORM Objects the state of the Sitetree and algolia can be managed.
The PageAlgoliaExtension is hooked on the Page Class to keep track of the deleted pages.
There are 3 ORM objects being created to keep track of the state:
* The removed pages in the DeletedPageAlgoliaObjectIDHolder
* The synced pages in the PageAlgoliaObjectIDHolder
* The last date of the sync in AlgoliaSyncLog, this also contains some usefull information of the synctask

We now can sync for the three conditions.
Added/removed and updated pages. 
The AlgoliaIndexTask does this. 
If you wan't to update your algolia index with searchResults every 12 hours you can schedule this task for every 12 hours. 
The tasks provides a fullsync param to do a fully sync 

Via Config it is possible to add fields/images urls that will be synced to an Algolia index

It is possible to add Localised data if you use Fluent. Via config it is possible to add fields/image urls that will be added in the Algolia object in an local object. This makes it possible to use the algolia search with searchattributes and you can add for example as search attributes: Locales.nl_NL and in this object will be the data and Algolia is smart enough to included all the variables in this objct.

It will log to SilverStripe.log and a a record will be created after the task has runned succesfully in the database.

## Requirements

* SilverStripe ^4.0

Fluent support


## Installation

```
composer require silverstripe-module/skeleton 4.x-dev
```


## License
See [License](license)

## How to run
If a sync hasn't been run yet a full sync will happen. You can schedule this task for any given time period to keep your algolia data up to date.
#### Via browser
```markdown
https://mysite.nl/AlgoliaSyncModuleDirectLease-AlgoliaIndexTask # A sync that only pushed the changes
```
full sync
```markdown
https://mysite.nl/AlgoliaSyncModuleDirectLease-AlgoliaIndexTask?fullsync=1 # For a full sync
```
### Via Terminal
```shell
php vendor/silverstripe/framework/cli-script.php dev/build 
```
full sync
```shell
php vendor/silverstripe/framework/cli-script.php dev/build fullsync=1
```

## Configuration
Via config an extension will be set on the Page Class. This will add an onBeforeDelete action to keep track of the deleted pages.
Via config it possible to add Fields that will be synced to Algolia. If the field or image excist on the page it will be added no matter the page type

The default values that will be added are:
* Title
* MenuTitle
* PageClass
* Url

It is possible to add your own DBFields/functions for localised or non localised fields. It is also possible to add localised or non localised Images if a DataObject has an $has_one relaion to that image. If you wan't to return your own logic for example via a method called getMyAwesomeCustomLogic it is also possible to add this to the fields config

##### AlgoliaSyncFieldslocalised
An array containing fields of DataBasefields wich values are being synced to Algolia. It is also possible to add your own custom logic functions via getCustomLogic() on the Page class. Since the ORM will forward this function to CustomLogic it is possible to add this to the config
- "CustomLogic" 

This will result in the algolia object containing this as key->value if the given page has this method and a value for it.

##### AlgoliaSyncFieldsNonlocalised
An array containing fields of DataBasefields wich values are being synced to Algolia. The difference between this and AlgoliaSyncFieldslocalised is that if Fluent is installed it will put the data in a localised object. The object in Algolia will be Locales->Locale->Key = Value. Instead of Key = value It is also possible to add your own custom logic functions via getCustomLogic() on the Page class. Since the ORM will forward this function to CustomLogic it is possible to add this to the config
- "CustomLogic" 

This will result in the algolia object containing this as Locales->Locale->key = value if the given page has this method and a value for it.

##### AlgoliaSyncImageslocalised
An array containing the names of $has_one image relations. If the page has an Image relation and the image is published it the Link() return will be saved in Algolia
- 'MyImage'

This wil results in MyImage = url.

##### AlgoliaSyncImagesNonlocalised
An array containing the names of $has_one image relations. If the page has an Image relation and the image is published it the Link() return will be saved in Algolia. The difference between this and AlgoliaSyncImageslocalised is that if Fluent is installed it will put the data in a localised object.

- 'MyImage'

This wil results in Locales->Locale->MyImage = url.

```yaml
Page:
  Extensions:
    - AlgoliaSyncModuleDirectLease\PageAlgoliaExtension
AlgoliaKeys:
  adminApiKey: '' # Algolia Admin API KEY
  applicationId: '' # Algolia App ID
  indexName: 'sitecontent' # default name of the index the pages will be synced in 
AlgoliaSyncFieldslocalised:
AlgoliaSyncFieldsNonlocalised:
AlgoliaSyncImageslocalised:
AlgoliaSyncImagesNonlocalised:
```


## Example configurations
A Config with Fluent support 
```yaml
---
name: 'my overide'
After:
  - '#algoliapagesyncmoduleconfig'
---
Page:
 Extensions:
  - AlgoliaSyncModuleDirectLease\PageAlgoliaExtension
AlgoliaKeys:
  adminApiKey: '' # Algolia Admin API KEY
  applicationId: '' # Algolia App ID
  indexName: 'sitecontent' # default name of the index the pages will be synced in 
AlgoliaSyncFieldslocalised:
 - "MyAwesomeSearchTextHolder"
 - "MyAwesomeSearchTextHolder2"
AlgoliaSyncFieldsNonlocalised:
 - "MyAwesomeSearchTextHolderNonLocalised"
AlgoliaSyncImageslocalised:
 - "MyAwesomeImage"
AlgoliaSyncImagesNonlocalised:
 - "MyAwesomeImageButNonLocalised"
```
A config without fluent support

```yaml
---
name: 'my overide'
After:
  - '#algoliapagesyncmoduleconfig'
---
Page:
 Extensions:
  - AlgoliaSyncModuleDirectLease\PageAlgoliaExtension
AlgoliaKeys:
  adminApiKey: '' # Algolia Admin API KEY
  applicationId: '' # Algolia App ID
  indexName: 'sitecontent' # default name of the index the pages will be synced in 
AlgoliaSyncFieldslocalised:
AlgoliaSyncFieldsNonlocalised:
 - "MyAwesomeSearchTextHolder"
AlgoliaSyncImageslocalised:
AlgoliaSyncImagesNonlocalised:
 - "MyAwesomeImage"
 - "MyAwesomeImage2"

```
