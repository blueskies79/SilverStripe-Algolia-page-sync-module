<?php


namespace AlgoliaSyncModuleDirectLease;


use SilverStripe\ORM\DataObject;

/**
 * Class DeletedPageAlgoliaObjectIDHolder Used for knowing wich pages have been deleted the PageAlgoliaExtension have to be added on the page onBeforeDelete hook
 * @package AlgoliaSyncModuleDirectLease
 */
class DeletedPageAlgoliaObjectIDHolder extends DataObject
{
    private static $table_name = 'DeletedAlgoliaObjectIDHolder';
    private static $singular_name = 'DeletedAlgoliaObjectIDHolder';
    private static $plural_name = 'DeletedAlgoliaObjectIDHolder';
    
    private static $db = [
        'AlgoliaObjectID' => 'Int'
    ];
    
    
}