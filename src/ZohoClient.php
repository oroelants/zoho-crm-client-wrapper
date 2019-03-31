<?php

namespace Wabel\Zoho\CRM;
use Psr\Log\LoggerInterface;
use Wabel\Zoho\CRM\Exceptions\ExceptionZohoClient;

/**
 * Client for provide interface with Zoho CRM.
 */
class ZohoClient
{

    /**
     * @var array|null
     */
    private $configurations;

    /**
     * @var string
     */
    protected $timezone;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ZohoClient constructor.
     *
     * @param array $configurations
     *             ['client_id' => '',
     *              'client_secret' => '',
     *             'redirect_uri' => '',
     *             'currentUserEmail' => '',
     *             'applicationLogFilePath' => '',
     *             'sandbox' => true or false,
     *             'apiBaseUrl' => '',
     *             'apiVersion' => '',
     *             'access_type' => '',
     *             'accounts_url' => '',
     *             'persistence_handler_class' => '',
     *             'token_persistence_path' => '']
     * @param string $timezone
     * @param LoggerInterface $logger
     */
    public function __construct(array $configurations = null, string $timezone, LoggerInterface $logger)
    {
        $this->configurations = $configurations;
        $this->timezone = $timezone;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }


    /**
     * @return array|null
     */
    public function getConfigurations(): ?array
    {
        return $this->configurations;
    }


    public function initCLient() :void
    {
        \ZCRMRestClient::initialize($this->configurations);
    }

    /**
     * @return \ZohoOAuthClient
     * @throws \ZohoOAuthException
     */
    public function getZohoOAuthClient()
    {
        $this->initCLient();
        try{
            return \ZohoOAuth::getClientInstance();
        } catch (\ZohoOAuthException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot get the zoho Client Instance');
            throw $ex;
        }
    }

    /**
     * @return \ZCRMRestClient
     */
    public function getZCRMRestClient()
    {
        $this->initCLient();
        return \ZCRMRestClient::getInstance();
    }

    /**
     * @param  string $grantToken
     * @return \ZohoOAuthTokens
     * @throws \ZohoOAuthException
     */
    public function generateAccessToken(string $grantToken)
    {
        $client = $this->getZohoOAuthClient();
        try{
            return $client->generateAccessToken($grantToken);
        } catch (\ZohoOAuthException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot generate access token {grantToken}', ['grantToken' => $grantToken]);
            throw $ex;
        }
    }

