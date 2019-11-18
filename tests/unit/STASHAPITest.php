<?php

namespace Stash;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Stash\StashAPI as STASHAPI;
use \Exception as Exception;
use UnexpectedValueException;
use UnitTester;

/**
 * Class STASHAPITest
 * Runs unit testing on the functions in the API
 * @package Stash
 */

class STASHAPITest extends Unit
{

    /**
     * @var UnitTester
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
     * @throws Exception
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
    {
        if (file_exists(codecept_data_dir(self::testFile)))
            @unlink(codecept_data_dir(self::testFile));

        fwrite(STDOUT, PHP_EOL . "Post Test Cleanup Completed" . PHP_EOL);
    }

    /**
     * Checks the main constructor
     * @return STASHAPI
     * @throws Exception
     */
    public function testAPIValidConstructor()
    {
        $api = new STASHAPI(true);
        $this->assertInstanceOf(STASHAPI::class, $api);

        $api->setId($this->apiid);
        $api->setPw($this->apipw);

        $this->assertEquals("STASHAPI Object - Version: 1.0 ID: " . $api->getId(), $api->__toString());
        $this->assertEquals("1.0", $api->getVersion());

        $this->assertEquals($this->apiid, $api->getId());
        $this->assertEquals($this->apipw, $api->getPw());

        $api->url = $this->baseUrl . "api2/file/listfiles";

        unset($configArray);
        return $api;
    }

    /**
     * @param STASHAPI $api the API object to test
     * @depends testAPIValidConstructor
     */
    public function testAPIParamsIsValid($api)
    {
        $testArray = array(
            'api_id' => '12345678901234567890123456789012',        // valid API id
            'api_pw' => 'abcdefabcdefabcdefabcdefabcdefab',        // valid API pw
            'api_signature' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',    // valid signature
            'api_timestamp' => 1000,                // Valid timestamp
            'api_version' => "1.0",                    // Valid version
            'verbosity' => true,                    // Valid verbosity
            'url' => $this->baseUrl . 'api2/file/listfiles',    // Valid URL
            'params' => array('Test Value' => "OK"),            // Valid params
        );
        $this->assertTrue($api->isValid($testArray));

        // Invalid API id
        $testArray2 = ['api_id' => '1A57*^#!--1234d987987u2348y7asf8'];
        $this->assertFalse($api->isValid($testArray2));

        // Invalid API PW
        $testArray3 = ['api_pw' => '123487aabbccddeeffgg&#(@&4000000'];
        $this->assertFalse($api->isValid($testArray3));

        // Invalid Signatures
        $testArray4 = ['api_signature' => 0];
        $this->assertFalse($api->isValid($testArray4));
        $testArray4 = ['api_signature' => 'abcdef1234567890'];
        $this->assertFalse($api->isValid($testArray4));
        $testArray4 = ['api_signature' => '&#8321340LKJSFKLJHWEIUHSK&#@*@#1234'];
        $this->assertFalse($api->isValid($testArray4));

        // Invalid Timestamps
        $testArray5 = ['api_timestamp' => '12345'];
        $this->assertFalse($api->isValid($testArray5));
        $testArray5 = ['api_timestamp' => -1];
        $this->assertFalse($api->isValid($testArray5));
        $testArray5 = ['api_timestamp' => 0];
        $this->assertFalse($api->isValid($testArray5));
        $testArray5 = ['api_timestamp' => 'Test String'];
        $this->assertFalse($api->isValid($testArray5));

        // Invalid Versions
        $testArray6 = ['api_version' => 0];
        $this->assertFalse($api->isValid($testArray6));
        $testArray6 = ['api_version' => "2.0"];
        $this->assertFalse($api->isValid($testArray6));

        // Invalid verbosity
        $testArray7 = ['verbosity' => 'Test String'];
        $this->assertFalse($api->isValid($testArray7));

        // Invalid urls
        $testArray8 = ['url' => 0];
        $this->assertFalse($api->isValid($testArray8));
        $testArray8 = ['url' => 'http://www.stashbusiness.com/api2/file'];      // Explicit Invalid URL, no https
        $this->assertFalse($api->isValid($testArray8));
        $testArray8 = ['url' => 'www.stashbusiness.com/api2'];      // Explicit Invalid URL, no https://
        $this->assertFalse($api->isValid($testArray8));

        // Invalid Params
        $testArray9 = ['params' => 0];
        $this->assertFalse($api->isValid($testArray9));
        $testArray9 = ['params' => 'Test String'];
        $this->assertFalse($api->isValid($testArray9));
    }

