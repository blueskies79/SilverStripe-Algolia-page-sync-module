<?php

namespace AlgoliaSyncModuleDirectLease;

use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;
use SilverStripe\ORM\DB;
use Algolia\AlgoliaSearch\SearchClient;

/**
 * Class AlgoliaIndexTask Read more https://directlease.atlassian.net/wiki/spaces/DZS/pages/1778253825/AlgoliaIndexTask
 * @package AlgoliaSyncModuleDirectLease
 */
class AlgoliaIndexTask extends BuildTask
{

    protected $title = 'DirectLease AlgoliaIndexTask';
    protected $description = "This task will synchronize all Pages with ShowSearch on true with algolia. To perform a clean run use param fullsync=1";

    protected $enabled = true;

    public function run($request)
    {
        // create algolia client based on the config variables
        $client = SearchClient::create(
            Config::inst()->get('AlgoliaKeys', 'applicationId'),
            Config::inst()->get('AlgoliaKeys', 'adminApiKey')
        );
        $index = $client->initIndex(
            Config::inst()->get('AlgoliaKeys', 'indexName')
        );
        // do either a fullsync add/remove all algolia data or sync the changes from the last task
        if($request->getVar('fullsync')) {
            $this->fullSync($index);
        }
        else {
            $this->syncChanges($index);
        }
    }

    /**
     * remove state here and remove all object in algolia index and add them again for a fresh state
     *
     * @param $index algolia index
     */
    private function fullSync($index) {
        try {
            $index->clearObjects(); // remove all excisting objects in algolia
            $deletedCount = $this->deleteAllPageAlgoliaObjectIDHolder(); // remove all existing objects
            $this->deleteAllDeletedPageAlgoliaObjectIDHolder(); // remove all existing objects
            $pages = Versioned::get_by_stage('Page', 'Live')->filter('ShowInSearch', true);
            $syncCount = $this->syncPagesWithIndex($index, $pages);
            $this->createLogDataObject(true, $syncCount, 0, $deletedCount); // create a log object containing information about the sync
            $this->logInfo("Succesfull did a full sync with page count:". $syncCount);
        } catch (Exception $e) {
            $this->logError("Error during full Algolia SYNC with message: ".$e->getMessage());
        }
    }

    /**
     * Add pages to the algolia index
     *
     * For every config variable in the yaml add the fields to the algolia object this either are datafields or url of imaages wich can be localised as well as nonlocalised values
     *
     * @param $index algolia index
     * @param $pages pages that needs to be added to the index
     * @param $update boolean If the sync is an update, if false it creates a PageAlgoliaObjectIDHolder
     * @return int the count of pages being synced
     * @throws \SilverStripe\ORM\ValidationException
     */
    private function syncPagesWithIndex($index, $pages, $update = false) {
        $dataForAlgolia = [];
        foreach ($pages as $page) {
            $algoliaObject = [];
            $algoliaObject['objectID'] = $page->ID; //PageID used as ID
            $algoliaObject['ClassName'] = $page->ClassName;
            // add fieldvalue for key in config yml array in algolia.yml
            $algoliaObject = $this->addFieldDataToObjectIfsetOnPage($page, Config::inst()->get('AlgoliaSyncFieldsNonlocalised'), $algoliaObject);
            // add image Link if in config yml array in algolia.yml
            $algoliaObject = $this->addImageLinkToObjectIfSetOnPage($page, Config::inst()->get('AlgoliaSyncImagesNonlocalised'), $algoliaObject);
           // If Fluent is installed add localised data
           if (method_exists($page->getLocaleInstances())) {
                $algoliaObject = $this->addDataForEveryLocale($page, $algoliaObject);
           }
           $dataForAlgolia[] = $algoliaObject;
    
        }
        $index->saveObjects($dataForAlgolia, ['autoGenerateObjectIDIfNotExist' => true]);

        if (!$update) {
            $this->syncCreatedPagesWithPageAlgoliaObjectIDHolders($pages);
        }
        return sizeof($dataForAlgolia);
    }
    private function addDataForEveryLocale($page, $algoliaObject) {
        $locales = $page->getLocaleInstances();
        foreach ($locales as $locale) {
            $algoliaObject = TractorCow\Fluent\State\FluentState::singleton()
                ->withState(function (FluentState $state) use ($locale, $page, $algoliaObject) {
                    $state->setLocale($locale->Locale);
                    // we need to get the page again since our fluent context is changed and we want to get the localised data
                    $page = Versioned::get_by_stage('Page', 'Live')->byID($page->ID);
                    $algoliaObject['Locales'][$locale->Locale]['Title'] = $page->Title;
                    // in current context we get the stage url we do not wan't to fill in algolia
                    if ($page->ClassName == RedirectorPage::class) {
                        $link = $page->Link();
                        $link = str_replace("/?stage=Stage", "", $link);
                        $algoliaObject['Locales'][$locale->Locale]['Url'] = $link;
                    } // normal pages will return a normal urL
                    else {
                        $algoliaObject['Locales'][$locale->Locale]['Url'] = $page->Link();
                    }
                    $algoliaObject['Locales'][$locale->Locale]['MenuTitle'] = $page->MenuTitle;
                    // add fieldvalue for key in config yml array in algolia.yml
                    $algoliaObject['Locales'][$locale->Locale] = $this->addFieldDataToObjectIfsetOnPage($page, Config::inst()->get('AlgoliaSyncFieldslocalised'), $algoliaObject['Locales'][$locale->Locale]);
                    // add image Link if in config yml array in algolia.yml
                    $algoliaObject['Locales'][$locale->Locale] = $this->addImageLinkToObjectIfSetOnPage($page, Config::inst()->get('AlgoliaSyncImageslocalised'), $algoliaObject['Locales'][$locale->Locale]);
                
                    return $algoliaObject;
                });
        }
        return $algoliaObject;
    }

