<?php


namespace AlgoliaSyncModuleDirectLease;


use SilverStripe\ORM\DataObject;

/**
 * Class PageAlgoliaObjectIDHolder Used for knowing wich pages have been synced to Algolia
 * @package AlgoliaSyncModuleDirectLease
 */
class PageAlgoliaObjectIDHolder extends DataObject
{
    private static $table_name = 'PageAlgoliaObjectIDHolder';
    private static $singular_name = 'PageAlgoliaObjectIDHolder';
    private static $plural_name = 'PageAlgoliaObjectIDHolder';
    
    private static $db = [
        'AlgoliaObjectID' => 'Int'
    ];
    
    
}