<?php

namespace Wabel\Zoho\CRM\Helpers;

use Wabel\Zoho\CRM\Exceptions\ExceptionZohoClient;
use Wabel\Zoho\CRM\ZohoClient;

class ZCRMModuleHelper
{

    /**
     * @param \Wabel\Zoho\CRM\ZohoClient $zohoClient
     * @param $module
     * @param string|null $cvId
     * @param string|null $sortColumnString
     * @param string|null $sortOrderString
     * @param int $page
     * @param int $limitRows
     * @param \DateTime|null $fromModifiedDate
     * @return \ZCRMRecord[]
     */
    public static function getAllZCRMRecordsFromPagination(ZohoClient $zohoClient, $module, $cvId = null, $sortColumnString = null, $sortOrderString = null, $page = 1, $limitRows = 200, \DateTime$fromModifiedDate = null){

        /**
         * @var $records \ZCRMRecord[]
         */
        $records = [];
        $header = null;
        if($fromModifiedDate){
            $header = [
              'If-Modified-Since' =>  $fromModifiedDate->format(\DateTime::ATOM)
            ];
        }
        $module = $zohoClient->getModule($module);
        do{
            try{
                /**
                 * @var $recordsRequest \BulkAPIResponse
                 */
                $recordsRequest = $module->getRecords($cvId, $sortColumnString, $sortOrderString, $page, $limitRows, $header);

            } catch (\ZCRMException $exception){
                if(strtolower($exception->getExceptionCode()) === strtolower(ExceptionZohoClient::EXCEPTION_CODE_NO__CONTENT)){
                    $recordsRequest =  null;
                } else{
                    \APIExceptionHandler::logException($exception);
                    throw $exception;
                }
            }

            /**
             * @var $infoResponse \ResponseInfo
             */
            $infoResponse= $recordsRequest ? $recordsRequest->getInfo() : null;
            if($infoResponse && $infoResponse->getRecordCount()){
                $records = array_merge($records, $recordsRequest->getData());
            }
            $page++;
        } while($infoResponse && $infoResponse->getMoreRecords());
        return $records;
    }


    /**
     * @param ZohoClient $zohoClient
     * @param $module
     * @param string $typeRecord
     * @param \DateTimeInterface|null $lastModifiedTime
     * @param int $page
     * @param int $perPage
     * @return \ZCRMTrashRecord[]
     * @throws \ZCRMException
     */
    public static function getAllZCRMTrashRecordsFromPagination(ZohoClient $zohoClient, $module,$typeRecord = 'all', \DateTimeInterface $lastModifiedTime = null, $page = 1, $perPage= 200){

        /**
         * @var $records \ZCRMTrashRecord[]
         */
        $records = [];

        do{
            /**
             * @var $recordsRequest \BulkAPIResponse
             */
            $recordsRequest = $zohoClient->getDeletedRecords($module, $typeRecord, $lastModifiedTime, $page, $perPage);


            /**
             * @var $infoResponse \ResponseInfo
             */
            $infoResponse= $recordsRequest?$recordsRequest->getInfo() : null;
            if($infoResponse && $infoResponse->getRecordCount()){
                $records = array_merge($records, $recordsRequest->getData());
            }
            $page++;
        } while($infoResponse && $infoResponse->getMoreRecords());

        return $records;
    }
}