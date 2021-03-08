<?php


namespace AlgoliaSyncModuleDirectLease;


use SilverStripe\ORM\DataObject;

/**
 * Class AlgoliaSyncLog Used for logging of the sync and the sync uses the last date of this log to see if pages have been updated
 * @package AlgoliaSyncModuleDirectLease
 */
class AlgoliaSyncLog extends DataObject
{
    private static $table_name = 'AlgoliaSyncLog';
    private static $singular_name = 'AlgoliaSyncLog';
    private static $plural_name = 'AlgoliaSyncLog';
    
    private static $db = [
        'FullSync' => 'Boolean',
        'SyncDate' => 'Datetime',
        'AddedCount' => 'Int',
        'UpdatedCount' => 'Int',
        'DeletedCount' => 'Int'
    ];
}