    /**
     * @param STASHAPI $api the API object to test
     * @depends testAPIValidConstructor
     */
    public function testAPIParamValidation($api)
    {
        $api->params = array('fileKey' => 'testkey', 'fileId' => '1', 'fileName' => 'testfile.txt', 'folderId' => '1', 'folderNames' => array("Dir1", "Dir2"),
            'destFileName' => 'destname.txt', 'destFolderId' => 1, 'destFolderNames' => array("DestDir1", "DestDir2"), 'outputType' => 1);

        $this->assertTrue($api->validateParams('read'));
        $this->assertTrue($api->validateParams('write'));
        $this->assertTrue($api->validateParams('copy'));
        $this->assertTrue($api->validateParams('move'));
        $this->assertTrue($api->validateParams('delete'));
        $this->assertTrue($api->validateParams('rename'));
        $this->assertTrue($api->validateParams('listfiles'));
        $this->assertTrue($api->validateParams('none'));
        $this->assertFalse($api->validateParams('listfilesdir'));       // listfilesdir is not a support op

        // Test invalid filekey input
        $api->params = array('fileId' => '1', 'fileName' => 'testfile.txt', 'folderId' => '1', 'folderNames' => array("Dir1", "Dir2"),
            'destFileName' => 'destname.txt', 'destFolderId' => 1, 'destFolderNames' => array("DestDir1", "DestDir2"));
        $this->assertFalse($api->validateParams('read'));

        // Test remaining invalid inputs with move or copy as both operations consider all source and destination parameters
        // Missing All Source parameters
        $api->params = array('destFileName' => 'destname.txt', 'destFolderId' => 1, 'destFolderNames' => array("DestDir1", "DestDir2"));
        $this->assertFalse($api->validateParams('copy'));

        // Missing All Destination parameters
        $api->params = array('fileId' => '1', 'fileName' => 'testfile.txt', 'folderId' => '1', 'folderNames' => array("Dir1", "Dir2"));
        $this->assertFalse($api->validateParams('copy'));

        // Missing Source File params
        $api->params = array('folderId' => '1', 'folderNames' => array("Dir1", "Dir2"),
            'destFileName' => 'destname.txt', 'destFolderId' => 1, 'destFolderNames' => array("DestDir1", "DestDir2"));
        $this->assertFalse($api->validateParams('copy'));

        // Missing Source Folder params with FileId
        $api->params = array('fileId' => '1', 'fileName' => 'testfile.txt',
            'destFileName' => 'destname.txt', 'destFolderId' => 1, 'destFolderNames' => array("DestDir1", "DestDir2"));
        $this->assertTrue($api->validateParams('copy'));

        // Missing Source Folder params without FileId
        $api->params = array('fileName' => 'testfile.txt',
            'destFileName' => 'destname.txt', 'destFolderId' => 1, 'destFolderNames' => array("DestDir1", "DestDir2"));
        $this->assertFalse($api->validateParams('copy'));

        // Missing Dest File params
        $api->params = array('fileKey' => 'testkey', 'fileId' => '1', 'fileName' => 'testfile.txt', 'folderId' => '1', 'folderNames' => array("Dir1", "Dir2"),
            'destFolderId' => 1, 'destFolderNames' => array("DestDir1", "DestDir2"));
        $this->assertFalse($api->validateParams('copy'));

        // Missing Dest Folder params
        $api->params = array('fileKey' => 'testkey', 'fileId' => '1', 'fileName' => 'testfile.txt', 'folderId' => '1', 'folderNames' => array("Dir1", "Dir2"),
            'destFileName' => 'destname.txt');
        $this->assertFalse($api->validateParams('copy'));
    }

    /**
     * Tests calculating and setting a valid signature for the request
     * @param STASHAPI $api the API object to test
     * @throws Exception
     * @depends testAPIValidConstructor
     */
    public function testAPISignature($api)
    {
        $api->params = array("destFolderNames" => ["My Home"]);
        $apiParams['url'] = $api->url;
        $apiParams['api_version'] = $api->getVersion();
        $apiParams['api_id'] = $api->getId();
        $api->setTimestamp();
        $apiParams['api_timestamp'] = $api->getTimestamp();
        $this->assertTrue($api->setSignature(array_merge($apiParams, $api->params)));
        $this->assertNotRegExp('/[^abcdef0-9]/i', $api->getSignature());
    }

    /**
     * Tests encrypting / decrypting strings
     * @param STASHAPI $apiIn
     * @depends testAPIValidConstructor
     */
    public function testEncryptDecryptString($apiIn)
    {
        $testString = "testpw!";
        $enc = $apiIn->encryptString($testString, true);
        $dec = $apiIn->decryptString($enc, true);
        $this->assertEquals($testString, $dec);
    }

    /**
     * Tests encrypting a string with an empty key
     * @throws Exception
     */
    public function testEncryptEmptyKey()
    {
        $api = new STASHAPI(false);
        $api->setPw("");
        $api->setId("AAAAAAAAAAAAAAAAAAAABBBBBBBBBBBB");

        $this->assertEquals("", $api->encryptString("testpw!", true));
    }

