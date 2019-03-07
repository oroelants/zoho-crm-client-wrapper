<?php

namespace Wabel\Zoho\CRM;

use PHPUnit\Framework\TestCase;

class ZohoClientTest extends TestCase
{
    /**
     * @var ZohoClient
     */
    private $zohoClient;

    protected function setUp()
    {
        $this->zohoClient  = new ZohoClient(
            [
                'client_id' => getenv('client_id'),
                'client_secret' => getenv('client_secret'),
                'redirect_uri' => getenv('redirect_uri'),
                'currentUserEmail' => getenv('currentUserEmail'),
                'applicationLogFilePath' => getenv('applicationLogFilePath'),
                'persistence_handler_class' => getenv('persistence_handler_class'),
                'token_persistence_path' => getenv('token_persistence_path'),
            ],
            getenv('timeZone')
        );
    }

    public function testGetModules()
    {
        $allModules = $this->zohoClient->getModules();
        $this->assertNotEmpty($allModules);
        $this->assertContainsOnlyInstancesOf('\ZCRMModule', $allModules);
    }

    public function testGetModule()
    {
        $module = $this->zohoClient->getModule('Accounts');
        $this->assertInstanceOf('\ZCRMModule', $module);
    }

    public function testGetFields()
    {
        $allFields = $this->zohoClient->getFields('Accounts');
        $this->assertNotEmpty($allFields);
        $this->assertContainsOnlyInstancesOf('\ZCRMField', $allFields);
    }

    public function testGetAllUsers()
    {
        $users = $this->zohoClient->getUsers();
        $this->assertNotEmpty($users);
        $this->assertContainsOnlyInstancesOf('\ZCRMUser', $users);
    }

    public function testGetUser()
    {
        $user = $this->zohoClient->getUser(getenv('userid_test'));
        $this->assertInstanceOf('\ZCRMUser', $user);
        $this->assertEquals(getenv('userid_test'), $user->getId());
    }


    /**
     * @return \ZCRMRecord[]
     */
    public function testInsertRecords()
    {
        $lead1 = \ZCRMRecord::getInstance('Leads', null);
        $lead2 = \ZCRMRecord::getInstance('Leads', null);
        $lead3 = \ZCRMRecord::getInstance('Leads', null);
        $lead1->setFieldValue('Last_Name', 'Lead 1');
        $lead2->setFieldValue('Last_Name', 'Lead 2');
        $lead3->setFieldValue('Last_Name', 'Lead 3');
        $lead1->setFieldValue('Company', 'Company Lead 1');
        $lead2->setFieldValue('Company', 'Company Lead 2');
        $lead3->setFieldValue('Company', 'Company Lead 3');
        $response = $this->zohoClient->insertRecords('Leads', [$lead1, $lead2, $lead3]);
        $this->assertNotEmpty($response);
        $this->assertNotNull($lead1->getEntityId());
        $this->assertNotNull($lead2->getEntityId());
        $this->assertNotNull($lead3->getEntityId());
        return [$lead1, $lead2, $lead3];
    }

    /**
     * @depends testInsertRecords
     * @param   \ZCRMRecord[] $leads
     */
    public function testUpdateRecords(array $leads)
    {
        $leads[0]->setFieldValue('Last_Name', 'Lead 1-1');
        $leads[1]->setFieldValue('Last_Name', 'Lead 2-1');
        $leads[2]->setFieldValue('Last_Name', 'Lead 3-1');
        $entityResponses = $this->zohoClient->updateRecords('Leads', $leads);
        $firstEntity = array_shift($entityResponses);
        $this->assertInstanceOf('\ZCRMRecord', $firstEntity->getData());
        $this->assertEquals($leads[0]->getFieldValue('Last_Name'), $firstEntity->getData()->getFieldValue('Last_Name'));
        return $leads;
    }

    /**
     * @depends testUpdateRecords
     * @param   \ZCRMRecord[] $leads
     * @return  \ZCRMRecord[]
     */
    public function testGetRecords(array $leads)
    {
        $records = $this->zohoClient->getRecords(
            'Leads', null, 'Created_Time', 'desc', 1, 4,
            ['If-Modified-Since' => (new \DateTime())->sub(new \DateInterval('PT2M'))->format(\DateTime::ATOM)]
        );
        $firstEntity = array_shift($records);
        $this->assertContainsOnlyInstancesOf('\ZCRMRecord', $records);
        $this->assertContains(
            $firstEntity->getEntityId(), array_map(
                function (\ZCRMRecord $leadEntity) {
                    return $leadEntity->getEntityId();
                }, $leads
            )
        );
        return $leads;
    }

