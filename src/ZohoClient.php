<?php

namespace Wabel\Zoho\CRM;

/**
 * Client for provide interface with Zoho CRM.
 *
 */
class ZohoClient
{

    /**
     * @var array|null
     */
    private $configurations;

    /**
     * ZohoClient constructor.
     * @param array|null $configurations
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
     *
     */
    public function __construct(?array $configurations = null)
    {
        $this->configurations = $configurations;
    }

    public function initCLient() :void{
        \ZCRMRestClient::initialize($this->configurations);
    }

    /**
     * @return \ZohoOAuthClient
     * @throws \ZohoOAuthException
     */
    public function getZohoOAuthClient(){
        $this->initCLient();
        return \ZohoOAuth::getClientInstance();
    }

    /**
     * @return \ZCRMRestClient
     */
    public function getZCRMRestClient(){
        $this->initCLient();
        return \ZCRMRestClient::getInstance();
    }

    /**
     * @param string $grantToken
     * @return \ZohoOAuthTokens
     * @throws \ZohoOAuthException
     */
    public function generateAccessToken(string $grantToken){
        $client = $this->getZohoOAuthClient();
        return $client->generateAccessToken($grantToken);
    }

    /**
     * @param string $refreshToken
     * @param string $userIdentifier
     * @return mixed
     */
    public function generateAccessTokenFromRefreshToken(string $refreshToken, string $userIdentifier){
        $oAuthClient = $this->getZohoOAuthClient();
        return $oAuthClient->generateAccessTokenFromRefreshToken($refreshToken,$userIdentifier);
    }

    /**
     * Implements convertLead API method.
     * @param string $leadId
     * @param string|null $dealId
     * @param string|null $userId
     * @return array
     */
    public function convertLead($leadId, $dealId = null, $userId = null)
    {
        $record = $this->getRecordById("Leads", $leadId);
        $userInstance = null;
        $recordDeal = null;
        if($dealId){
            $modIns = $this->getModule("Deals");
            $recordDeal = $modIns->getRecord($dealId)->getData();
        }
        if($userId){
            $userInstance = $this->getUser($userId);
        }
        return $record->convert($recordDeal, $userInstance);
    }

    /**
     * Implements getFields API method.
     *
     * @return \ZCRMField[]
     */
    public function getFields($module)
    {
        /**
         * @var $response \APIResponse
         */
        $response = $this->getModule($module)->getAllFields();
        return $response->getData();
    }

    /**
     * Implements deleteRecords API method.
     *
     * @param string $module
     * @param string|array $ids     Id of the record
     * @return \EntityResponse[]
     */
    public function deleteRecords($module, $ids)
    {
        $zcrmModuleIns = $this->getModule($module);
        if(is_string($ids)){
            $ids = [$ids];
        }
        /**
         * @var $bulkAPIResponse \BulkAPIResponse
         */
        $bulkAPIResponse = $zcrmModuleIns->deleteRecords($ids);

        return $bulkAPIResponse->getEntityResponses();
    }

    /**
     * Implements getRecordById API method.
     *
     * @param string $module The module to use
     * @param string $id     Id of the record or a list of IDs separated by a semicolon
     * @return \ZCRMRecord
     */
    public function getRecordById($module, $id)
    {
        /**
         * @var $response \APIResponse
         */
        $response = $this->getModule($module)->getRecord($id);
        return $response->getData();
    }
    /**
     * Implements getRecords API method.
     * @param string $module
     * @param string|null $cvId
     * @param string|null $sortColumnString
     * @param string|null $sortOrderString
     * @param int $fromIndex
     * @param int $toIndex
     * @param null $header
     * @return \ZCRMRecord[]
     */
    public function getRecords($module, $cvId = null, $sortColumnString = null, $sortOrderString = null, $fromIndex = 1, $toIndex = 200, $header = null)
    {

        $zcrmModuleIns = $this->getModule($module);
        /**
         * @var $bulkAPIResponse \BulkAPIResponse
         */
        $bulkAPIResponse = $zcrmModuleIns->getRecords($cvId,$sortColumnString, $sortOrderString, $fromIndex, $toIndex, $header);
        return $bulkAPIResponse->getData();
    }