    /**
     * Tests encrypting an empty string
     * @throws Exception
     */
    public function testEncryptEmptyString()
    {
        $api = new STASHAPI(false);
        $api->setPw("AAAAAAAAAAAAAAAAAAAABBBBBBBBBBBB");
        $api->setId("AAAAAAAAAAAAAAAAAAAABBBBBBBBBBBB");

        $this->assertEquals("", $api->encryptString("", true));
    }

    /**
     * Test decrypting with an empty key
     * @throws Exception
     */
    public function testDecryptEmptyKey()
    {
        $api = new STASHAPI(false);
        $api->setPw("AAAAAAAAAAAAAAAAAAAABBBBBBBBBBBB");
        $api->setId("AAAAAAAAAAAAAAAAAAAABBBBBBBBBBBB");

        $ct = $api->encryptString("testpw!", true);
        $api->setPw("");
        $this->assertEquals("", $api->decryptString($ct, true));
    }

    /**
     * Tests decrypting an empty string
     * @throws Exception
     */
    public function testDecryptEmptyString()
    {
        $api = new STASHAPI(false);
        $api->setPw("AAAAAAAAAAAAAAAAAAAABBBBBBBBBBBB");
        $api->setId("AAAAAAAAAAAAAAAAAAAABBBBBBBBBBBB");

        $this->assertEquals("", $api->decryptString("", true));
    }

    /**
     * @param STASHAPI $apiIn the API object to test
     * @depends testAPIValidConstructor
     * @expectedException InvalidArgumentException
     */
    public function testEncryptInvalidKey($apiIn)
    {
        $pw = "testpw!";
        $apiIn->setPw("1234567890");
        $this->assertEquals("1234567890", $apiIn->getPw());
        $apiIn->encryptString($pw, true);
    }

    /**
     * @param STASHAPI $apiIn
     * @throws Exception
     * @depends testAPIValidConstructor
     * @expectedException InvalidArgumentException
     */
    public function testDecryptInvalidKey($apiIn)
    {
        $testString = "testpw!";
        $apiIn->setPw($this->apipw);
        $apiIn->encryptString($testString, true);
        $apiIn->setPw("1234567890");
        $this->assertEquals("1234567890", $apiIn->getPw());
        $apiIn->decryptString($testString, true);
    }

    /**
     * @param STASHAPI $apiIn
     * @throws Exception
     * @depends testAPIValidConstructor
     * @expectedException UnexpectedValueException
     */
    public function testDecryptInvalidDataIn($apiIn)
    {
        $testString = "testpw!";
        $apiIn->setPw($this->apipw);
        $this->assertEquals($this->apipw, $apiIn->getPw());
        $enc = $apiIn->encryptString($testString, true);
        $apiIn->decryptString(substr($enc, 0, 10), true);    // Only ask to decrypt 10 characters of data to force detection of too little data in input
    }

    /**
     * @throws Exception
     */
    public function testAPISend()
    {
        $api = new STASHAPI(false);
        $api->url = $this->baseUrl . "api2/file/listfiles";
        $api->setId($this->apiid);
        $api->setPw($this->apipw);

        $api->params = array("folderNames" => ["My Home"]);
        $api->params['outputType'] = 1;

        $response = $api->sendRequest();
        $this->assertContains("testapifunctional.txt", $response);
    }

    /**
     * @throws Exception
     * @expectedException UnexpectedValueException
     */
    public function testAPIFileSendEmptyURL()
    {
        $api = new STASHAPI(false);
        $api->url = "";
        $api->setId($this->apiid);
        $api->setPw($this->apipw);
        $api->params = array();
        $api->sendFileRequest("blah");
    }

    /**
     * @throws Exception
     * @expectedException InvalidArgumentException
     */
    public function testAPIFileSendEmptyFilename()
    {
        $api = new STASHAPI(false);
        $api->url = $this->baseUrl . "api2/file/write";
        $api->setId($this->apiid);
        $api->setPw($this->apipw);
        $api->params = array();
        $api->sendFileRequest("");
    }

    /**
     * @throws Exception
     * @expectedException InvalidArgumentException
     */
    public function testAPIFileSendFileNotExist()
    {
        $api = new STASHAPI(false);
        $api->url = $this->baseUrl . "api2/file/write";
        $api->setId($this->apiid);
        $api->setPw($this->apipw);
        $api->params = array();
        $api->sendFileRequest("/tmp/aaaaaaaaaaaaaaaaa.stashapitest.txt");
    }

    /*
    // ToDo

        public function testAPIFileSendBadSignature() {
            $this->assertTrue(false);
        }

    */

}