    /**
     * @depends testGetRecords
     * @param   \ZCRMRecord[] $leads
     */
    public function testDeleteRecords(array $leads)
    {
        $zohoEntitiesReponse = $this->zohoClient->deleteRecords('Leads', [$leads[0]->getEntityId(),$leads[1]->getEntityId()]);
        $this->assertNotEmpty($zohoEntitiesReponse);
        $firstEntity = array_shift($zohoEntitiesReponse);
        $this->assertInstanceOf('\ZCRMRecord', $firstEntity->getData());
        $this->assertEquals('success', $firstEntity->getStatus());
        return $leads[2];
    }

    /**
     * @depends testDeleteRecords
     * @param   \ZCRMRecord $lead
     */
    public function testGetRecordById(\ZCRMRecord $lead)
    {
        $record = $this->zohoClient->getRecordById('Leads', $lead->getEntityId());
        $this->assertInstanceOf('\ZCRMRecord', $record);
        $this->assertEquals($lead->getEntityId(), $record->getEntityId());
        return $lead;
    }

    /**
     * @depends testGetRecordById
     * @param   \ZCRMRecord $lead
     * @return  \ZCRMRecord
     */
    public function testDeleteRecord(\ZCRMRecord $lead)
    {
        $zohoEntitiesReponse = $this->zohoClient->deleteRecords('Leads', $lead->getEntityId());
        $this->assertNotEmpty($zohoEntitiesReponse);
        $firstEntity = array_shift($zohoEntitiesReponse);
        $this->assertInstanceOf('\ZCRMRecord', $firstEntity->getData());
        $this->assertEquals($lead->getEntityId(), $firstEntity->getDetails()['id']);
        return $lead;
    }

    /**
     * @depends testGetRecordById
     * @param   \ZCRMRecord $lead
     */
    public function testGetDeletedRecords(\ZCRMRecord $lead)
    {
        $trashEntities = $this->zohoClient->getDeletedRecords('Leads');
        $this->assertNotEmpty($trashEntities);
        $this->assertContainsOnlyInstancesOf('\ZCRMTrashRecord', $trashEntities->getData());
        $this->assertContains(
            $lead->getFieldValue('Last_Name'), array_map(
                function (\ZCRMTrashRecord $trashRecord) {
                    return $trashRecord->getDisplayName();
                }, $trashEntities->getData()
            )
        );
    }

    /**
     * @depends testGetDeletedRecords
     */
    public function testUpsertRecordsAndSearch()
    {
        $lead4 = \ZCRMRecord::getInstance('Leads', null);
        $lead5 = \ZCRMRecord::getInstance('Leads', null);
        $lead6 = \ZCRMRecord::getInstance('Leads', null);
        $lead4->setFieldValue('Last_Name', 'NewLead 4');
        $lead5->setFieldValue('Last_Name', 'NewLead 5');
        $lead6->setFieldValue('Last_Name', 'NewLead 6');
        $lead4->setFieldValue('Company', 'Company Lead 4');
        $lead5->setFieldValue('Company', 'Company Lead 5');
        $lead6->setFieldValue('Company', 'Company Lead 6');
        $response = $this->zohoClient->upsertRecords('Leads', [$lead4,$lead5]);
        $this->assertNotEmpty($response);
        $this->assertNotNull($lead4->getEntityId());
        $this->assertNotNull($lead5->getEntityId());
        $this->assertNull($lead6->getEntityId());
        $lastIdLead4 = $lead4->getEntityId();
        $lead4->setFieldValue('Last_Name', 'NewLead 4-1');
        $lead4->setFieldValue('Company', 'Company Lead 4-1');
        $response2 = $this->zohoClient->upsertRecords('Leads', [$lead4,$lead6]);
        $this->assertNotEmpty($response);
        $this->assertEquals($lastIdLead4, $response2[0]->getData()->getEntityId());
        $this->assertNotNull($lead6->getEntityId());
        $this->zohoClient->deleteRecords('Leads', [$lead4->getEntityId(),$lead5->getEntityId(),$lead6->getEntityId()]);
    }


    public function testConvertLeadNoDeal()
    {
        $lead = \ZCRMRecord::getInstance('Leads', null);
        $lead->setFieldValue('Last_Name', 'Lead To Convert');
        $lead->setFieldValue('Company', 'Company To Convert');
        $this->zohoClient->insertRecords('Leads', [$lead]);
        $this->assertNotNull($lead->getEntityId());
        $conversionResult = $this->zohoClient->convertLead($lead->getEntityId(), null, getenv('userid_test'));
        $this->assertArrayHasKey(\APIConstants::ACCOUNTS, $conversionResult);
        $this->assertArrayHasKey(\APIConstants::CONTACTS, $conversionResult);
    }