    /**
     * For every field in the config check if the page has that field and if it contains data add it to the object
     *
     * @param $page
     * @param $config yaml fieldNames if they exist on the page they will be added to the object that wel be synced to algolia
     * @param $object object of wich the data needs to be added tho
     * @return mixed
     */
    private function addFieldDataToObjectIfsetOnPage($page, $config, $object) {
        if ($config) {
            foreach ($config as $value) {
                if (isset($page->{$value}) && $pageValue = $page->{$value}) {
                    $object[$value] = $pageValue;
                }
            }
            return $object;
        }
        return $object;
    }

    /**
     * For every Field in the config check if the page has that Image and if it is set add the Link() to the object
     *
     * @param $page
     * @param $config yaml has_one image object relation names if they exist on the page the Link() will be added to the object that wel be synced to algolia
     * @param $object object of wich the data needs to be added tho
     * @return mixed
     */
    private function addImageLinkToObjectIfSetOnPage($page, $config, $object) {
        if ($config) {
            foreach ($config as $value) {
                if (isset($page->{$value."ID"}) && $page->{$value}()) {
                    if($link = $page->{$value}()->Link()) {
                        $object[$value] = $link;
                    }
                }
            }
            return $object;
        }
        return $object;
    }

    /**
     * Create PageAlgoliaObjectIDHolder for every added page in algolia
     *
     * we technical could just use the PageID for setting
     *
     * @param $pages
     * @param $savedObjectsResponse
     * @throws \SilverStripe\ORM\ValidationException
     */
    private function syncCreatedPagesWithPageAlgoliaObjectIDHolders($pages) {
        foreach($pages as $key => $page) {
            $pageAlgoliaObjectHolder = PageAlgoliaObjectIDHolder::create();
            $pageAlgoliaObjectHolder->AlgoliaObjectID = $page->ID;
            $pageAlgoliaObjectHolder->write();
        }
    }

    /**
     * synChanages remove old pages, update pages and add new pages to algolia
     *
     * @param $index
     * @throws \SilverStripe\ORM\ValidationException
     */
    private function syncChanges($index) {
        try {
            // safety check
            if (AlgoliaSyncLog::get()->count() == 0) {
                $this->logInfo("a normal sync has been requested but their is no sync history so it is not possible to sync the changes only");
                return $this->fullSync($index);
            }
            // remove all deleted pages
            $deletedCount = $this->deleteAlgoliaObjectsForIDs($index);
            // update pages with changed these last 24 hours
            $updatedCount = $this->getChangedPagesAndUpdateAlgolia($index);
            // add new pages
            $addedCount = $this->addNewCreatedPagesToAlgolia($index);

            $this->createLogDataObject(false, $addedCount, $updatedCount, $deletedCount); // create a log object containg information about teh sync

        } catch (Exception $e) {
            $this->logError("Error during Algolia SYNC with message: ".$e->getMessage());
        }
    }