    /**
     * @param string $refreshToken
     * @param string $userIdentifier
     * @return mixed
     * @throws \ZohoOAuthException
     */
    public function generateAccessTokenFromRefreshToken(string $refreshToken, string $userIdentifier)
    {
        $oAuthClient = $this->getZohoOAuthClient();
        try{
            return $oAuthClient->generateAccessTokenFromRefreshToken($refreshToken, $userIdentifier);
        } catch (\ZohoOAuthException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot refresh token {grantToken} with user identifier : {userIdentifier} ', [
                'refreshToken' => $refreshToken,
                'userIdentifier' => $userIdentifier
            ]);
            throw $ex;
        }
    }

    /**
     * Implements convertLead API method.
     *
     * @param  string $leadId
     * @param  string|null $dealId
     * @param  string|null $userId
     * @return \APIResponse
     * @throws \ZCRMException
     */
    public function convertLead($leadId, $dealId = null, $userId = null)
    {
        $record = $this->getRecordById("Leads", $leadId);
        $userInstance = null;
        $recordDeal = null;
        if($dealId) {
            $modIns = $this->getModule("Deals");
            $recordDeal = $modIns->getRecord($dealId)->getData();
        }
        if($userId) {
            $userInstance = $this->getUser($userId);
        }
        try{
            return $record->convert($recordDeal, $userInstance);
        } catch (\ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot covert the lead {leadId} for dealId {dealId} with the userId {userId}', [
                'leadId' => $leadId,
                'userId' => $userId? : '',
                'dealId' => $dealId? : ''
            ]);
            throw $ex;
        }
    }

    /**
     * Implements getFields API method.
     *
     * @return \ZCRMField[]|null
     * @throws \ZCRMException
     */
    public function getFields($module)
    {
        try{

            /**
             * @var $response \APIResponse
             */
            $response = $this->getModule($module)->getAllFields();
            return $response->getData();
        } catch(\ZCRMException $exception){
            if(ExceptionZohoClient::exceptionCodeFormat($exception->getExceptionCode()) === ExceptionZohoClient::EXCEPTION_CODE_NO__CONTENT) {
                return null;
            }
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot get all fields for the module {moduleName}', [
                'moduleName' => $module
            ]);
            throw $exception;
        }
    }

    /**
     * Implements deleteRecords API method.
     *
     * @param  string       $module
     * @param  string|array $ids    Id of the record
     * @return \EntityResponse[]
     */
    public function deleteRecords($module, $ids)
    {
        $zcrmModuleIns = $this->getModule($module);
        if(is_string($ids)) {
            $ids = [$ids];
        }
        try{
            /**
             * @var $bulkAPIResponse \BulkAPIResponse
             */
            $bulkAPIResponse = $zcrmModuleIns->deleteRecords($ids);
            return $bulkAPIResponse->getEntityResponses();
        } catch(\ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot delete the record(s) {id} for the module {moduleName}', [
                'moduleName' => $module,
                'id' => implode(',', $ids)
            ]);
            throw  $ex;
        }
    }

    /**
     * Implements getRecordById API method.
     *
     * @param  string $module The module to use
     * @param  string $id     Id of the record or a list of IDs separated by a semicolon
     * @return \ZCRMRecord
     */
    public function getRecordById($module, $id)
    {
        try{
            /**
             * @var $response \APIResponse
             */
            $response = $this->getModule($module)->getRecord($id);
            return $response->getData();
        }catch(\ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot get record {id} for the module {moduleName}. Maybe it does not exist or something wrong', [
                'moduleName' => $module,
                'id' => implode(',', $id)
            ]);
            throw $ex;
        }
    }
    /**
     * Implements getRecords API method.
     *
     * @param  string      $module
     * @param  string|null $cvId
     * @param  string|null $sortColumnString
     * @param  string|null $sortOrderString
     * @param  int         $fromIndex
     * @param  int         $toIndex
     * @param  null        $header
     * @return \ZCRMRecord[]
     */
    public function getRecords($module, $cvId = null, $sortColumnString = null, $sortOrderString = null, $fromIndex = 1, $toIndex = 200, $header = null)
    {

        $zcrmModuleIns = $this->getModule($module);
        try{
            /**
             * @var $bulkAPIResponse \BulkAPIResponse
             */
            $bulkAPIResponse = $zcrmModuleIns->getRecords($cvId, $sortColumnString, $sortOrderString, $fromIndex, $toIndex, $header);
            return $bulkAPIResponse->getData();
        } catch (\ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot get records for the module {moduleName}', [
                'moduleName' => $module,
            ]);
            throw $ex;
        }
    }

    /**
     * Implements getDeletedRecords by rewrite MassEntityAPIHandler::getDeletedRecords API method.
     *
     * @param  string                  $module
     * @param  string                  $typeOfRecord
     * @param  \DateTimeInterface|null $lastModifiedTime
     * @param  int                     $page
     * @param  int                     $perPage
     * @return \BulkAPIResponse|null
     * @throws \ZCRMException
     * @see    \ZCRMModule::getAllDeletedRecords()
     * @see    \ZCRMModule::getRecycleBinRecords()
     * @see    \ZCRMModule::getPermanentlyDeletedRecords()
     * @see    \MassEntityAPIHandler::getAllDeletedRecords()
     * @see    \MassEntityAPIHandler::getRecycleBinRecords()
     * @see    \MassEntityAPIHandler::getPermanentlyDeletedRecords()
     * @see    \MassEntityAPIHandler::getDeletedRecords()
     */
    public function getDeletedRecords($module, $typeOfRecord = "all", \DateTimeInterface $lastModifiedTime = null, $page = 1, $perPage= 200)
    {
        try
        {
            $zcrmModuleIns = $this->getModule($module);
            $massEntityAPIHandler = \MassEntityAPIHandler::getInstance($zcrmModuleIns);
            if($lastModifiedTime) {

                $massEntityAPIHandler->addHeader('If-Modified-Since', $lastModifiedTime->format(\DateTime::ATOM));
            }
            $massEntityAPIHandler->addParam('page', $page);
            $massEntityAPIHandler->addParam('per_page', $perPage);
            $massEntityAPIHandler->setUrlPath($zcrmModuleIns->getAPIName()."/deleted");
            $massEntityAPIHandler->setRequestMethod(\APIConstants::REQUEST_METHOD_GET);
            $massEntityAPIHandler->addHeader("Content-Type", "application/json");
            $massEntityAPIHandler->addParam("type", $typeOfRecord);
            /**
             * @var $responseInstance \BulkAPIResponse
             */
            $responseInstance=\APIRequest::getInstance($massEntityAPIHandler)->getBulkAPIResponse();

            $responseJSON=$responseInstance->getResponseJSON();
            $trashRecordList=array();
            if(isset($responseJSON['data'])) {
                $trashRecords=$responseJSON["data"];
                foreach ($trashRecords as $trashRecord)
                {
                    $trashRecordInstance = \ZCRMTrashRecord::getInstance($trashRecord['type'], $trashRecord['id']);
                    $massEntityAPIHandler->setTrashRecordProperties($trashRecordInstance, $trashRecord);
                    array_push($trashRecordList, $trashRecordInstance);
                }
            }

            $responseInstance->setData($trashRecordList);
            return $responseInstance;
        }
        catch (\ZCRMException $exception)
        {
            if(ExceptionZohoClient::exceptionCodeFormat($exception->getExceptionCode()) === ExceptionZohoClient::EXCEPTION_CODE_NO__CONTENT) {
                return null;
            }
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot get deleted records with type "{type}" for the module {moduleName}', [
                'moduleName' => $module,
                'type' => $typeOfRecord
            ]);
            throw $exception;
        }
    }

    /**
     * @param  string                  $module
     * @param  \DateTimeInterface|null $lastModifiedTime
     * @param  int                     $page
     * @param  int                     $perPage
     * @return \BulkAPIResponse
     * @throws \ZCRMException
     * @see    \ZCRMModule::getPermanentlyDeletedRecords()
     * @see    \MassEntityAPIHandler::getPermanentlyDeletedRecords()
     */
    public function getPermanentlyDeletedRecords($module, \DateTimeInterface $lastModifiedTime = null, $page = 1, $perPage= 200)
    {
        return $this->getDeletedRecords($module, 'permanent', $lastModifiedTime, $page, $perPage);
    }


    /**
     * @param  string                  $module
     * @param  \DateTimeInterface|null $lastModifiedTime
     * @param  int                     $page
     * @param  int                     $perPage
     * @return \BulkAPIResponse
     * @throws \ZCRMException
     * @see    \ZCRMModule::getAllDeletedRecords()
     * @see    \MassEntityAPIHandler::getAllDeletedRecords()
     */
    public function getAllDeletedRecords($module, \DateTimeInterface $lastModifiedTime = null, $page = 1, $perPage= 200)
    {
        return $this->getDeletedRecords($module, 'all', $lastModifiedTime, $page, $perPage);
    }


    /**
     * @param  string                  $module
     * @param  \DateTimeInterface|null $lastModifiedTime
     * @param  int                     $page
     * @param  int                     $perPage
     * @return \BulkAPIResponse
     * @throws \ZCRMException
     * @see    \ZCRMModule::getRecycleBinRecords()
     * @see    \MassEntityAPIHandler::getRecycleBinRecords()
     */
    public function getRecycleBinRecords($module, \DateTimeInterface $lastModifiedTime = null, $page = 1, $perPage= 200)
    {
        return $this->getDeletedRecords($module, 'recycle', $lastModifiedTime, $page, $perPage);
    }

    /**
     * Implements get Related List Records API method.
     *
     * @param  $module
     * @param  $id
     * @param  string      $relatedListAPIName
     * @param  string|null $sortByField
     * @param  string|null $sortByOrder
     * @param  int         $page
     * @param  int         $perPage
     * @return \BulkAPIResponse
     */
    public function getRelatedRecords($module, $id, $relatedListAPIName, $sortByField = null, $sortByOrder = null, $page = 1, $perPage = 200)
    {
        /**
         * @var $zcrmRecordIns \ZCRMRecord
         */
        $zcrmRecordIns  = $this->getRecordById($module, $id);
        try{
            $bulkAPIResponse = $zcrmRecordIns->getRelatedListRecords($relatedListAPIName, $sortByField, $sortByOrder, $page, $perPage);
        } catch(\ZCRMException $exception){
            if(ExceptionZohoClient::exceptionCodeFormat($exception->getExceptionCode()) === ExceptionZohoClient::EXCEPTION_CODE_NO__CONTENT) {
                return null;
            }
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot get related records from the record id {id} for the module {moduleName}', [
                'moduleName' => $module,
                'id' => $id
            ]);
            throw $exception;
        }

        return $bulkAPIResponse;
    }

    /**
     * Implements searchRecords API method.
     * For unit tests or search after creation of entities you have to wait indexing from zoho.
     *
     * @param  $module
     * @param  mixed  $searchCondition
     * @param  string $type            Type of search(among phone, email, criteria, word).By default  search by word
     * @param  int    $page
     * @param  int    $perPage
     * @return \ZCRMRecord[]
     */
    public function searchRecords($module, $searchCondition, string $type = 'word', $page = 1, $perPage = 200)
    {
        $zcrmModuleIns = $this->getModule($module);
        if($type === 'word') {
            $bulkAPIResponse = $zcrmModuleIns->searchRecords($searchCondition, $page, $perPage);
        } else{
            $typeSearchMethod = "searchRecordsBy".ucfirst($type);
            $bulkAPIResponse = $zcrmModuleIns->{"$typeSearchMethod"}($searchCondition, $page, $perPage);
        }
        return $bulkAPIResponse->getData();
    }

    /**
     * Implements getUser API method.
     *
     * @param  string|null $orgName
     * @param  string|null $orgId
     * @return \ZCRMUser
     */
    public function getUser($userId ,$orgName = null, $orgId = null)
    {
        $this->initCLient();
        /**
         * @var $APIResponse \APIResponse
         */
        $APIResponse = \ZCRMOrganization::getInstance($orgName, $orgId)->getUser($userId);
        return $APIResponse->getData();
    }


    /**
     * Implements getUsers API method.
     *
     * @param  string      $type    The type of users you want retrieve (among AllUsers, ActiveUsers, DesactiveUsers, AdminUsers and ActiveConfirmedAdmins)
     * @param  string|null $orgName
     * @param  string|null $orgId
     * @return \ZCRMUser[]
     */
    public function getUsers($type = 'AllUsers',$orgName = null, $orgId = null)
    {
        $typeMethod = "get" . $type;
        $this->initCLient();
        /**
         * @var $bulkAPIResponse \BulkAPIResponse
         */
        $bulkAPIResponse = \ZCRMOrganization::getInstance($orgName, $orgId)->{"$typeMethod"}();
        return $bulkAPIResponse->getData();
    }

    /**
     * Implements insert or update Records API method.
     *
     * @param  $module
     * @param  array|\ZCRMRecord[] $records
     * @return \EntityResponse[]
     */
    public function upsertRecords($module, array $records)
    {
        $zcrmModuleIns = $this->getModule($module);
        /**
         * @var $bulkAPIResponse \BulkAPIResponse
         */
        $bulkAPIResponse = $zcrmModuleIns->upsertRecords($records);
        return $bulkAPIResponse->getEntityResponses();
    }


    /**
     * Implements insertRecords API method.
     *
     * @param  $module
     * @param  \ZCRMRecord[] $records
     * @param  null|bool     $trigger
     * @return \EntityResponse[]
     */
    public function insertRecords($module, array $records,  ?bool $trigger = null)
    {
        $zcrmModuleIns = $this->getModule($module);
        /**
         * @var $bulkAPIResponse \BulkAPIResponse
         */
        $bulkAPIResponse = $zcrmModuleIns->createRecords($records, $trigger);
        return $bulkAPIResponse->getEntityResponses();
    }

    /**
     * Implements updateRecords API method.
     *
     * @param  string        $module
     * @param  \ZCRMRecord[] $records
     * @param  null|bool     $trigger
     * @return \EntityResponse[]
     */
    public function updateRecords(string $module, array $records,  ?bool $trigger = null)
    {
        $zcrmModuleIns = $this->getModule($module);
        /**
         * @var $bulkAPIResponse \BulkAPIResponse
         */
        $bulkAPIResponse = $zcrmModuleIns->updateRecords($records, $trigger);
        return $bulkAPIResponse->getEntityResponses();
    }

    /**
     * Implements updateRelatedRecords API method.
     *
     * @param  string $module
     * @param  string $recordId
     * @param  string $relatedModule
     * @param  string $relatedRecordId
     * @param  array  $fieldsValue
     * @return \APIResponse
     */
    public function updateRelatedRecords(string $module, string $recordId,  string $relatedModule, string $relatedRecordId,  array $fieldsValue = [])
    {
        $parentRecord= $this->getRecordById($module, $recordId);
        $junctionRecord= \ZCRMJunctionRecord::getInstance($relatedModule, $relatedRecordId);
        foreach ($fieldsValue as $fieldApiName => $value){
            $junctionRecord->setRelatedData($fieldApiName, $value);
        }
        return $parentRecord->addRelation($junctionRecord);
    }

    /**
     * Implements uploadFile API method.
     *
     * @param  string $module
     * @param  string $recordId
     * @param  string $filepath
     * @return \APIResponse
     */
    public function uploadFile(string $module, string $recordId, string $filepath)
    {
        $record = $this->getRecordById($module, $recordId);
        return $record->uploadAttachment($filepath);
    }



    /**
     * Implements downloadFile API method.
     *
     * @param  string $module
     * @param  string $recordId
     * @param  string $attachmentId
     * @return \FileAPIResponse
     */
    public function downloadFile(string $module, string $recordId, string $attachmentId)
    {
        $record = $this->getRecordById($module, $recordId);
        return $record->downloadAttachment($attachmentId);
    }


    /**
     * Returns a module from Zoho.
     *
     * @param  string $moduleName
     * @return \ZCRMModule
     */
    public function getModule(string $moduleName)
    {
        $this->initCLient();
        return \ZCRMModule::getInstance($moduleName);
    }


    /**
     * Returns a list of modules from Zoho.
     *
     * @return \ZCRMModule[]
     */
    public function getModules(): array
    {
        $this->initCLient();
        /**
         * @var $bulkAPIResponse \BulkAPIResponse
         */
        $bulkAPIResponse =  \ZCRMRestClient::getInstance()->getAllModules();
        return $bulkAPIResponse->getData();
    }

    public function logException(\ZCRMException $exception){
        \APIExceptionHandler::logException($exception);
    }

    /**
     * @param string $method
     * @param \Exception $exception
     * @param string $type
     * @param string $message
     * @param array $contextParams
     */
    private function  logClientException(string $method, \Exception $exception,$type = 'error', string $message, array $contextParams = []){

        $this->{$type}($message? $message.'. From '.self::class.':'.$method.'()' : $exception->getMessage(), array_merge([
            'exception' => $exception], $contextParams));
    }
}