    public function testConvertLeadWithDeal()
    {
        $lead2 = \ZCRMRecord::getInstance('Leads', null);
        $lead2->setFieldValue('Last_Name', 'Contact LastLead To Convert 2');
        $lead2->setFieldValue('First_Name', 'Contact FirstLead To Convert 2');
        $lead2->setFieldValue('Company', 'Account Lead To Convert 2');
        $this->zohoClient->insertRecords('Leads', [$lead2]);
        $this->assertNotNull($lead2->getEntityId());
        $account = \ZCRMRecord::getInstance('Accounts', null);
        $account->setFieldValue('Account_Name', 'Account Lead To Convert 2');
        $this->zohoClient->insertRecords('Accounts', [$account]);
        $this->assertNotNull($account->getEntityId());
        $campaign = \ZCRMRecord::getInstance('Campaigns', null);
        $campaign->setFieldValue('Campaign_Name', 'Campaign Test Lead Convert');
        $campaign->setFieldValue('Type', getenv('campaign_type'));
        $this->zohoClient->insertRecords('Campaigns', [$campaign]);
        $this->assertNotNull($campaign->getEntityId());
        $deal = \ZCRMRecord::getInstance('Deals', null);
        $deal->setFieldValue('Deal_Name', 'Deal Lead To Convert 2');
        $deal->setFieldValue('Closing_Date', (new \DateTime())->sub(new \DateInterval('P1D'))->format('Y-m-d'));
        $deal->setFieldValue('Stage', getenv('deal_status'));
        $deal->setFieldValue('Account_Name', $account);
        $deal->setFieldValue('Amount', rand(10000, 20000));
        $deal->setFieldValue('Campaign_Source', $campaign);
        $this->zohoClient->insertRecords('Deals', [$deal]);
        $this->assertNotNull($deal->getEntityId());
        $conversionResult2 = $this->zohoClient->convertLead($lead2->getEntityId(), $deal->getEntityId(), getenv('userid_test'));
        $this->assertArrayHasKey(\APIConstants::ACCOUNTS, $conversionResult2);
        $this->assertArrayHasKey(\APIConstants::CONTACTS, $conversionResult2);
        $this->assertArrayHasKey(\APIConstants::DEALS, $conversionResult2);
        $this->assertNotNull($conversionResult2[\APIConstants::ACCOUNTS]);
        $this->assertNotNull($conversionResult2[\APIConstants::CONTACTS]);
        $this->assertNotNull($conversionResult2[\APIConstants::DEALS]);
    }

    public function testUpdateRelatedRecords()
    {
        $account = \ZCRMRecord::getInstance('Accounts', null);
        $account->setFieldValue('Account_Name', 'New Account Related List');
        $this->zohoClient->insertRecords('Accounts', [$account]);
        $this->assertNotNull($account->getEntityId());
        $product1 = \ZCRMRecord::getInstance('Products', null);
        $product1->setFieldValue('Product_Name', 'New Product1 Doe');
        $product1->setFieldValue('Unit_Price', rand(20, 42));
        $product2 = \ZCRMRecord::getInstance('Products', null);
        $product2->setFieldValue('Product_Name', 'New Product2 Doe');
        $this->zohoClient->insertRecords('Products', [$product1, $product2]);
        $this->assertNotNull($product1->getEntityId());
        $this->assertNotNull($product2->getEntityId());
        $relatedLists = $this->zohoClient->getRelatedRecords('Accounts', $account->getEntityId(), 'Products');
        $this->assertNull($relatedLists);
        $this->zohoClient->updateRelatedRecords('Accounts', $account->getEntityId(), 'Products', $product1->getEntityId());
        $valueFields = ['Unit_Price' => rand(5, 50)];
        $this->zohoClient->updateRelatedRecords('Accounts', $account->getEntityId(), 'Products', $product2->getEntityId(), $valueFields);
        $relatedLists = $this->zohoClient->getRelatedRecords('Accounts', $account->getEntityId(), 'Products');
        $this->assertCount(2, $relatedLists->getData());

    }
    //
    public function testUploadFile()
    {
        $account = \ZCRMRecord::getInstance('Accounts', null);
        $account->setFieldValue('Account_Name', 'Account To Upload File');
        $this->zohoClient->insertRecords('Accounts', [$account]);
        $this->assertNotNull($account->getEntityId());
        $response = $this->zohoClient->uploadFile('Accounts', $account->getEntityId(), getenv('filepath_upload'));
        $this->assertNotNull($response->getDetails());
        $this->assertArrayHasKey('id', $response->getDetails());
        $this->assertInstanceOf('\ZCRMAttachment', $response->getData());
        return $response->getData();
    }

    /**
     * @depends testUploadFile
     * @param   \ZCRMAttachment fileUploaded
     */
    public function testDownloadFile(\ZCRMAttachment $fileUploaded)
    {
        $fileApiResponse = $this->zohoClient->downloadFile('Accounts', $fileUploaded->getParentRecord()->getEntityId(), $fileUploaded->getId());
        $filename= pathinfo(getenv('filepath_upload'), PATHINFO_BASENAME);
        $this->assertEquals($filename, $fileApiResponse->getFileName());
        $this->assertNotNull($fileApiResponse->getFileContent());
    }

}