    /**
     * All pages that have been removed will
     *
     * @param $index
     * @return int deleted count
     */
    private function deleteAlgoliaObjectsForIDs($index) {
        $deletedPageAlgoliaObjectIDHolders = DeletedPageAlgoliaObjectIDHolder::get();
        $arrayDeletedAlgoliaObjectIDs = $deletedPageAlgoliaObjectIDHolders ? $deletedPageAlgoliaObjectIDHolders->map('ID','AlgoliaObjectID')->values(): [];

        // if showInSearch has been changed we need to delete that pages as well so add it to the list
        $syncedPages = PageAlgoliaObjectIDHolder::get()->map("ID", 'AlgoliaObjectID')->values();
        $pagesWithShowInSearchSetToFalse = Versioned::get_by_stage('Page', 'Live')->filter(['ShowInSearch' => false, 'ID' => $syncedPages]);
        foreach ($pagesWithShowInSearchSetToFalse as $page) {
            array_push($arrayDeletedAlgoliaObjectIDs, $page->ID);
        }
        $deletedCount = count($arrayDeletedAlgoliaObjectIDs);
        if ($arrayDeletedAlgoliaObjectIDs) {
            $index->deleteObjects($arrayDeletedAlgoliaObjectIDs);
            // delete DB objects since algolia has been synced with removed objects.
            foreach ($deletedPageAlgoliaObjectIDHolders as $holder) {
                $holder->delete();
            }
            // delete page with ShowInSearch set to false
            foreach ($pagesWithShowInSearchSetToFalse as $page){
                $holder = PageAlgoliaObjectIDHolder::get()->filter('AlgoliaObjectID',$page->ID)->first();
                if($holder) {
                    $holder->delete();
                }
            }
        }


        $this->logInfo("Succesfully removed pages from algolia amount of deleted page objects: ".$deletedCount);
        return $deletedCount;
    }

    /**
     *
     * @param $index algolia index
     * @return int count of added Pages
     * @throws \SilverStripe\ORM\ValidationException
     */
    private function addNewCreatedPagesToAlgolia($index) {
        $syncedPages = PageAlgoliaObjectIDHolder::get()->map("ID", 'AlgoliaObjectID')->values();
        $pages = Versioned::get_by_stage('Page', 'Live')->filter(['ShowInSearch' => true, 'ID:not' => $syncedPages]);
        $syncCount = $this->syncPagesWithIndex($index, $pages);
        $this->logInfo('Succesfully synced new created pages: '. $syncCount);
        return $pages->count();
    }


    /**
     * All the pages that have been synced to algolia with changed the past 24hours will be updated
     *
     * @param $index
     * @param int updated page count
     * @throws \SilverStripe\ORM\ValidationException
     */
    private function getChangedPagesAndUpdateAlgolia($index) {
        $syncedPages = PageAlgoliaObjectIDHolder::get()->map("ID", 'AlgoliaObjectID')->values();
        if($syncedPages) {
            $date = AlgoliaSyncLog::get()->sort("SyncDate", "DESC")->first()->SyncDate;
            $pages = Versioned::get_by_stage('Page', 'Live')->filter(['ShowInSearch' => true, 'ID' => $syncedPages, 'LastEdited:GreaterThan' => $date]);
            if($count = $pages->count() > 0) {
                $this->syncPagesWithIndex($index, $pages, true);
            }
            $this->logInfo('Succesfully Updated pages:'. $count);
            return $count;
        }
        $this->logInfo('Succesfully Updated pages: 0, this is because there have not been pages synced and their is something going wrong');
        return 0;

    }

    /**
     * Empty table PageAlgoliaObjectIDHolder
     *
     * @return int count of deleted Items
     */
    private function deleteAllPageAlgoliaObjectIDHolder(){
        $deleteCount = PageAlgoliaObjectIDHolder::get()->count();
        DB::query("DELETE FROM PageAlgoliaObjectIDHolder");
        $this->logInfo('Succesfully deleted all PageAlgoliaObjectIDHolder: '.$deleteCount);
        return $deleteCount;
    }

    /**
     * Empty table DeletedAlgoliaObjectIDHolder
     */
    private function deleteAllDeletedPageAlgoliaObjectIDHolder(){
        DB::query("DELETE FROM DeletedAlgoliaObjectIDHolder");
        $this->logInfo('Succesfully deleted all DeletedPageAlgoliaObjectIDHolder');
    }

    /**
     * create a log object containing information about the task
     *
     * @param $fullSync boolean if it is a fullsync or a normal sync
     * @param $addedCount int
     * @param $updatedCount int
     * @param $deletedCount int
     * @throws \SilverStripe\ORM\ValidationException
     */
    private function createLogDataObject($fullSync, $addedCount, $updatedCount, $deletedCount) {
        $log = AlgoliaSyncLog::create();
        $log->FullSync = $fullSync ? true : false;
        $log->SyncDate =  date('Y-m-d H:i:s');
        $log->AddedCount = $addedCount;
        $log->UpdatedCount = $updatedCount;
        $log->DeletedCount = $deletedCount;
        $log->write();
    }

    /**
     * append silverstripe.log with error log message
     *
     * @param $message string
     */
    private function logError($message) {
        Injector::inst()->get(LoggerInterface::class)->error($message);
    }

    /**
     * append silverstripe.log with info log message
     *
     * @param $message string
     */
    private function logInfo($message) {
        Injector::inst()->get(LoggerInterface::class)->info($message);
    }

}