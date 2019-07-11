<?php

namespace Wabel\Zoho\CRM;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wabel\Zoho\CRM\Exceptions\ExceptionZohoClient;
use zcrmsdk\crm\api\APIRequest;
use zcrmsdk\crm\api\handler\EntityAPIHandler;
use zcrmsdk\crm\api\handler\MassEntityAPIHandler;
use zcrmsdk\crm\api\response\APIResponse;
use zcrmsdk\crm\api\response\BulkAPIResponse;
use zcrmsdk\crm\api\response\EntityResponse;
use zcrmsdk\crm\api\response\FileAPIResponse;
use zcrmsdk\crm\crud\ZCRMField;
use zcrmsdk\crm\crud\ZCRMJunctionRecord;
use zcrmsdk\crm\crud\ZCRMModule;
use zcrmsdk\crm\crud\ZCRMRecord;
use zcrmsdk\crm\crud\ZCRMTrashRecord;
use zcrmsdk\crm\exception\APIExceptionHandler;
use zcrmsdk\crm\exception\ZCRMException;
use zcrmsdk\crm\setup\org\ZCRMOrganization;
use zcrmsdk\crm\setup\restclient\ZCRMRestClient;
use zcrmsdk\crm\setup\users\ZCRMUser;
use zcrmsdk\crm\utility\APIConstants;
use zcrmsdk\oauth\exception\ZohoOAuthException;
use zcrmsdk\oauth\ZohoOAuth;
use zcrmsdk\oauth\ZohoOAuthClient;

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
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $configurations = null, string $timezone, ?LoggerInterface $logger = null)
    {
        $this->configurations = $configurations;
        $this->timezone = $timezone;
        $this->logger = $logger? $logger : new NullLogger();
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger() {
        return $this->logger;
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


    /**
     * @throws ZohoOAuthException
     */
    public function initCLient() :void
    {
        try{
            ZCRMRestClient::initialize($this->configurations);
        } catch (ZohoOAuthException $exception){
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot initialize the  zoho Client Instance');
            throw $exception;
        }
    }

    /**
     * @return ZohoOAuthClient
     * @throws ZohoOAuthException
     */
    public function getZohoOAuthClient()
    {
        $this->initCLient();
        try{
            return ZohoOAuth::getClientInstance();
        } catch (ZohoOAuthException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot get the zoho Client Instance');
            throw $ex;
        }
    }

    /**
     * @return ZCRMRestClient
     */
    public function getZCRMRestClient()
    {
        $this->initCLient();
        return ZCRMRestClient::getInstance();
    }

    /**
     * @param  string $grantToken
     * @return ZohoOAuthTokens
     * @throws ZohoOAuthException
     */
    public function generateAccessToken(string $grantToken)
    {
        $client = $this->getZohoOAuthClient();
        try{
            return $client->generateAccessToken($grantToken);
        } catch (ZohoOAuthException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot generate access token {grantToken}', ['grantToken' => $grantToken]);
            throw $ex;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * @param string $refreshToken
     * @param string $userIdentifier
     * @return mixed
     * @throws ZohoOAuthException
     */
    public function generateAccessTokenFromRefreshToken(string $refreshToken, string $userIdentifier)
    {
        $oAuthClient = $this->getZohoOAuthClient();
        try{
            return $oAuthClient->generateAccessTokenFromRefreshToken($refreshToken, $userIdentifier);
        } catch (ZohoOAuthException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot refresh token {grantToken} with user identifier : {userIdentifier} ', [
                'refreshToken' => $refreshToken,
                'userIdentifier' => $userIdentifier
            ]);
            throw $ex;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements convertLead API method.
     *
     * @param  string $leadId
     * @param  string|null $dealId
     * @param  string|null $userId
     * @return APIResponse
     * @throws ZCRMException
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
        } catch (ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot covert the lead {leadId} for dealId {dealId} with the userId {userId}', [
                'leadId' => $leadId,
                'userId' => $userId? : 'null',
                'dealId' => $dealId? : 'null'
            ]);
            throw $ex;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements getFields API method.
     *
     * @return ZCRMField[]|null
     * @throws ZCRMException
     */
    public function getFields($module)
    {
        try{

            /**
             * @var $response APIResponse
             */
            $response = $this->getModule($module)->getAllFields();
            return $response->getData();
        } catch(ZCRMException $exception){
            if(ExceptionZohoClient::exceptionCodeFormat($exception->getExceptionCode()) === ExceptionZohoClient::EXCEPTION_CODE_NO__CONTENT) {
                return null;
            }
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot get all fields for the module {moduleName}', [
                'moduleName' => $module
            ]);
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements deleteRecords API method.
     *
     * @param  string $module
     * @param  string|array $ids Id of the record
     * @return EntityResponse[]
     * @throws ZCRMException
     */
    public function deleteRecords($module, $ids)
    {
        $zcrmModuleIns = $this->getModule($module);
        if(is_string($ids)) {
            $ids = [$ids];
        }
        try{
            /**
             * @var $bulkAPIResponse BulkAPIResponse
             */
            $bulkAPIResponse = $zcrmModuleIns->deleteRecords($ids);
            return $bulkAPIResponse->getEntityResponses();
        } catch(ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot delete the record(s) {id} for the module {moduleName}', [
                'moduleName' => $module,
                'id' => implode(',', $ids)
            ]);
            throw  $ex;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements getRecordById API method.
     *
     * @param  string $module The module to use
     * @param  string $id Id of the record or a list of IDs separated by a semicolon
     * @return ZCRMRecord
     * @throws ZCRMException
     */
    public function getRecordById($module, $id)
    {
        try{
            /**
             * @var $response APIResponse
             */
            $response = $this->getModule($module)->getRecord($id);
            return $response->getData();
        }catch(ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot get record {id} for the module {moduleName}. Maybe it does not exist or something wrong', [
                'moduleName' => $module,
                'id' => $id
            ]);
            throw $ex;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements getRecords API method.
     *
     * @param  string $module
     * @param  string|null $cvId
     * @param  string|null $sortColumnString
     * @param  string|null $sortOrderString
     * @param  int $fromIndex
     * @param  int $toIndex
     * @param  null $header
     * @return ZCRMRecord[]
     * @throws ZCRMException
     */
    public function getRecords($module, $cvId = null, $sortColumnString = null, $sortOrderString = null, $fromIndex = 1, $toIndex = 200, $header = null)
    {

        $zcrmModuleIns = $this->getModule($module);
        try{
            /**
             * @var $bulkAPIResponse BulkAPIResponse
             */
            $bulkAPIResponse = $zcrmModuleIns->getRecords($cvId, $sortColumnString, $sortOrderString, $fromIndex, $toIndex, $header);
            return $bulkAPIResponse->getData();
        } catch (ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot get records for the module {moduleName}', [
                'moduleName' => $module,
            ]);
            throw $ex;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
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
     * @return BulkAPIResponse|null
     * @throws ZCRMException
     * @see    ZCRMModule::getAllDeletedRecords()
     * @see    ZCRMModule::getRecycleBinRecords()
     * @see    ZCRMModule::getPermanentlyDeletedRecords()
     * @see    MassEntityAPIHandler::getAllDeletedRecords()
     * @see    MassEntityAPIHandler::getRecycleBinRecords()
     * @see    MassEntityAPIHandler::getPermanentlyDeletedRecords()
     * @see    MassEntityAPIHandler::getDeletedRecords()
     */
    public function getDeletedRecords($module, $typeOfRecord = "all", \DateTimeInterface $lastModifiedTime = null, $page = 1, $perPage= 200)
    {
        try
        {
            $zcrmModuleIns = $this->getModule($module);
            $massEntityAPIHandler = MassEntityAPIHandler::getInstance($zcrmModuleIns);
            if($lastModifiedTime) {

                $massEntityAPIHandler->addHeader('If-Modified-Since', $lastModifiedTime->format(\DateTime::ATOM));
            }
            $massEntityAPIHandler->addParam('page', $page);
            $massEntityAPIHandler->addParam('per_page', $perPage);
            $massEntityAPIHandler->setUrlPath($zcrmModuleIns->getAPIName()."/deleted");
            $massEntityAPIHandler->setRequestMethod(APIConstants::REQUEST_METHOD_GET);
            $massEntityAPIHandler->addHeader("Content-Type", "application/json");
            $massEntityAPIHandler->addParam("type", $typeOfRecord);
            /**
             * @var $responseInstance BulkAPIResponse
             */
            $responseInstance=APIRequest::getInstance($massEntityAPIHandler)->getBulkAPIResponse();

            $responseJSON=$responseInstance->getResponseJSON();
            $trashRecordList=array();
            if(isset($responseJSON['data'])) {
                $trashRecords=$responseJSON["data"];
                foreach ($trashRecords as $trashRecord)
                {
                    $trashRecordInstance = ZCRMTrashRecord::getInstance($trashRecord['type'], $trashRecord['id']);
                    $massEntityAPIHandler->setTrashRecordProperties($trashRecordInstance, $trashRecord);
                    array_push($trashRecordList, $trashRecordInstance);
                }
            }

            $responseInstance->setData($trashRecordList);
            return $responseInstance;
        }
        catch (ZCRMException $exception)
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
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * @param  string                  $module
     * @param  \DateTimeInterface|null $lastModifiedTime
     * @param  int                     $page
     * @param  int                     $perPage
     * @return BulkAPIResponse
     * @throws ZCRMException
     * @see    ZCRMModule::getPermanentlyDeletedRecords()
     * @see    MassEntityAPIHandler::getPermanentlyDeletedRecords()
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
     * @return BulkAPIResponse
     * @throws ZCRMException
     * @see    ZCRMModule::getAllDeletedRecords()
     * @see    MassEntityAPIHandler::getAllDeletedRecords()
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
     * @return BulkAPIResponse
     * @throws ZCRMException
     * @see    ZCRMModule::getRecycleBinRecords()
     * @see    MassEntityAPIHandler::getRecycleBinRecords()
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
     * @param  string $relatedListAPIName
     * @param  string|null $sortByField
     * @param  string|null $sortByOrder
     * @param  int $page
     * @param  int $perPage
     * @return BulkAPIResponse
     * @throws ZCRMException
     */
    public function getRelatedRecords($module, $id, $relatedListAPIName, $sortByField = null, $sortByOrder = null, $page = 1, $perPage = 200)
    {
        /**
         * @var $zcrmRecordIns ZCRMRecord
         */
        $zcrmRecordIns  = $this->getRecordById($module, $id);
        try{
            $bulkAPIResponse = $zcrmRecordIns->getRelatedListRecords($relatedListAPIName, $sortByField, $sortByOrder, $page, $perPage);
        } catch(ZCRMException $exception){
            if(ExceptionZohoClient::exceptionCodeFormat($exception->getExceptionCode()) === ExceptionZohoClient::EXCEPTION_CODE_NO__CONTENT) {
                return null;
            }
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot get related records from the record id {id} for the module {moduleName}', [
                'moduleName' => $module,
                'id' => $id
            ]);
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }

        return $bulkAPIResponse;
    }

    /**
     * Implements searchRecords API method.
     * For unit tests or search after creation of entities you have to wait indexing from zoho.
     *
     * @param  $module
     * @param  mixed $searchCondition
     * @param string $type Type of search(among phone, email, criteria, word).By default  search by word
     * @param  int $page
     * @param  int $perPage
     * @return ZCRMRecord[]
     * @throws ZCRMException
     */
    public function searchRecords($module, $searchCondition, string $type = 'word', $page = 1, $perPage = 200)
    {
        $zcrmModuleIns = $this->getModule($module);
        try{
            if($type === 'word') {
                $bulkAPIResponse = $zcrmModuleIns->searchRecords($searchCondition, $page, $perPage);
            } else{
                $typeSearchMethod = "searchRecordsBy".ucfirst($type);
                $bulkAPIResponse = $zcrmModuleIns->{"$typeSearchMethod"}($searchCondition, $page, $perPage);
            }
            return $bulkAPIResponse->getData();
        }catch (ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot search records from {moduleName} with searchCondition "{searchCondition}" and type "{type}"', [
                'moduleName' => $module,
                'searchCondition' => $searchCondition,
                'type' => $type
            ]);
            throw $ex;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements getUser API method.
     *
     * @param $userId
     * @param  string|null $orgName
     * @param  string|null $orgId
     * @return ZCRMUser
     * @throws ZCRMException
     */
    public function getUser($userId ,$orgName = null, $orgId = null)
    {
        $this->initCLient();
        try{
            /**
             * @var $APIResponse APIResponse
             */
            $APIResponse = ZCRMOrganization::getInstance($orgName, $orgId)->getUser($userId);
            return $APIResponse->getData();
        } catch(ZCRMException $ex){
            $this->logClientException(__METHOD__, $ex,'error', 'Cannot get user with id {id} , organisation Name "{orgName}" and organisation ID {orgId}', [
                'id' => $userId,
                'orgName' => $orgName,
                'orgId' => $orgId
            ]);
            throw $ex;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }


    /**
     * Implements getUsers API method.
     *
     * @param  string $type The type of users you want retrieve (among AllUsers, ActiveUsers, DesactiveUsers, AdminUsers and ActiveConfirmedAdmins)
     * @param  string|null $orgName
     * @param  string|null $orgId
     * @return ZCRMUser[]
     * @throws ZCRMException
     */
    public function getUsers($type = 'AllUsers',$orgName = null, $orgId = null)
    {
        $typeMethod = "get" . $type;
        $this->initCLient();
        try{
            /**
             * @var $bulkAPIResponse BulkAPIResponse
             */
            $bulkAPIResponse = ZCRMOrganization::getInstance($orgName, $orgId)->{"$typeMethod"}();
            return $bulkAPIResponse->getData();
        }catch(ZCRMException $exception){
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot get {type} users',['type' => $type]);
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements insert or update Records API method.
     *
     * @param  $module
     * @param  array|ZCRMRecord[] $records
     * @return EntityResponse[]
     */
    public function upsertRecords($module, array $records)
    {
        try{
            $zcrmModuleIns = $this->getModule($module);
            /**
             * @var $bulkAPIResponse BulkAPIResponse
             */
            $bulkAPIResponse = $zcrmModuleIns->upsertRecords($records);
            return $bulkAPIResponse->getEntityResponses();
        } catch(ZCRMException $exception){
            $recordsJson = [];
            foreach ($records as $record){
                $recordsJson[]=EntityAPIHandler::getInstance($record)->getZCRMRecordAsJSON();
            }
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot upsert records for the module {moduleName}. Send Data: {json}', [
                'moduleName' => $module,
                'json' => json_encode($recordsJson)
            ]);
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }


    /**
     * Implements insertRecords API method.
     *
     * @param  $module
     * @param  ZCRMRecord[] $records
     * @param bool|null $trigger
     * @return EntityResponse[]
     * @throws ZCRMException
     */
    public function insertRecords($module, array $records,  ?bool $trigger = null)
    {
        try{
            $zcrmModuleIns = $this->getModule($module);
            /**
             * @var $bulkAPIResponse BulkAPIResponse
             */
            $bulkAPIResponse = $zcrmModuleIns->createRecords($records, $trigger);
            return $bulkAPIResponse->getEntityResponses();
        } catch(ZCRMException $exception){
            $recordsJson = [];
            foreach ($records as $record){
                $recordsJson[]=EntityAPIHandler::getInstance($record)->getZCRMRecordAsJSON();
            }
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot insert records for the module {moduleName}. Send Data: {json}', [
                'moduleName' => $module,
                'json' => json_encode($recordsJson)
            ]);
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements updateRecords API method.
     *
     * @param string $module
     * @param  ZCRMRecord[] $records
     * @param bool|null $trigger
     * @return EntityResponse[]
     * @throws \Exception
     */
    public function updateRecords(string $module, array $records,  ?bool $trigger = null)
    {
        $zcrmModuleIns = $this->getModule($module);

        try{
            /**
             * @var $bulkAPIResponse BulkAPIResponse
             */
            $bulkAPIResponse = $zcrmModuleIns->updateRecords($records, $trigger);
            return $bulkAPIResponse->getEntityResponses();

        } catch(\Exception $exception){
            $recordsJson = [];
            foreach ($records as $record){
                $recordsJson[]=EntityAPIHandler::getInstance($record)->getZCRMRecordAsJSON();
            }
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot update records for the module {moduleName}. Send Data: {json}', [
                'moduleName' => $module,
                'json' => json_encode($recordsJson)
            ]);
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements updateRelatedRecords API method.
     *
     * @param string $module
     * @param string $recordId
     * @param string $relatedModule
     * @param string $relatedRecordId
     * @param  array $fieldsValue
     * @return APIResponse
     * @throws ZCRMException
     */
    public function updateRelatedRecords(string $module, string $recordId,  string $relatedModule, string $relatedRecordId,  array $fieldsValue = [])
    {
        try{
            $parentRecord= $this->getRecordById($module, $recordId);
            $junctionRecord= ZCRMJunctionRecord::getInstance($relatedModule, $relatedRecordId);
            foreach ($fieldsValue as $fieldApiName => $value){
                $junctionRecord->setRelatedData($fieldApiName, $value);
            }
            return $parentRecord->addRelation($junctionRecord);
        } catch(ZCRMException $exception){
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot update related records for the module {moduleName} for the record id  {id} for related module {relatedModule} and related reecord {relatedId}', [
                'moduleName' => $module,
                'id' => $recordId,
                'relatedModule' => $relatedModule,
                'relatedId' => $relatedRecordId
            ]);
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    /**
     * Implements uploadFile API method.
     *
     * @param string $module
     * @param string $recordId
     * @param string $filepath
     * @return APIResponse
     * @throws ZCRMException
     */
    public function uploadFile(string $module, string $recordId, string $filepath)
    {
        $record = $this->getRecordById($module, $recordId);
        try{
            return $record->uploadAttachment($filepath);
        } catch(ZCRMException $exception){
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot upload {filepath} for the module {moduleName} and  the record id  {id}', [
                'moduleName' => $module,
                'id' => $recordId,
                'filepath' => $filepath
            ]);
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }


    /**
     * Implements downloadFile API method.
     *
     * @param string $module
     * @param string $recordId
     * @param string $attachmentId
     * @return FileAPIResponse
     * @throws ZCRMException
     */
    public function downloadFile(string $module, string $recordId, string $attachmentId)
    {
        $record = $this->getRecordById($module, $recordId);
        try{
            return $record->downloadAttachment($attachmentId);
        } catch(ZCRMException $exception){
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot download attchment #{fileID} for the module {moduleName} and  the record id  {id}', [
                'moduleName' => $module,
                'id' => $recordId,
                'fileID' => $attachmentId
            ]);
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }


    /**
     * Returns a module from Zoho.
     *
     * @param  string $moduleName
     * @return ZCRMModule
     */
    public function getModule(string $moduleName)
    {
        $this->initCLient();
        return ZCRMModule::getInstance($moduleName);
    }


    /**
     * Returns a list of modules from Zoho.
     *
     * @return ZCRMModule[]
     * @throws ZCRMException
     */
    public function getModules(): array
    {
        $this->initCLient();
        try{
            /**
             * @var $bulkAPIResponse BulkAPIResponse
             */
            $bulkAPIResponse =  ZCRMRestClient::getInstance()->getAllModules();
            return $bulkAPIResponse->getData();
        } catch (ZCRMException $exception){
            $this->logClientException(__METHOD__, $exception,'error', 'Cannot get all modules');
            throw $exception;
        }
        catch (ZohoOAuthException $exceptionAuth){
            $this->logAuthException(__METHOD__, $exceptionAuth);
        }
    }

    public function logException(ZCRMException $exception){
        APIExceptionHandler::logException($exception);
    }

    /**
     * @param string $method
     * @param \Exception $exception
     * @param string $type
     * @param string $message
     * @param array $contextParams
     */
    private function  logClientException(string $method, \Exception $exception,$type = 'error', string $message, array $contextParams = []){

        $this->logger->{$type}($message? $message.'. From '.$method.'()' : $exception->getMessage(), array_merge([
            'exception' => $exception], $contextParams));
    }

    private function logAuthException(string $method, \Exception $exception){
        $this->logger->error('Can process method {method} because of authentication problem', ['exception' => $exception]);
        throw $exception;
    }
}
