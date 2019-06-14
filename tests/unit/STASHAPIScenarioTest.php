<?php

namespace Stash;

use Stash\StashAPI as STASHAPI;
use \Exception as Exception;

/**
 * Class STASHAPIScenarioTest
 * Runs scenario based testing for each of the API functions
 * @package Stash
 */
class STASHAPIScenarioTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    const testFile = "tmpfile_stashapitest.txt";        // Test file to use for uploads/write - will be deleted upon completion of all tests (see tearDownAfterClass())
    private $apiid;
    private $apipw;
    private $baseUrl;
    private $accountPw;
    private $folderPath;
    private $folderId;

    /**
     * This function is run before each individual test
     */
    protected function _before()
    {
        $configArray = parse_ini_file(codecept_data_dir("creds.ini"));
        $this->apiid = $configArray['apiid'];
        $this->apipw = $configArray['apipw'];
        $this->baseUrl = $configArray['baseurl'];
        $this->accountPw = $configArray['filekey'];
        $this->folderId = $configArray['folderid'];
        $this->folderPath = $configArray['folderpath'];
        unset($configArray);
    }

    /**
     * This function is run after each individual test
     */
    protected function _after()
    {

    }

    /**
     * The function is run once, before all tests in the suite are run
     * @throws \Exception
     */
    public static function setUpBeforeClass()
    {
        if (!file_exists(codecept_data_dir("creds.ini"))) {
            throw new Exception("Required file: creds.ini missing from _data directory");
        }
    }

    /**
     * This function is run once, after all tests in the suite are run
     */
    public static function tearDownAfterClass()
    {   if (file_exists(codecept_data_dir(self::testFile)))
            @unlink(codecept_data_dir(self::testFile));

        fwrite(STDOUT, "\nPost Test Cleanup Completed\n");
    }

    /**
     * Tests if the StashAPI constructor produces a valid constructor with given inputs
     * @return STASHAPI
     * @throws \Exception
     */
    public function testAPIValidConstructor()
    {
        $api = new STASHAPI(true);
        $this->assertInstanceOf(STASHAPI::class, $api);
        $api->setId($this->apiid);
        $api->setPw($this->apipw);
        $this->assertEquals("STASHAPI Object - Version: 1.0 ID: " . $this->apiid, $api->__toString());    // API_ID not set
        $this->assertEquals("1.0", $api->getVersion());
        $this->assertEquals($this->apiid, $api->getId());
        $this->assertEquals($this->apipw, $api->getPw());
        return $api;
    }

    /**
     * @depends testAPIValidConstructor
     * @param STASHAPI $apiIn
     * @throws \Exception
     */
    public function testAPIListAll($apiIn)
    {
        $apiIn->url = $this->baseUrl . "api2/file/listall";
        $apiIn->params = array("folderId" => $this->folderId, "outputType" => 1);
        $response = $apiIn->sendRequest();
        $this->assertContains("testapifunctional.txt", $response);
    }

    /**
     * Tests getting folder list containing root folder
     * @depends testAPIValidConstructor
     * @param STASHAPI $apiIn
     * @throws \Exception
     */
    public function testAPIListFoldersRoot($apiIn)
    {
        $apiIn->url = $this->baseUrl . "api2/file/listfolders";
        $apiIn->params = array("folderId" => 0, "outputType" => 1);
        $response = $apiIn->sendRequest();
        $this->assertContains("My Home", $response);
    }

    /**
     * Tests getting folder list containing all folders
     * @depends testAPIValidConstructor
     * @param STASHAPI $apiIn
     * @throws \Exception
     */
    public function testAPIListFoldersAll($apiIn)
    {
        $apiIn->url = $this->baseUrl . "api2/file/listfolders";
        $apiIn->params = array("folderId" => -1, "outputType" => 1);
        $response = $apiIn->sendRequest();
        $this->assertContains("My Home", $response);
        $this->assertContains("Documents", $response);
    }

    /**
     * Tests getting folder list containing sub folders
     * @depends testAPIValidConstructor
     * @param STASHAPI $apiIn
     * @throws \Exception
     */
    public function testAPIListFoldersSub($apiIn)
    {
        $apiIn->url = $this->baseUrl . "api2/file/listfolders";
        $apiIn->params = array("folderId" => $this->folderId, "outputType" => 1);
        $response = $apiIn->sendRequest();
        $this->assertContains("Pictures", $response);
        $this->assertContains("Documents", $response);
    }

    /**
     * Tests the read() / getFile() function
     * @throws \Exception
     */
    public function testAPIFileReadByFileFolderNames()
    {
        $api = new STASHAPI(false);
        $api->url = $this->baseUrl . "api2/file/read";
        $api->setId($this->apiid);
        $api->setPw($this->apipw);
        $api->params = array('fileKey' => $api->encryptString($this->accountPw, true), 'fileName' => 'testapifunctional.txt', 'folderNames' => ["My Home"]);
        $response = $api->sendRequest();
        $this->assertContains("test file", $response);
    }

    /**
     * @throws Exception
     */
    public function testAPIFileSendByFolderId()
    {
        $destFileName = "test.txt";

        $api = new STASHAPI(false);
        $api->url = $this->baseUrl . "api2/file/write";
        $api->setId($this->apiid);
        $api->setPw($this->apipw);

        // Create temp file to test upload/write
        file_put_contents(codecept_data_dir(self::testFile), "This is a test file for STASHAPITest Unit Testing\n\r\n\rTest File");
        $api->params = array('fileKey' => $api->encryptString($this->accountPw, true), 'destFileName' => $destFileName, 'destFolderId' => $this->folderId);
        $response = $api->sendFileRequest(codecept_data_dir(self::testFile));

        $this->assertContains("OK", $response);
        $this->assertContains("200", $response);
        $this->assertContains("fileAliasId", $response);

        $tVal = json_decode($response, true);
        $fileAliasId = $tVal['fileAliasId'];

        $api->url = $this->baseUrl . "api2/file/listfiles";
        $api->params = array("folderNames" => ["My Home"], "outputType" => 1);
        $response = $api->sendRequest();
        $this->assertContains($destFileName, $response);

        $api->url = $this->baseUrl . "api2/file/delete";
        $api->params = array('fileId' => $fileAliasId);
        $response = $api->sendRequest();
        $this->assertContains("OK", $response);
        $this->assertContains("200", $response);
    }

    /**
     * Tests the API sendRequest with folder names
     * @throws Exception
     */
    public function testAPIFileSendByFolderNames()
    {
        $destFileName = "test.txt";

        $api = new STASHAPI(false);
        $api->url = $this->baseUrl . "api2/file/write";
        $api->setId($this->apiid);
        $api->setPw($this->apipw);

        // Create temp file to test upload/write
        file_put_contents(codecept_data_dir(self::testFile), "This is a test file for STASHAPITest Unit Testing\n\r\n\rTest File");
        $api->params = array('fileKey' => $api->encryptString($this->accountPw, true), 'destFileName' => $destFileName, 'destFolderNames' => ["My Home", "Documents"]);
        $response = $api->sendFileRequest(codecept_data_dir(self::testFile));

        $this->assertContains("OK", $response);
        $this->assertContains("200", $response);
        $this->assertContains("fileAliasId", $response);

        $tVal = json_decode($response, true);
        $fileAliasId = $tVal['fileAliasId'];

        $api->url = $this->baseUrl . "api2/file/listfiles";
        $api->params = array("folderNames" => ["My Home", "Documents"], "outputType" => 1);
        $response = $api->sendRequest();
        $this->assertContains($destFileName, $response);

        $api->url = $this->baseUrl . "api2/file/delete";
        $api->params = array('fileId' => $fileAliasId);
        $response = $api->sendRequest();
        $this->assertContains("OK", $response);
        $this->assertContains("200", $response);
    }

    public function testPutFile() {

    }

    public function testGetFile() {

    }

    public function testCopyFile() {

    }

    public function testRenameFile() {

    }

    public function testMoveFile() {

    }

    public function testDeleteFile() {

    }

    public function testListAll() {

    }

    public function testListFiles() {

    }

    public function testListSFFiles() {

    }

    public function testListFolders() {

    }

    public function testGetFolderId() {

    }

    public function testCreateDirectory() {

    }

    public function testRenameDirectory() {

    }

    public function testMoveDirectory() {

    }

    public function testCopyDirectory() {

    }

    public function testDeleteDirectory() {

    }

    public function testGetFileInfo() {

    }

    public function testGetFolderInfo() {

    }

    public function testGetSyncInfo() {

    }

    public function testGetVaultInfo() {

    }

    public function testCheckCreds() {

    }

    public function testCheckVaultConnection() {

    }

    public function testIsValidUser() {

    }

    public function testSetPermissions() {

    }

    public function testCheckPermissions() {

    }

}

