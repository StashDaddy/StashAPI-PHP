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
    const outFile = "tmpfile_stashapitest.out.txt";     // Test file to use for downloads/read - will be deleted upon completion of all tests (see tearDownAfterClass())
    private $apiid;
    private $apipw;
    private $baseUrl;
    private $accountId;
    private $accountUsername;
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
        $this->accountId = $configArray['userid'];
        $this->accountUsername = $configArray['username'];
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
        define("CURL_IGNORE_SSL_ERRORS", true);

        if (!file_exists(codecept_data_dir("creds.ini"))) {
            throw new Exception("Required file: creds.ini missing from _data directory");
        }
    }

    /**
     * This function is run once, after all tests in the suite are run
     */
    public static function tearDownAfterClass()
    {
        if (file_exists(codecept_data_dir(self::testFile)))
            @unlink(codecept_data_dir(self::testFile));

        if (file_exists(codecept_data_dir(self::outFile)))
            @unlink(codecept_data_dir(self::outFile));

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
        $this->assertContains("Test Small Single Slice File", $response);
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

    /**
     * Tests the putFile() function
     * @param boolean $cleanup if T, will run the cleanup commands to remove the file uploaded to the vault and source file in the file system
     * @throws Exception
     */
    public function testPutFile($cleanup = true) {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);

        // Delete the test file if it exists
        $src = array('folderNames'=>array("My Home", "Documents"), 'fileName' => basename(self::testFile));
        $api->deleteFile($src, $retCode);

        file_put_contents(codecept_data_dir(self::testFile), "This is a test file for putFile()");
        $src = array('fileKey'=>$api->encryptString($this->accountPw,true), 'destFolderNames'=>array("My Home", "Documents"));
        $retCode = 0; $fileId = 0; $fileAliasId = 0;
        $res = $api->putFile(codecept_data_dir(self::testFile), $src, $retCode, $fileId, $fileAliasId);

        $this->assertEquals("200", $retCode);
        $this->assertTrue($fileId > 0);
        $this->assertTrue($fileAliasId > 0);
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['fileId']));
        $this->assertTrue(isset($res['fileAliasId']));
        $this->assertEquals($fileId, $res['fileId']);
        $this->assertEquals($fileAliasId, $res['fileAliasId']);

        // Cleanup
        if ($cleanup) {
            unlink(codecept_data_dir(self::testFile));
            $retCode = 0;
            $src = array('fileId' => $fileAliasId);
            $res = $api->deleteFile($src, $retCode);

            $this->assertEquals("200", $retCode);
            $this->assertTrue(is_array($res));
        }
    }

    /**
     * Tests the getFile() function
     * @throws Exception
     */
    public function testGetFile() {
        $this->testPutFile(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('fileKey'=>$api->encryptString($this->accountPw,true), 'folderNames'=>array("My Home", "Documents"), 'fileName' => self::testFile);
        $res = $api->getFile($src, codecept_data_dir(self::outFile), $retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertEquals(md5_file(codecept_data_dir(self::testFile)), md5_file(codecept_data_dir(self::outFile)));

        // Cleanup
        unlink(codecept_data_dir(self::testFile));
        unlink(codecept_data_dir(self::outFile));

        $retCode = 0;
        $src = array('fileName' => self::testFile, 'folderNames' => array("My Home", "Documents"));
        $res = $api->deleteFile($src, $retCode);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
    }

    /**
     * Tests the copyFile() function
     * @throws Exception
     */
    public function testCopyFile() {
        $this->testPutFile(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);

        // Delete destination file
        $src = array('folderNames' => array("My Home", "Documents"), 'fileName' => 'copyOfFile.txt');
        $retCode = 0;
        $api->deleteFile($src, $retCode);
        $this->assertEquals("200", $retCode);

        // Copy File
        $src = array('folderNames'=>array("My Home", "Documents"), 'fileName' => self::testFile);
        $dst = array('destFolderNames'=>array("My Home", "Documents"), 'destFileName' => "copyOfFile.txt");
        $retCode = 0; $fileAliasId = 0;
        $res = $api->copyFile($src, $dst,$retCode, $fileAliasId);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['fileAliasId']));
        $this->assertEquals($fileAliasId, $res['fileAliasId']);

        //$src = array('fileKey'=>$api->encryptString($this->accountPw,true), 'folderNames'=>array("My Home", "Documents"), 'fileName' => "copyOfFile.txt");
        $src = array('fileKey'=>$api->encryptString($this->accountPw,true), 'fileId'=>$fileAliasId);
        $res = $api->getFile($src, codecept_data_dir(self::outFile), $retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertEquals(md5_file(codecept_data_dir(self::testFile)), md5_file(codecept_data_dir(self::outFile)));

        // Cleanup
        unlink(codecept_data_dir(self::testFile));
        unlink(codecept_data_dir(self::outFile));

        $retCode = 0;
        $src = array('fileName' => self::testFile, 'folderNames' => array("My Home", "Documents"));
        $res = $api->deleteFile($src, $retCode);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));

        $retCode = 0;
        $src = array('fileName' => "copyOfFile.txt", 'folderNames' => array("My Home", "Documents"));
        $res = $api->deleteFile($src, $retCode);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
    }

    /**
     * Tests the renameFile() function
     * @throws Exception
     */
    public function testRenameFile() {
        $this->testPutFile(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);

        // Delete destination file
        $src = array('folderNames' => array("My Home", "Documents"), 'fileName' => 'renamedFile.txt');
        $api->deleteFile($src, $retCode);

        // Rename File
        $src = array('folderNames'=>array("My Home", "Documents"), 'fileName' => self::testFile);
        $dst = array('destFileName' => "renamedFile.txt");
        $retCode = 0;
        $res = $api->renameFile($src, $dst,$retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['fileAliasId']));

        $fileAliasId = $res['fileAliasId'];
        $src = array('fileKey'=>$api->encryptString($this->accountPw,true), 'fileId' => $fileAliasId);
        $res = $api->getFile($src, codecept_data_dir(self::outFile), $retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertEquals(md5_file(codecept_data_dir(self::testFile)), md5_file(codecept_data_dir(self::outFile)));

        // Cleanup
        unlink(codecept_data_dir(self::testFile));
        unlink(codecept_data_dir(self::outFile));

        $retCode = 0;
        $src = array('fileName' => "renamedFile.txt", 'folderNames' => array("My Home", "Documents"));
        $res = $api->deleteFile($src, $retCode);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
    }

    /**
     * Tests the moveFile() function
     * @throws Exception
     */
    public function testMoveFile() {
        $this->testPutFile(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);

        // Delete destination file
        $src = array('folderNames' => array("My Home"), 'fileName' => 'movedFile.txt');
        $api->deleteFile($src, $retCode);

        // Move File
        $src = array('folderNames'=>array("My Home", "Documents"), 'fileName' => self::testFile);
        $dst = array('destFolderNames' => array("My Home"), 'destFileName' => "movedFile.txt");
        $retCode = 0;
        $res = $api->moveFile($src, $dst,$retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['fileAliasId']));

        $fileAliasId = $res['fileAliasId'];
        $src = array('fileKey'=>$api->encryptString($this->accountPw,true), 'fileId' => $fileAliasId);
        $res = $api->getFile($src, codecept_data_dir(self::outFile), $retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertEquals(md5_file(codecept_data_dir(self::testFile)), md5_file(codecept_data_dir(self::outFile)));

        // Cleanup
        unlink(codecept_data_dir(self::testFile));
        unlink(codecept_data_dir(self::outFile));

        $retCode = 0;
        $src = array('fileName' => "movedFile.txt", 'folderNames' => array("My Home"));
        $res = $api->deleteFile($src, $retCode);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
    }

    /**
     * Tests the delete file function (overlaps with testPutFile)
     * @throws Exception
     */
    public function testDeleteFile() {
        // Overlap with testPutFile()
        $this->testPutFile(true);
    }

    /**
     * Tests the listAll() function
     * @throws Exception
     */
    public function testListAll() {
        $this->testPutFile(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames'=>array("My Home", "Documents"),'outputType' => 0);
        $res = $api->listAll($src, $retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['all']));
        $this->assertTrue(is_array($res['all']));
        $this->assertTrue(count($res['all']) > 0);
        $this->assertTrue(isset($res['all']['text']));
        $this->assertTrue(isset($res['all']['data']));

        // Check directory data properties
        $this->assertTrue(count($res['all']['data']) > 0);
        $this->assertTrue(isset($res['all']['data']['bytes']));
        $this->assertEquals("0", $res['all']['data']['bytes']);
        $this->assertTrue(isset($res['all']['data']['size']));
        $this->assertEquals("0B", $res['all']['data']['size']);
        $this->assertTrue(isset($res['all']['data']['type']));
        $this->assertTrue(isset($res['all']['data']['date']));
        $this->assertTrue(isset($res['all']['data']['by']));
        $this->assertTrue(isset($res['all']['data']['parent_id']));
        $this->assertTrue(isset($res['all']['data']['numChildren']));
        $this->assertTrue(isset($res['all']['id']));
        $this->assertTrue(isset($res['all']['state']));

        // Check directory state properties
        $this->assertTrue(is_array($res['all']['state']));
        $this->assertTrue(count($res['all']['state']) > 0);
        $this->assertTrue(isset($res['all']['state']['opened']));

        $this->assertTrue(isset($res['all']['icon']));
        $this->assertTrue(isset($res['all']['children']));
        $this->assertTrue(is_array($res['all']['children']));
        $this->assertTrue(count($res['all']['children']) > 0);
        $this->assertTrue(isset($res['all']['children'][0]));

        $model = $res['all']['children'][0];
        $this->assertTrue(isset($model['text']));
        $this->assertEquals(self::testFile, ($model['text']));
        $this->assertTrue(isset($model['data']));
        $this->assertTrue(isset($model['id']));
        $this->assertTrue(isset($model['state']));
        $this->assertTrue(isset($model['icon']));

        // Check file data properties
        $this->assertTrue(is_array($model['data']));
        $this->assertTrue(count($model['data']) > 0);
        $this->assertTrue(isset($model['data']['bytes']));
        $this->assertTrue(isset($model['data']['size']));
        $this->assertTrue(isset($model['data']['type']));
        $this->assertTrue(isset($model['data']['date']));
        $this->assertTrue(isset($model['data']['by']));
        $this->assertTrue(isset($model['data']['parent_id']));
        $this->assertTrue(isset($model['data']['numChildren']));
        $this->assertTrue(isset($model['data']['filetype']));

        // Check file state properties
        $this->assertTrue(is_array($model['state']));
        $this->assertTrue(count($model['state']) > 0);
        $this->assertTrue(isset($model['state']['opened']));
    }

    /**
     * Tests the listFiles() function
     * @throws Exception
     */
    public function testListFiles() {
        $this->testPutFile(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames'=>array("My Home", "Documents"),'outputType' => 0);
        $res = $api->listFiles($src, $retCode, $fileNames);

        // Check outputType = 0 (no output)
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($fileNames));
        $this->assertTrue(count($fileNames) > 0);
        $this->assertTrue(in_array("No Output Requested", $fileNames));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['files']));
        $this->assertTrue(is_array($res['files']));
        $this->assertTrue(count($res['files']) > 0);
        $this->assertEquals("No Output Requested", $res['files'][0]);

        // Check outputType = 1 (file names only)
        $src = array('folderNames'=>array("My Home", "Documents"),'outputType' => 1);
        $res = $api->listFiles($src, $retCode, $fileNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($fileNames));
        $this->assertTrue(count($fileNames) > 0);
        $this->assertTrue(in_array(self::testFile, $fileNames));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['files']));
        $this->assertTrue(is_array($res['files']));
        $this->assertTrue(count($res['files']) > 0);
        $this->assertTrue(in_array(self::testFile, $res['files']));

        // Check outputType = 2 (file and path as arrays)
        $src = array('folderNames'=>array("My Home", "Documents"),'outputType' => 2);
        $res = $api->listFiles($src, $retCode, $fileNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($fileNames));
        $this->assertTrue(count($fileNames) > 0);
        $found = false;
        foreach ($res['files'] as $file) {
            if ($file[0] == "My Home" && $file[1] == "Documents" && $file[2] == self::testFile) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['files']));
        $this->assertTrue(is_array($res['files']));
        $this->assertTrue(count($res['files']) > 0);
        $found = false;
        foreach ($res['files'] as $file) {
            if ($file[0] == "My Home" && $file[1] == "Documents" && $file[2] == self::testFile) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        // Check outputType = 3 (file and path as string)
        $src = array('folderNames'=>array("My Home", "Documents"),'outputType' => 3);
        $res = $api->listFiles($src, $retCode, $fileNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($fileNames));
        $this->assertTrue(count($fileNames) > 0);
        $this->assertTrue(in_array("My Home/Documents/" . self::testFile, $res['files']));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['files']));
        $this->assertTrue(is_array($res['files']));
        $this->assertTrue(count($res['files']) > 0);
        $this->assertTrue(in_array("My Home/Documents/" . self::testFile, $res['files']));

        // Check outputType = 4 (model JSON)
        $src = array('folderNames'=>array("My Home", "Documents"),'outputType' => 4);
        $res = $api->listFiles($src, $retCode, $fileNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($fileNames));
        $this->assertTrue(count($fileNames) > 0);
        $this->assertTrue(in_array(self::testFile, $fileNames));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['files']));
        $this->assertTrue(is_array($res['files']));
        $this->assertTrue(count($res['files']) > 0);
        $found = false;
        foreach ($res['files'] as $model) {
            $this->assertTrue(isset($model['name']));
            $this->assertTrue(isset($model['date']));
            $this->assertTrue(isset($model['type']));
            $this->assertTrue(isset($model['size']));
            $this->assertTrue(isset($model['id']));
            $this->assertTrue(isset($model['thumbnail']));
            $this->assertTrue(isset($model['downloadUrl']));
            $this->assertTrue(isset($model['documentMimeType']));
            $this->assertTrue(isset($model['isDocument']));
            $this->assertTrue(isset($model['isVideo']));
            $this->assertTrue(isset($model['tags']));
            $this->assertTrue(isset($model['is_dashed']));
            if ($model['name'] == self::testFile) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        // Check outputType = 5 (GhostFiles format)
        $src = array('folderNames'=>array("My Home", "Documents"),'outputType' => 5);
        $res = $api->listFiles($src, $retCode, $fileNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($fileNames));
        $this->assertTrue(count($fileNames) > 0);
        $this->assertTrue(in_array(self::testFile, $fileNames));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['files']));
        $this->assertTrue(is_array($res['files']));
        $this->assertTrue(count($res['files']) > 0);
        $found = false;
        foreach ($res['files'] as $model) {
            $this->assertTrue(isset($model['name']));
            $this->assertTrue(isset($model['date']));
            $this->assertTrue(isset($model['size']));
            $this->assertTrue(isset($model['fileId']));
            if ($model['name'] == self::testFile) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        // Cleanup
        unlink(codecept_data_dir(self::testFile));
        $retCode = 0;
        $src = array('folderNames' => array("My Home", "Documents"), 'fileName' => self::testFile);
        $res = $api->deleteFile($src, $retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));

    }

    /**
     * Tests the listSFFiles() function
     */
    public function testListSFFiles() {
        // ToDo need to create a smart folder first, then run listSFFiles
    }

    /**
     * Tests the listFolders() function
     * @throws Exception
     */
    public function testListFolders() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames'=>array("My Home"),'outputType' => 0);
        $res = $api->listFolders($src, $retCode, $folderNames);

        // Check outputType = 0 (no output)
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($folderNames));
        $this->assertTrue(count($folderNames) > 0);
        $this->assertTrue(in_array("No Output Requested", $folderNames));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['folders']));
        $this->assertTrue(is_array($res['folders']));
        $this->assertTrue(count($res['folders']) > 0);
        $this->assertEquals("No Output Requested", $res['folders'][0]);

        // Check outputType = 1 (folder names only)
        $src = array('folderNames'=>array("My Home"),'outputType' => 1);
        $res = $api->listFolders($src, $retCode, $folderNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($folderNames));
        $this->assertTrue(count($folderNames) > 0);
        $this->assertTrue(in_array("Documents", $folderNames));
        $this->assertTrue(in_array("Pictures", $folderNames));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['folders']));
        $this->assertTrue(is_array($res['folders']));
        $this->assertTrue(count($res['folders']) > 0);
        $this->assertTrue(in_array("Documents", $res['folders']));
        $this->assertTrue(in_array("Pictures", $res['folders']));

        // Check outputType = 2 (folder and path as arrays)
        $src = array('folderNames'=>array("My Home"),'outputType' => 2);
        $res = $api->listFolders($src, $retCode, $folderNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($folderNames));
        $this->assertTrue(count($folderNames) > 0);
        $found = false;
        foreach ($res['folders'] as $folder) {
            if ($folder[0] == "My Home" && $folder[1] == "Documents" && (!isset($folder[2]))) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['folders']));
        $this->assertTrue(is_array($res['folders']));
        $this->assertTrue(count($res['folders']) > 0);
        $found = false;
        foreach ($res['folders'] as $folder) {
            if ($folder[0] == "My Home" && $folder[1] == "Documents" && (! isset($folder[2]))) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        // Check outputType = 3 (file and path as string)
        $src = array('folderNames'=>array("My Home"),'outputType' => 3);
        $res = $api->listFolders($src, $retCode, $folderNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($folderNames));
        $this->assertTrue(count($folderNames) > 0);
        $this->assertTrue(in_array("My Home/Documents", $res['folders']));
        $this->assertTrue(in_array("My Home/Pictures", $res['folders']));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['folders']));
        $this->assertTrue(is_array($res['folders']));
        $this->assertTrue(count($res['folders']) > 0);
        $this->assertTrue(in_array("My Home/Documents", $res['folders']));
        $this->assertTrue(in_array("My Home/Pictures", $res['folders']));

        // Check outputType = 4 (model JSON)
        $src = array('folderNames'=>array("My Home"),'outputType' => 4);
        $res = $api->listFolders($src, $retCode, $folderNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($folderNames));
        $this->assertTrue(count($folderNames) > 0);
        $this->assertTrue(in_array("Documents", $folderNames));
        $this->assertTrue(in_array("Pictures", $folderNames));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['folders']));
        $this->assertTrue(is_array($res['folders']));
        $this->assertTrue(count($res['folders']) > 0);
        $found = false;
        foreach ($res['folders'] as $model) {
            $this->assertTrue(isset($model['text']));
            $this->assertTrue(isset($model['qtip']));
            $this->assertTrue(isset($model['qtitle']));
            $this->assertTrue(isset($model['allowDrag']));
            $this->assertTrue(isset($model['allowDrop']));
            $this->assertTrue(isset($model['id']));
            $this->assertTrue(isset($model['isRootFolder']));
            $this->assertTrue(isset($model['hidden']));
            $this->assertTrue(isset($model['cls']));
            $this->assertTrue(isset($model['leaf']));
            $this->assertTrue(isset($model['expanded']));
            $this->assertTrue(isset($model['permission']));
            if ($model['text'] == "Documents") {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        // Check outputType = 5 (GhostFiles format)
        $src = array('folderNames'=>array("My Home"),'outputType' => 5);
        $res = $api->listFolders($src, $retCode, $folderNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($folderNames));
        $this->assertTrue(count($folderNames) > 0);
        $this->assertTrue(in_array("Documents", $folderNames));
        $this->assertTrue(in_array("Pictures", $folderNames));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['folders']));
        $this->assertTrue(is_array($res['folders']));
        $this->assertTrue(count($res['folders']) > 0);
        $found = false;
        foreach ($res['folders'] as $model) {
            $this->assertTrue(isset($model['text']));
            $this->assertTrue(isset($model['date']));
            $this->assertTrue(isset($model['folderId']));
            if ($model['text'] == "Documents") {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        // Check outputType = 6 (NodeNameId format)
        $src = array('folderNames'=>array("My Home"),'outputType' => 6);
        $res = $api->listFolders($src, $retCode, $folderNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($folderNames));
        $this->assertTrue(count($folderNames) > 0);
        $this->assertTrue(in_array("Documents", $folderNames));
        $this->assertTrue(in_array("Pictures", $folderNames));
        $this->assertTrue(is_array($res));
        $this->assertTrue(isset($res['code']));
        $this->assertTrue(isset($res['message']));
        $this->assertTrue(isset($res['folders']));
        $this->assertTrue(is_array($res['folders']));
        $this->assertTrue(count($res['folders']) > 0);
        $found = false;
        foreach ($res['folders'] as $model) {
            $this->assertTrue(isset($model['id']));
            $this->assertTrue(isset($model['parent']));
            $this->assertTrue(isset($model['text']));
            if ($model['text'] == "Documents") {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * Tests the createDirectory() function
     * @param bool $cleanup T to remove the directory that was created, F to leave it
     * @throws \Exception
     */
    public function testCreateDirectory($cleanup = true) {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames'=>array("My Home", "Created Dir"));
        $api->deleteDirectory($src, $retCode);

        $src = array('folderNames'=>array("My Home", "Created Dir"));
        $api->createDirectory($src, $retCode, $dirId);

        $this->assertEquals("200", $retCode);
        $this->assertTrue($dirId > 0);

        $src = array('folderNames' => array("My Home"), 'outputType' => 1);
        $api->listFolders($src, $retCode, $folderNames);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(in_array("Created Dir", $folderNames));

        $src = array('folderNames' => array("My Home", "Created Dir"));
        $res = $api->getFolderInfo($src, $retCode);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(isset($res['folderInfo']['folderId']));
        $this->assertEquals($dirId, $res['folderInfo']['folderId']);

        if ($cleanup) {
            $src = array('folderNames' => array("My Home", "Created Dir"));
            $api->deleteDirectory($src, $retCode);
            $this->assertEquals("200", $retCode);
        }
    }

    /**
     * Tests the renameDirectory() function
     * @throws Exception
     */
    public function testRenameDirectory() {
        $this->testCreateDirectory(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames' => array("My Home", "Renamed Dir"));
        $api->deleteDirectory($src, $retCode);

        $src = array('folderNames' => array("My Home", "Created Dir"));
        $dst = array('destFolderNames' => array("My Home", "Renamed Dir"));
        $api->renameDirectory($src, $dst, $retCode);
        $this->assertEquals("200", $retCode);

        $src = array('folderNames' => array("My Home"), 'outputType' => 1);
        $api->listFolders($src, $retCode, $folderNames);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(in_array("Renamed Dir", $folderNames));
        $this->assertFalse(in_array("Created Dir", $folderNames));

        // Cleanup
        $src = array('folderNames' => array("My Home", "Renamed Dir"));
        $api->deleteDirectory($src, $retCode);
        $this->assertEquals("200", $retCode);
    }

    /**
     * Tests the moveDirectory() function
     * @throws Exception
     */
    public function testMoveDirectory() {
        $this->testCreateDirectory(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames' => array("My Home", "Documents", "Moved Dir"));
        $api->deleteDirectory($src, $retCode);

        $src = array('folderNames' => array("My Home", "Created Dir"));
        $dst = array('destFolderNames' => array("My Home", "Documents", "Moved Dir"));
        $api->moveDirectory($src, $dst, $retCode);
        $this->assertEquals("200", $retCode);

        $src = array('folderNames' => array("My Home", "Documents"), 'outputType' => 1);
        $api->listFolders($src, $retCode, $folderNames);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(in_array("Moved Dir", $folderNames));
        $this->assertFalse(in_array("Created Dir", $folderNames));

        $src = array('folderNames' => array("My Home"), 'outputType' => 1);
        $api->listFolders($src, $retCode, $folderNames);

        $this->assertEquals("200", $retCode);
        $this->assertFalse(in_array("Moved Dir", $folderNames));
        $this->assertFalse(in_array("Created Dir", $folderNames));

        // Cleanup
        $src = array('folderNames' => array("My Home", "Documents", "Moved Dir"));
        $api->deleteDirectory($src, $retCode);
        $this->assertEquals("200", $retCode);

    }

    /**
     * Tests the copyDirectory() function
     * @throws Exception
     */
    public function testCopyDirectory() {
        $this->testCreateDirectory(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames' => array("My Home", "Documents", "Created Dir"));
        $api->deleteDirectory($src, $retCode);

        $src = array('folderNames' => array("My Home", "Created Dir"));
        $dst = array('destFolderNames' => array("My Home", "Documents"));
        $res = $api->copyDirectory($src, $dst, $retCode, $folderId);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(isset($res['folderId']));
        $this->assertTrue($res['folderId'] > 0);
        $this->assertTrue($folderId > 0);

        $src = array('folderNames' => array("My Home", "Documents"), 'outputType' => 1);
        $api->listFolders($src, $retCode, $folderNames);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(in_array("Created Dir", $folderNames));

        $src = array('folderNames' => array("My Home"), 'outputType' => 1);
        $api->listFolders($src, $retCode, $folderNames);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(in_array("Created Dir", $folderNames));

        $src = array('folderId' => $folderId);
        $res = $api->getFolderInfo($src, $retCode);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(isset($res['folderInfo']));
        $this->assertTrue(isset($res['folderInfo']['folderId']));
        $this->assertTrue(isset($res['folderInfo']['dirName']));
        $this->assertEquals("Created Dir", $res['folderInfo']['dirName']);
        $this->assertEquals($folderId, $res['folderInfo']['folderId']);

        // Cleanup
        $src = array('folderNames' => array("My Home", "Documents", "Created Dir"));
        $api->deleteDirectory($src, $retCode);
        $this->assertEquals("200", $retCode);

    }

    /**
     * Tests the deleteDirectory() function
     * Overlaps with testCreateDirectory, @see testCreateDirectory
     * @throws Exception
     */
    public function testDeleteDirectory() {
        // Overlaps with testCreateDirectory()
        $this->testCreateDirectory(true);
    }

    /**
     * Tests the getFolderId() function
     * @throws Exception
     */
    public function testGetFolderId() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames'=>array("My Home"));
        $res = $api->getFolderId($src);

        $this->assertTrue(isset($res['code']));
        $this->assertEquals("200", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("OK", $res['message']);
        $this->assertTrue(isset($res['folderId']));
        $this->assertEquals("66844", $res['folderId']);
    }

    /**
     * Tests the getFileInfo() function
     * @throws Exception
     */
    public function testGetFileInfo() {
        $this->testPutFile(false);

        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames'=>array("My Home", "Documents"), 'fileName' => self::testFile);
        $retCode = 0;
        $res = $api->getFileInfo($src, $retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(isset($res['code']));
        $this->assertEquals("200", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("OK", $res['message']);
        $this->assertTrue(isset($res['fileInfo']));
        $this->assertTrue(isset($res['fileInfo']['fileAliasId']));
        $this->assertTrue(isset($res['fileInfo']['fileName']));
        $this->assertEquals(self::testFile, $res['fileInfo']['fileName']);
        $this->assertTrue(isset($res['fileInfo']['fileSize']));
        $this->assertTrue($res['fileInfo']['fileSize'] > 0);
        $this->assertTrue(isset($res['fileInfo']['fileTimestamp']));
        $this->assertTrue($res['fileInfo']['fileTimestamp'] > 0);

        // Cleanup
        unlink(codecept_data_dir(self::testFile));

        $retCode = 0;
        $src = array('fileName' => self::testFile, 'folderNames' => array("My Home","Documents"));
        $res = $api->deleteFile($src, $retCode);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(is_array($res));
    }

    /**
     * Tests the getFolderInfo() function
     * @throws Exception
     */
    public function testGetFolderInfo() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames'=>array("My Home", "Documents"));
        $retCode = 0;
        $res = $api->getFolderInfo($src, $retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(isset($res['code']));
        $this->assertEquals("200", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("OK", $res['message']);
        $this->assertTrue(isset($res['folderInfo']));
        $this->assertTrue(isset($res['folderInfo']['folderId']));
        $this->assertTrue($res['folderInfo']['folderId'] > 0);
        $this->assertTrue(isset($res['folderInfo']['dirName']));
        $this->assertEquals("Documents", $res['folderInfo']['dirName']);
        $this->assertTrue(isset($res['folderInfo']['dirTimestamp']));
        $this->assertTrue($res['folderInfo']['dirTimestamp'] > 0);
        $this->assertTrue(isset($res['folderInfo']['parentId']));
        $this->assertTrue(isset($res['folderInfo']['isRoot']));
        $this->assertTrue(isset($res['folderInfo']['numSubDirs']));
        $this->assertTrue(isset($res['folderInfo']['subDirs']));
        $this->assertTrue(is_array($res['folderInfo']['subDirs']));
        $this->assertTrue(isset($res['folderInfo']['numFiles']));
        $this->assertTrue(isset($res['folderInfo']['files']));
        $this->assertTrue(is_array($res['folderInfo']['files']));
    }

    /**
     * Tests getSyncInfo() function
     * @throws Exception
     */
    public function testGetSyncInfo() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $src = array('folderNames'=>array("My Home", "Documents"));
        $res = $api->getSyncInfo($src);

        $this->assertTrue(isset($res['id']));
        $this->assertTrue(isset($res['dirName']));
        $this->assertEquals("Documents", $res['dirName']);
        $this->assertTrue(isset($res['dirTimestamp']));
        $this->assertTrue($res['dirTimestamp'] > 0);
        $this->assertTrue(isset($res['parentId']));
        $this->assertTrue($res['parentId'] > 0);
        $this->assertTrue(isset($res['isRoot']));
        $this->assertEquals(0, $res['isRoot']);
        $this->assertTrue(isset($res['fileSize']));
        $this->assertEquals(0, $res['fileSize']);
        $this->assertTrue(isset($res['elements']));
        $this->assertTrue(is_array($res['elements']));
        $this->assertTrue(isset($res['numElements']));
        $this->assertEquals($res['numElements'], count($res['elements']));
    }

    /**
     * Tests the getVaultInfo() function
     * @throws Exception
     */
    public function testGetVaultInfo() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $retCode = 0;
        $res = $api->getVaultInfo($retCode);

        $this->assertEquals("200", $retCode);
        $this->assertTrue(isset($res['code']));
        $this->assertEquals("200", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("OK", $res['message']);
        $this->assertTrue(isset($res['vaultInfo']));
        $this->assertTrue(isset($res['vaultInfo']['numUsers']));
        $this->assertTrue(isset($res['vaultInfo']['numFiles']));
        $this->assertTrue($res['vaultInfo']['numFiles'] > 0);
        $this->assertTrue(isset($res['vaultInfo']['numDirs']));
        $this->assertTrue($res['vaultInfo']['numDirs'] > 0);
        $this->assertTrue(isset($res['vaultInfo']['strBaseDir']));
        $this->assertEquals("My Home", $res['vaultInfo']['strBaseDir']);
        $this->assertTrue(isset($res['vaultInfo']['numBytesTotal']));
        $this->assertTrue($res['vaultInfo']['numBytesTotal'] > 0);
        $this->assertTrue(isset($res['vaultInfo']['numBytesInUse']));
        $this->assertTrue($res['vaultInfo']['numBytesInUse'] > 0);
        $this->assertTrue(isset($res['vaultInfo']['numBytesFree']));
        $this->assertTrue($res['vaultInfo']['numBytesFree'] > 0);
    }

    /**
     * Tests the checkCreds() function
     * @throws Exception
     */
    public function testCheckCreds() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);

        $src = array("fileKey" => $api->encryptString($this->accountPw . "BADPW", true), "accountUsername" => $this->accountUsername);
        $res = $api->checkCreds($src);
        $this->assertTrue(isset($res['code']));
        $this->assertEquals("401", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("Unauthorized", $res['message']);

        $src = array("fileKey" => $api->encryptString($this->accountPw, true), "accountUsername" => $this->accountUsername);
        $res = $api->checkCreds($src);
        $this->assertTrue(isset($res['code']));
        $this->assertEquals("200", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("OK", $res['message']);
    }

    /**
     * Tests the checkVaultConnection() function
     * @throws Exception
     */
    public function testCheckVaultConnection() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);
        $res = $api->checkVaultConnection($retCode, $errMsg);

        $this->assertEquals(200, $retCode);
        $this->assertEquals("", $errMsg);
        $this->assertTrue($res);
    }

    /**
     * Tests the isValidUser() function
     * @throws Exception
     */
    public function testIsValidUser() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);

        $src = array("accountUsername" => $this->accountUsername . "_AvailableUsername");
        $res = $api->isValidUser($src);
        $this->assertTrue(isset($res['code']));
        $this->assertEquals("200", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("OK", $res['message']);

        $src = array("accountUsername" => $this->accountUsername);
        $res = $api->isValidUser($src);
        $this->assertTrue(isset($res['code']));
        $this->assertEquals("400", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("Bad Request", $res['message']);
    }

    /**
     * Tests the setPermissions() function
     * @throws Exception
     */
    public function testSetPermissions() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);

        // Get Folder ID for Documents Directory
        $src = array('folderNames' => array("My Home", "Documents"));
        $res = $api->getFolderId($src);
        $this->assertEquals("200", $res['code']);
        $this->assertTrue(isset($res['folderId']));
        $this->assertTrue($res['folderId'] > 0);
        $docFolderId = $res['folderId'];

        // Build permission array for user 92 (which doesn't exist)
        $permissionArray = array('folderId' => $docFolderId, 'requestorId' => $this->accountId, 'perms' => array(array('userGroupId' => 92, 'userGroupIdType' => 1, 'permVal' => 3)));
        $src = array('permJson' => json_encode($permissionArray));
        $res = $api->setPermissions($src, $retCode, $resultIds);
        $this->assertEquals("400", $retCode);

        $this->assertTrue(isset($res['code']));
        $this->assertEquals("400", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("Bad Request", $res['message']);

        // Extend function to test with Pro account here

    }

    /**
     * Tests the checkPermissions() function
     * @throws Exception
     */
    public function testCheckPermissions() {
        $api = new STASHAPI($this->apiid, $this->apipw, $this->baseUrl, false);

        // Get Folder ID for Documents Directory
        $src = array('folderNames' => array("My Home", "Documents"));
        $res = $api->getFolderId($src);
        $this->assertEquals("200", $res['code']);
        $this->assertTrue(isset($res['folderId']));
        $this->assertTrue($res['folderId'] > 0);
        $docFolderId = $res['folderId'];

        $src = array('objectUserId' => $this->accountId, 'objectId' => $docFolderId, 'objectIdType' => 2, 'requestedAccess' => 3);
        $res = $api->checkPermissions($src, $retCode, $checkResult);

        $this->assertEquals("200", $retCode);
        $this->assertTrue($checkResult);
        $this->assertTrue(isset($res['code']));
        $this->assertEquals("200", $res['code']);
        $this->assertTrue(isset($res['message']));
        $this->assertEquals("OK", $res['message']);
        $this->assertTrue(isset($res['result']));
        $this->assertTrue($res['result']);

    }
}

