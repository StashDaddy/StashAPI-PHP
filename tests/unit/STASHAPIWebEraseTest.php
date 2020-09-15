<?php

namespace Stash;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Stash\StashAPI as STASHAPI;
use \Exception as Exception;
use UnitTester;

/**
 * Class STASHAPIWebEraseTest
 * Runs scenario based testing for the WebErase functionality
 * @package Stash
 */
class STASHAPIWebEraseTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;
    const testFile = "tmpfile_stashapiweberasetest.txt";        // Test file to use for uploads/write - will be deleted upon completion of all tests (see tearDownAfterClass())
    const outFile = "tmpfile_stashapiweberasetest.out.txt";     // Test file to use for downloads/read - will be deleted upon completion of all tests (see tearDownAfterClass())
    const outFile2 = "tmpfile_stashapiweberasetest.out2.txt";   // Test file  to use for downloads/read - will be deleted upon completion of all tests (see tearDownAfterClass())
    const API_CONTEXT_WEBERASE = 1;
    const API_CONTEXT_VAULTUSER = 2;
    const API_CONTEXT_NOPERMUSER = 3;
    const API_CONTEXT_INVALIDUSER = 4;

    private $apiid;
    private $apipw;
    private $baseUrl;
    private $accountId;
    private $accountUsername;
    private $accountPw;
    private $rootFolderPath;
    private $rootFolderId;
    private $weProjectId;
    private $weFolderId;
    private $vaultApiId;
    private $vaultApiPw;
    private $vaultUsername;
    private $vaultPassword;
    private $vaultUserId;
    private $noPermApiId;
    private $noPermApiPw;
    private $noPermUsername;
    private $noPermPassword;
    private $noPermUserId;
    private $invalidApiId;
    private $invalidApiPw;
    private $invalidUsername;
    private $invalidPassword;
    private $invalidUserId;

    /**
     * This function is run before each individual test
     */
    protected function _before()
    {
        $configArray = parse_ini_file(codecept_data_dir("webEraseCreds.ini"));
        $this->apiid = $configArray['apiid'];
        $this->apipw = $configArray['apipw'];
        $this->baseUrl = $configArray['baseurl'];
        $this->accountId = $configArray['userid'];
        $this->accountUsername = $configArray['username'];
        $this->accountPw = $configArray['filekey'];
        $this->rootFolderId = $configArray['folderid'];
        $this->rootFolderPath = $configArray['folderpath'];
        $this->weProjectId = $configArray['projectid'];
        $this->weFolderId = $configArray['projectfolderid'];
        $this->vaultApiId = $configArray['vaultapiid'];
        $this->vaultApiPw = $configArray['vaultapipw'];
        $this->vaultUsername = $configArray['vaultusername'];
        $this->vaultUserId = $configArray['vaultuserid'];
        $this->vaultPassword = $configArray['vaultfilekey'];

        $this->noPermApiId = $configArray['notauthapiid'];
        $this->noPermApiPw = $configArray['notauthapipw'];
        $this->noPermUsername = $configArray['notauthusername'];
        $this->noPermPassword = $configArray['notauthfilekey'];
        $this->noPermUserId = $configArray['notauthuserid'];
        $this->invalidApiId = $configArray['invalidapiid'];
        $this->invalidApiPw = $configArray['invalidapipw'];
        $this->invalidUsername = $configArray['invalidusername'];
        $this->invalidPassword = $configArray['invalidfilekey'];
        $this->invalidUserId = $configArray['invaliduserid'];

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
    public static function setUpBeforeClass(): void
    {
        define("CURL_IGNORE_SSL_ERRORS", true);

        if (!file_exists(codecept_data_dir("webEraseCreds.ini"))) {
            throw new Exception("Required file: webEraseCreds.ini missing from _data directory");
        }
    }

    /**
     * This function is run once, after all tests in the suite are run
     */
    public static function tearDownAfterClass(): void
    {
        if (file_exists(codecept_data_dir(self::testFile)))
            @unlink(codecept_data_dir(self::testFile));

        if (file_exists(codecept_data_dir(self::outFile)))
            @unlink(codecept_data_dir(self::outFile));

        fwrite(STDOUT, "Post Test Cleanup Completed" . PHP_EOL);
    }

    /**
     * Helper function to set the API context prior to an API call
     * @param STASHAPI $apiIn the API object to set context for
     * @param integer $contextIn one of the API_CONTEXT_X constants
     * @throws InvalidArgumentException for invalid $contextIn values
     * @throws Exception for invalid settings to $apiIn
     */
    public function _setAPIContext($apiIn, $contextIn) {
        if ($contextIn == self::API_CONTEXT_WEBERASE) {
            $apiIn->setId($this->apiid);
            $apiIn->setPw($this->apipw);
        } else if ($contextIn == self::API_CONTEXT_VAULTUSER) {
            $apiIn->setId($this->vaultApiId);
            $apiIn->setPw($this->vaultApiPw);
        } else if ($contextIn == self::API_CONTEXT_NOPERMUSER) {
            $apiIn->setId($this->noPermApiId);
            $apiIn->setPw($this->noPermApiPw);
        } else if ($contextIn == self::API_CONTEXT_INVALIDUSER) {
            $apiIn->setId($this->invalidApiId);
            $apiIn->setPw($this->invalidApiPw);
        } else {
            throw new InvalidArgumentException("Invalid Context");
        }
    }

    /**
     * Function builds and sends an API request with changeable timestamp and signatures
     * @note DO NOT USE outside of unit testing - this is only for testing invalid timestamp and signature handling
     * @param STASHAPI $api the API object for generating valid values/parameters
     * @param integer $timeStampIn the timestamp to use for this request, otherwise 0 to use valid timestamp
     * @param string $signatureIn the 32 character signature to use for this request, otherwise empty string
     * @throws Exception for invalid URLs
     * @return String, the result from the curl operation
     */
    public function _sendRequest($api, $timeStampIn, $signatureIn)
    {
        if ($api->getVerbosity()) echo "- sendRequest -\n\r";

        if ($api->url == "") throw new Exception("Invalid URL");

        $ch = curl_init($api->url);
        $apiParams['url'] = $api->url;
        $apiParams['api_version'] = $api->getVersion();
        $apiParams['api_id'] = $api->getApiId();
        if ($timeStampIn == 0) {
            $api->setTimestamp();
            $apiParams['api_timestamp'] = $api->getTimestamp();
        } else {
            $apiParams['api_timestamp'] = $timeStampIn;
        }

        // Sign request
        if (isset($api->params) && is_array($api->params) && count($api->params) > 0) {
            if ($signatureIn == "") {
                $api->setSignature(array_merge($apiParams, $api->params));
            } else {
                $api->api_signature = $signatureIn;
            }
        } else {
            if ($signatureIn == "") {
                $api->setSignature($apiParams);
            } else {
                $api->api_signature = $signatureIn;
            }
        }
        $apiParams['api_signature'] = $api->getSignature();

        if (isset($api->params) && is_array($api->params) && count($api->params) > 0) {
            $payload = json_encode(array_merge($apiParams, $api->params));
        } else {
            $payload = json_encode($apiParams);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        // Define the CURL_IGNORE_SSL_ERRORS constant if you want to skip SSL verification (not recommended)
        if (defined("CURL_IGNORE_SSL_ERRORS") && CURL_IGNORE_SSL_ERRORS) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // Skip verifying peer certificate/name
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);        // Skip verifying host certificate/name
        }

        # Return response instead of printing.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        # Send request.
        $result = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($api->getVerbosity()) echo "- sendRequest Complete - Result: " . $result . " Error: " . $err . "\n\r";
        if ($result === false) {
            $result = json_encode(['code' => 500, 'message' => $err]);
        } else if ($code != 200) {
            $result = json_encode(['code' => $code, 'message' => $result]);
        }

        return $result;
    }

    /**
     * Tests if the StashAPI constructor produces a valid constructor with given inputs
     * @return STASHAPI
     * @throws Exception
     */
    public function testAPIValidConstructor()
    {
        $api = new STASHAPI("", "", $this->baseUrl, false);
        $this->assertInstanceOf(STASHAPI::class, $api);
        $this->_setAPIContext($api, self::API_CONTEXT_WEBERASE);
        $this->assertEquals("STASHAPI Object - Version: 1.0 ID: " . $this->apiid, $api->__toString());    // API_ID not set
        $this->assertEquals("1.0", $api->getVersion());
        $this->assertEquals($this->apiid, $api->getId());
        $this->assertEquals($this->apipw, $api->getPw());
        return $api;
    }

    /**
     * Tests if a token can successfully be requested by a tokenuser
     * @return string the token for weberase requests
     * @throws Exception
     */
    public function testWEToken()
    {
        $api = $this->testAPIValidConstructor();

        $src = array('null' => 'not needed');     // Empty source identifier
        $response = $api->webEraseToken($src, $retCode, $token);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(!empty($token));
        $this->assertTrue(is_array($response));
        $this->assertEquals("200", $response['code']);
        $this->assertEquals("OK", $response['message']);
        $this->assertTrue(!empty($response['token']));

        fwrite(STDOUT, "Token: " . $token . PHP_EOL);
        return $token;
    }

    /**
     * Tests if a token can successfully be requested by a Vault user
     * @return string the token for weberase requests with Vault User
     * @throws Exception
     */
    public function testWETokenVaultUser()
    {
        $api = $this->testAPIValidConstructor();
        $this->_setAPIContext($api, self::API_CONTEXT_VAULTUSER);

        $src = array('null' => 'not needed');     // Empty source identifier
        $response = $api->webEraseToken($src, $retCode, $token);
        $this->assertEquals("200", $retCode);
        $this->assertTrue(!empty($token));
        $this->assertTrue(is_array($response));
        $this->assertEquals("200", $response['code']);
        $this->assertEquals("OK", $response['message']);
        $this->assertTrue(!empty($response['token']));

        fwrite(STDOUT, "Token: " . $token . PHP_EOL);
        return $token;
    }

    /**
     * Tests if a token requested by User without Permissions fails
     * @return void
     * @throws Exception
     */
    public function testWETokenNoPermUser()
    {
        $api = $this->testAPIValidConstructor();
        $this->_setAPIContext($api, self::API_CONTEXT_NOPERMUSER);

        $src = array('null' => 'not needed');     // Empty source identifier
        $response = $api->webEraseToken($src, $retCode, $token);
        $this->assertEquals("403", $retCode);
        $this->assertTrue(empty($token));
        $this->assertTrue(is_array($response));
        $this->assertEquals("403", $response['code']);
        $this->assertEquals("Forbidden", $response['message']);
        $this->assertTrue(empty($response['token']));
        $this->assertEquals("403", $response['error']['errorCode']);
        $this->assertStringContainsString('You do not have permission', $response['error']['extendedErrorMessage']);
    }

    /**
     * Tests if a token requested by an Invalid set of User API Credentials
     * @return void
     * @throws Exception
     */
    public function testWETokenInvalidUser()
    {
        $api = $this->testAPIValidConstructor();
        $this->_setAPIContext($api, self::API_CONTEXT_INVALIDUSER);

        $src = array('null' => 'not needed');     // Empty source identifier
        $response = $api->webEraseToken($src, $retCode, $token);
        $this->assertEquals("401", $retCode);
        $this->assertTrue(empty($token));
        $this->assertTrue(is_array($response));
        $this->assertEquals("401", $response['code']);
        $this->assertEquals("Unauthorized", $response['message']);
        $this->assertTrue(empty($response['token']));
        $this->assertEquals("401", $response['error']['errorCode']);
        $this->assertStringContainsString('Invalid ID or Request Signature', $response['error']['extendedErrorMessage']);
    }

    /**
     * Tests if a token requested by an Invalid set of User API Credentials
     * @return void
     * @throws Exception
     */
    public function testWETokenInvalidSignature()
    {
        $api = $this->testAPIValidConstructor();
        $src = array('null' => 'not needed');     // Empty source identifier
        $api->params = $src;
        $api->url = $api->BASE_API_URL . "api2/weberase/token";
        $res = $this->_sendRequest($api, 0, "123456789012345678901234deadbeef");
        $response = json_decode($res, true);
        $this->assertTrue(is_array($response));
        $this->assertEquals("401", $response['code']);
        $this->assertEquals("Unauthorized", $response['message']);
        $this->assertTrue(empty($response['token']));
        $this->assertEquals("401", $response['error']['errorCode']);
        $this->assertStringContainsString('Invalid Message Authentication Signature', $response['error']['extendedErrorMessage']);
    }

    /**
     * Tests if a token requested by an Invalid set of User API Credentials
     * @return void
     * @throws Exception
     */
    public function testWETokenInvalidTimestamp()
    {
        $api = $this->testAPIValidConstructor();
        $src = array('null' => 'not needed');     // Empty source identifier
        $api->params = $src;
        $api->url = $api->BASE_API_URL . "api2/weberase/token";
        $res = $this->_sendRequest($api, time() - 100000, "");
        $response = json_decode($res, true);
        $this->assertTrue(is_array($response));
        $this->assertEquals("400", $response['code']);
        $this->assertEquals("Bad Request", $response['message']);
        $this->assertTrue(empty($response['token']));
        $this->assertEquals("400", $response['error']['errorCode']);
        $this->assertStringContainsString('Invalid or Timestamp Exceeded', $response['error']['extendedErrorMessage']);
    }

}