    /**
     * Implements getDeletedRecordIds API method.
     * @param $module
     * @param string $typeOfRecord
     * @return \ZCRMTrashRecord[]
     */
    public function getDeletedRecordIds($module, $typeOfRecord = "all")
    {
        $zcrmModuleIns = $this->getModule($module);

        /**
         * @var $response \BulkAPIResponse
         */
        switch ($typeOfRecord){
            case "recycle":
                $response = $zcrmModuleIns->getRecycleBinRecords();
            case "permanent":
                $response = $zcrmModuleIns->getPermanentlyDeletedRecords();
            case 'all':
            default:
                $response = $zcrmModuleIns->getAllDeletedRecords();
            break;

        }

        return $response->getData();
    }

    /**
     * Implements get Related List Records API method.
     * @param $module
     * @param $id
     * @param string $relatedListAPIName
     * @param string|null $sortByField
     * @param string|null $sortByOrder
     * @param int $page
     * @param int $perPage
     * @return \ZCRMRecord[]
     */
    public function getRelatedListRecords($module, $id, $relatedListAPIName, $sortByField = null, $sortByOrder = null, $page = 1, $perPage = 200)
    {
        /**
         * @var $zcrmRecordIns \ZCRMRecord
         */
        $zcrmRecordIns  = $this->getRecordById($module, $id);
        $bulkAPIResponse = $zcrmRecordIns->getRelatedListRecords($relatedListAPIName, $sortByField, $sortByOrder , $page, $perPage);
        return $bulkAPIResponse->getData();
    }

    /**
     * Implements searchRecords API method.
     * For unit tests or search after creation of entities you have to wait indexing from zoho.
     * @param $module
     * @param mixed $searchCondition
     * @param string $type Type of search(among phone, email, criteria, word).By default  search by word
     * @param int $page
     * @param int $perPage
     * @return \ZCRMRecord[]
     */
    public function searchRecords($module, $searchCondition, string $type = 'word', $page = 1, $perPage = 200)
    {
        $zcrmModuleIns = $this->getModule($module);
        if($type === 'word'){
            $bulkAPIResponse = $zcrmModuleIns->searchRecords($searchCondition,$page, $perPage);
        } else{
            $typeSearchMethod = "searchRecordsBy".ucfirst($type);
            $bulkAPIResponse = $zcrmModuleIns->{"$typeSearchMethod"}($searchCondition,$page, $perPage);
        }
        return $bulkAPIResponse->getData();
    }

    /**
     * Implements getUser API method.
     * @param string|null $orgName
     * @param string|null $orgId
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
     * @param string $type The type of users you want retrieve (among AllUsers, ActiveUsers, DesactiveUsers, AdminUsers and ActiveConfirmedAdmins)
     * @param string|null $orgName
     * @param string|null $orgId
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
     * @param $module
     * @param array|\ZCRMRecord[] $records
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
     * @param $module
     * @param \ZCRMRecord[] $records
     * @param null|bool $trigger
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
     * @param string $module
     * @param \ZCRMRecord[] $records
     * @param null|bool $trigger
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
     * @param string $module
     * @param string $relatedModule
     * @param string $recordId
     * @param string $relatedRecordId
     * @param array $fieldsValue
     * @return \APIResponse
     */
    public function updateRelatedRecords(string $module, string $relatedModule, string $recordId, string $relatedRecordId,  array $fieldsValue)
    {
        $parentRecord=\ZCRMRecord::getInstance($module, $recordId);
        $junctionRecord=\ZCRMJunctionRecord::getInstance($relatedModule, $relatedRecordId);
        foreach ($fieldsValue as $fieldApiName => $value){
            $junctionRecord->setRelatedData($fieldApiName, $value);
        }
        return $parentRecord->addRelation($junctionRecord);
    }

    /**
     * Implements uploadFile API method.
     * @param string $module
     * @param string $recordId
     * @param string $filepath
     * @return \APIResponse
     */
    public function uploadFile(string $module, string $recordId, string $filepath)
    {
        $record = \ZCRMRecord::getInstance($module, $recordId);
        return $record->uploadAttachment($filepath);
    }



    /**
     * Implements downloadFile API method.
     * @param string $module
     * @param string $recordId
     * @param string $attachmentId
     * @return \FileAPIResponse
     */
    public function downloadFile(string $module, string $recordId, string $attachmentId)
    {
        $record = \ZCRMRecord::getInstance($module, $recordId);
        return $record->downloadAttachment($attachmentId);
    }


    /**
     * Returns a module from Zoho.
     * @param  string $moduleName
     * @return \ZCRMModule
     */
    public function getModule(string $moduleName){
        $this->initCLient();
        return \ZCRMModule::getInstance($moduleName);
    }


    /**
     * Returns a list of modules from Zoho.
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

}
