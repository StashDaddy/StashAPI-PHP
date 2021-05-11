<?php
/** @noinspection PhpUnused - suppress IDE inspection for unused elements */

/**
 * STASH API - PHP Implementation
 *
 * A wrapper to interact with the STASH API from within PHP
 *
 * @property string $api_id - the API ID for your account
 * @property string $api_pw - the API PW for your account
 * @property string $api_signature - the sha256 hmac signature of the request
 * @property string $api_version - the version of the API used for the request
 * @property integer $api_timestamp - the epoch timestamp for when the request was generated
 * @property boolean $verbosity - T to print status messages using echo, F to suppress them
 * @property string $url - the URL of the API endpoint to send the request to
 * @property array $params - the parameters to pass to the API request (e.g. folderId, folderNames, etc)
 * @property string $BASE_API_URL - the base URL to use for the request
 * @see https://www.stashbusiness.com/helpcenter/users for the API reference
 */

namespace Stash;

use CURLFile;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use UnexpectedValueException;

class StashAPI
{
    const STASHAPI_VERSION = "1.0";        // API Version
    const STASHAPI_ID_LENGTH = 32;        // API_ID string length
    const STASHAPI_PW_LENGTH = 32;        // API_PW string length (minimum)
    const STASHAPI_SIG_LENGTH = 32;        // API_SIGNATURE string length (minimum)
    const STASHAPI_FILE_BUFFER_SIZE = 1024;    // File buffer read/write size
    const BASE_VAULT_FOLDER = "My Home";    // THis is prepended or removed from Vault paths as needed
    const BASE_URL = "https://www.stashbusiness.com/";    // This is the URL to send requests to, can override with BASE_API_URL in the constructor
    const ENC_ALG = 'aes-256-cbc';        // Encryption algorithm for use in encryptString & decryptString()

    private $api_id;            // The API_ID for your account
    private $api_pw;            // The API_PW for your account
    public $api_signature;            // The sha256 hmac signature for the request
    public $api_version;            // The API version which generated the request
    private $api_timestamp;            // The timestamp for when the request was generated
    private $verbosity;            // Set to T to generate echo logging message

    public $url;                // The URL to send the request to
    public $params;                // Associative array of parameters to send with the request

    public $BASE_API_URL;        // The BASE URL to use for the request

    /**
     * STASHAPI Constructor
     *
     * @param String $apiId the API ID
     * @param String $apiPw the API PW
     * @param string $urlIn the base url (e.g. 127.0.0.1, or https://www.stashbusiness.com) if different from the default
     * @param boolean $verbosity T to generate log messages from STASHAPI functions
     */
    public function __construct($apiId = "", $apiPw = "", $urlIn = "", $verbosity = false)
    {
        $this->api_version = self::STASHAPI_VERSION;
        $this->verbosity = $verbosity;
        if ($apiId != "") {
            $this->api_id = $apiId;
        }
        if ($apiPw != "") {
            $this->api_pw = $apiPw;
        }
        $this->BASE_API_URL = (!empty($urlIn) ? $urlIn : self::BASE_URL);
        if (substr_compare($this->BASE_API_URL, "/", -1, 1) !== 0) {
            $this->BASE_API_URL .= "/";
        }
    }

    /**
     * Returns the constants in the class
     * @return array|NULL
     * @throws ReflectionException if failed to get constants
     */
    public static function getConstants()
    {
        $oClass = new ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }

    /**
     * Returns a string representation of this object consisting of the API version
     *
     * @return string
     */
    public function __toString()
    {
        return "STASHAPI Object - Version: " . $this->api_version . " ID: " . $this->api_id;
    }

    /**
     * Returns the version for this API
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->api_version;
    }

    /**
     * Gets the verbosity setting for the STASHAPI instance
     * @return bool T if verbosity enabled and set in constructor, otherwise F
     */
    public function getVerbosity()
    {
        return $this->verbosity;
    }

    /**
     * Gets the API ID for the STASHAPI instance
     * @return string the API ID
     */
    public function getApiId()
    {
        return $this->api_id;
    }

    /**
     * Checks the parameter and value for sanity and compliance with rules
     *
     * @param array $dataIn the parameter name => value
     * @return boolean T if all the parameters are deemed valid
     */
    public static function isValid(array $dataIn)
    {
        try {
            foreach ($dataIn as $idx => $val) {
                if ($idx === "api_id") {
                    if (strpos($val, "@") === false) {
                        // Must be valid api_id (32 chars, a-f, 0-9 (hex chars only)
                        if (mb_strlen($val) != self::STASHAPI_ID_LENGTH) {
                            throw new Exception($idx . " Must Be " . self::STASHAPI_ID_LENGTH . " Characters in Length");
                        }
                        if (preg_match('/[^abcdef0-9]/i', $val)) {
                            throw new Exception($idx . " Has Invalid Characters, only a-f and 0-9 are allowed");
                        }
                    } else {
                        // Must be valid email address
                        if (! filter_var($val, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception($idx . " is Not a Valid Email Address");
                        }
                    }
//                    if (mb_strlen($val) != self::STASHAPI_ID_LENGTH && strpos($val, "@") === false)
//                        throw new Exception($idx . " Must Be " . self::STASHAPI_ID_LENGTH . " Characters in Length");
//                    if (preg_match('/[^abcdef0-9]/i', $val))
//                        throw new Exception($idx . " Has Invalid Characters, only a-f and 0-9 are allowed");
                } elseif ($idx === "api_pw") {                    // A-Z, a-z, and 0-9 characters only
                    if (mb_strlen($val) < self::STASHAPI_PW_LENGTH)
                        throw new Exception($idx . " Must Be at Least " . self::STASHAPI_PW_LENGTH . " Characters in Length");
                    if (preg_match('/[^a-zA-Z0-9]/i', $val))
                        throw new Exception($idx . " Has Invalid Characters, only a-z, A-Z, and 0-9 are allowed");
                } elseif ($idx === "api_signature") {
                    if (mb_strlen($val) < self::STASHAPI_SIG_LENGTH)
                        throw new Exception($idx . " Must Be at Least " . self::STASHAPI_SIG_LENGTH . " Characters in Length");
                    if (preg_match('/[^abcdef0-9]/i', $val))
                        throw new Exception($idx . " Has Invalid Characters, only a-f and 0-9 are allowed");
                } elseif ($idx === "api_timestamp") {
                    if (!is_int($val))
                        throw new Exception($idx . " Must be an Integer Value");
                    if ($val < 1)
                        throw new Exception($idx . " Must be Greater Than 0");
                } elseif ($idx === "api_version") {
                    if ($val != self::STASHAPI_VERSION)
                        throw new Exception($idx . " Does Not Match API Version for this Code");
                } elseif ($idx === "verbosity") {
                    if (is_bool($val) === false)
                        throw new Exception($idx . " Must be a Boolean Value");
                } elseif ($idx === "url") {
                    if (filter_var($val, FILTER_VALIDATE_URL) === false)
                        throw new Exception($idx . " Must be a Valid URL - including https");
                    if (mb_substr($val, 0, 5) != "https")
                        throw new Exception($idx . " Must start with HTTPS");
                } elseif ($idx === "params") {
                    if (!is_array($val))
                        throw new Exception($idx . " Must be an Array");
                }
            }
            $retVal = true;
        } catch (Exception $e) {
            echo "Invalid Parameter, Reason: " . $e->getMessage() . "\n\r";
            $retVal = false;
        }
        return $retVal;
    }

    /**
     * api_id setter
     *
     * @param string $idIn your api_id
     * @return StashAPI
     * @throws Exception if idIn is invalid or does not match syntax requirements
     */
    public function setId($idIn)
    {
        if ($this->verbosity) echo "- setId - " . $idIn . "\n\r";

        if (!StashAPI::isValid(array("api_id" => $idIn))) {
            throw new Exception("Invalid API ID");
        }
        $this->api_id = $idIn;
        return $this;
    }

    /**
     * api_id getter
     * @return String, the API_ID for this instance
     */
    public function getId()
    {
        return $this->api_id;
    }

    /**
     * api_pw setter
     *
     * @param string $pwIn your api_pw
     * @return StashAPI
     */
    public function setPw($pwIn)
    {
        if ($this->verbosity) echo "- setPw - " . $pwIn . "\n\r";
        $this->api_pw = $pwIn;
        return $this;
    }

    /**
     * api_pw getter
     * @return string the API_PW for this instance
     */
    public function getPw()
    {
        return $this->api_pw;
    }

    /**
     * api_timestamp setter
     *
     * @return StashAPI
     */
    public function setTimestamp()
    {
        if ($this->verbosity) echo "- setTimestamp - " . time() . "\n\r";
        $this->api_timestamp = (int)time();
        return $this;
    }

    /**
     * api_timestamp getter
     * @return integer the API_timestamp for this instance
     */
    public function getTimestamp()
    {
        return $this->api_timestamp;
    }

    /**
     * api_signature setter - signs the request with the current data in the STASHAPI request instance
     *
     * Uses the following parameters as part of the sig calculation (these are all contained in the $dataIn array as key=>value pairs)
     * - self::params() array containing the request parameters (could be 0 or more key=>value pairs)
     * - ['url'] contains the URL the request is being sent to
     * - ['api_version'] contains the version string for this API
     * - ['api_id'] contains the ID string for the API user
     * - ['api_timestamp'] contains the epoch / integer timestamp for when the request was created
     * @param array $dataIn - an array of key/value pairs containing the data to sign
     * @return boolean T if the function succeeds
     * @throws Exception for invalid inputs
     */
    public function setSignature(array $dataIn)
    {
        if ($this->verbosity) echo "- setSignature - dataIn: " . print_r($dataIn, true);

        if (!isset($dataIn['url']) || $dataIn['url'] == "") throw new Exception("Input array missing url for signature calculation");
        if (!isset($dataIn['api_version']) || $dataIn['api_version'] == "") throw new Exception("Input array missing api_version for signature calculation");
        if (!isset($dataIn['api_id']) || $dataIn['api_id'] == "") throw new Exception("Input array missing api_id for signature calculation");
        if (!isset($dataIn['api_timestamp']) || $dataIn['api_timestamp'] == "") throw new Exception("Input array missing api_timestamp for signature calculation");
        if (isset($dataIn['api_signature'])) unset($dataIn['api_signature']);

        // Must UNESCAPE the slashes so the json_encode here matches encoded json on other platforms
        $strToSign = json_encode($dataIn, JSON_UNESCAPED_SLASHES);
        //$strToSign = http_build_query($dataIn);

        $sig = hash_hmac('sha256', $strToSign, $this->getPw());

        $this->api_signature = $sig;
        return true;
    }

    /**
     * api_signature getter
     * @return string the API_signature for this instance
     */
    public function getSignature()
    {
        return $this->api_signature;
    }

    /**
     * Function encrypts a string with your API_PW
     * @param string $strIn the string to encrypt
     * @param boolean $returnHexBits T if the function should return hexbits instead of raw
     * @return string the encrypted string
     */
    public function encryptString($strIn, $returnHexBits)
    {
        if ($strIn == "") return "";
        if ($this->api_pw == "") return "";

        if (strlen($this->api_pw) < 32) {
            throw new InvalidArgumentException("API_PW must be at least 32 characters");
        }

        $ivsize = openssl_cipher_iv_length(self::ENC_ALG);
        $iv = openssl_random_pseudo_bytes($ivsize);

        $rawoption = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true;
        $ct = openssl_encrypt($strIn, self::ENC_ALG, substr($this->api_pw, 0, 32), $rawoption, $iv);

        if ($returnHexBits) {
            return bin2hex($iv . $ct);
        } else {
            return $iv . $ct;
        }
    }

    /**
     * Function decrypts a string with your API_PW
     * @param string $strIn the string to decrypt
     * @param boolean $inHexBits T if the input string is hexbits and should be converted back to binary before decryption
     * @return string the decrypted string
     */
    public function decryptString($strIn, $inHexBits)
    {
        if ($strIn == "") return "";
        if ($this->api_pw == "") return "";

        if (strlen($this->api_pw) < 32) {
            throw new InvalidArgumentException("API_PW must be at least 32 characters");
        }

        if ($inHexBits) {
            $strIn = hex2bin($strIn);
        }
        $ivsize = openssl_cipher_iv_length(self::ENC_ALG);
        $rawoption = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true;

        if (strlen($strIn) < $ivsize) {
            throw new UnexpectedValueException("Insufficient Input Data to Decrypt", 400);
        }

        $iv = substr($strIn, 0, $ivsize);
        $ct = substr($strIn, $ivsize, strlen($strIn) - $ivsize);

        return openssl_decrypt($ct, self::ENC_ALG, substr($this->api_pw, 0, 32), $rawoption, $iv);
    }

    /**
     * Function builds and sends an API request
     * @return string the result from the curl operation
     * @throws Exception for invalid URLs
     */
    public function sendRequest()
    {
        if ($this->verbosity) echo "- sendRequest -\n\r";

        if ($this->url == "") throw new Exception("Invalid URL");

        $ch = curl_init($this->url);
        $apiParams['url'] = $this->url;
        $apiParams['api_version'] = $this->getVersion();
        $apiParams['api_id'] = $this->getId();
        $this->setTimestamp();
        $apiParams['api_timestamp'] = $this->getTimestamp();

        // Sign request
        if (isset($this->params) && is_array($this->params) && count($this->params) > 0) {
            $this->setSignature(array_merge($apiParams, $this->params));
        } else {
            $this->setSignature($apiParams);
        }
        $apiParams['api_signature'] = $this->getSignature();

        if (isset($this->params) && is_array($this->params) && count($this->params) > 0) {
            $payload = json_encode(array_merge($apiParams, $this->params));
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
        if ($this->verbosity) echo "- sendRequest Complete - Result: " . $result . " Error: " . $err . "\n\r";
        if ($result === false) {
            $result = json_encode(['code' => 500, 'message' => $err]);
        } else if ($code != 200) {
            $result = json_encode(['code' => $code, 'message' => $result]);
        }

        return $result;
    }

    /**
     * Function builds and sends an API download request
     * The result will be stored in a file, specified by $fileNameIn
     *
     * @param string $fileNameIn the filename to save the downloaded data to
     * @return string the result from the curl operation
     * @throws UnexpectedValueException if $this->url is invalid
     */
    public function sendDownloadRequest($fileNameIn)
    {
        if ($this->verbosity) echo "- sendDownloadRequest -\n\r";
        if ($this->url == "") throw new UnexpectedValueException("Invalid URL");
        $result = null;
        $ch = null;
        $fOut = null;

        try {
            set_time_limit(0);
            $fOut = fopen($fileNameIn, "w+");

            $ch = curl_init($this->url);
            $apiParams['url'] = $this->url;
            $apiParams['api_version'] = $this->getVersion();
            $apiParams['api_id'] = $this->getId();
            $this->setTimestamp();
            $apiParams['api_timestamp'] = $this->getTimestamp();

            // Sign request
            if (isset($this->params) && is_array($this->params) && count($this->params) > 0) {
                $this->setSignature(array_merge($apiParams, $this->params));
            } else {
                $this->setSignature($apiParams);
            }
            $apiParams['api_signature'] = $this->getSignature();

            if (isset($this->params) && is_array($this->params) && count($this->params) > 0) {
                $payload = json_encode(array_merge($apiParams, $this->params));
            } else {
                $payload = json_encode($apiParams);
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt($ch, CURLOPT_FILE, $fOut);
            // Define the CURL_IGNORE_SSL_ERRORS constant if you want to skip SSL verification (not recommended)
            if (defined("CURL_IGNORE_SSL_ERRORS") && CURL_IGNORE_SSL_ERRORS) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // Skip verifying peer certificate/name
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);        // Skip verifying host certificate/name
            }

            // Send request.
            $result = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            fclose($fOut);
            if ($this->verbosity) echo "- sendDownloadRequest Complete - Result: " . $result . " Error: " . $err . "\n\r";

            // Examine output file for error JSON and if found, delete the download and return error
            // If any error occurs during this error check, just keep the file output as it is
            try {
                $fileSize = filesize($fileNameIn);
                $readLen = ($fileSize > 250 ? 250 : $fileSize);
                $buffer = file_get_contents($fileNameIn, false, null, 0, $readLen);
                //$idx = strpos($buffer, chr(0));
                //if ($idx > 1) {
                //    $subBuffer = substr($buffer, 0, $idx);
                $tArr = json_decode($buffer, true);
                if (!is_null($tArr) && is_array($tArr) && isset($tArr['code']) && ($tArr['code'] == "400" || $tArr['code'] == "403" || $tArr['code'] == "404" || $tArr['code'] == "500")) {
                    return $buffer;
                }
                //}
            } catch (Exception $e) {
                // Do nothing, assume the error check failed and its a valid file anyway
            }
            return 1;
        } catch (Exception $e) {
            return $e->getMessage();
        } finally {
            if (is_resource($fOut)) {
                fclose($fOut);
            }
            if (is_resource($ch)) {
                curl_close($ch);
            }
        }
    }

    /**
     * Function builds and sends an API request to upload a file
     *
     * The input parameters for this function must include a FILE
     *
     * @param string $fileNameIn the full path and name of the file to upload
     * @return string the result from the curl operation
     * @throws Exception if setSignature fails
     * @throws UnexpectedValueException if $this->url is invalid
     * @throws InvalidArgumentException if the fileNameIn parameter is empty or the file doesn't exist
     */
    public function sendFileRequest($fileNameIn)
    {
        if ($this->verbosity) echo "- sendFileRequest -\n\r";

        if ($this->url == "") throw new UnexpectedValueException("Invalid URL");
        if ($fileNameIn == "" || (!file_exists($fileNameIn))) throw new InvalidArgumentException("A Filename Must be Specified or File Does Not Exist");

        $apiParams['url'] = $this->url;
        $apiParams['api_version'] = $this->getVersion();
        $apiParams['api_id'] = $this->getId();
        $this->setTimestamp();
        $apiParams['api_timestamp'] = $this->getTimestamp();
        $this->setSignature(array_merge($apiParams, $this->params));        // Sign request
        $apiParams['api_signature'] = $this->getSignature();

        $header = array('Content-Type: multipart/form-data');
        $ch = curl_init($this->url);
        $cFile = new CURLFile($fileNameIn, "application/octet-stream", $fileNameIn);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        // Define the CURL_IGNORE_SSL_ERRORS constant if you want to skip SSL verification (not recommended)
        if (defined("CURL_IGNORE_SSL_ERRORS") && CURL_IGNORE_SSL_ERRORS) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // Skip verifying peer certificate/name
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);        // Skip verifying host certificate/name
        }

        //$fields['file'] = '@' . $fileNameIn;
        $mergedArrays = array_merge($apiParams, $this->params);
        $fields['params'] = json_encode($mergedArrays);
        $fields['file'] = $cFile;

        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        // Send request.
        $result = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($this->verbosity) echo "- sendFileRequest Complete - Result: " . $result . " Error: " . $err . "\n\r";
        if ($result === false) {
            $result = json_encode(['code' => 500, 'message' => $err]);
        } else if ($code != 200) {
            $result = json_encode(['code' => $code, 'message' => $result]);
        }

        return $result;
    }

    /**
     * Function builds and sends an API request to upload a file in chunks
     * @param string $fileNameIn the full path and name of the file to upload
     * @param object $objIn placeholder, not used, for status updates callback
     * @param object $cancelIn placeholder, not used, for transfer cancellation
     * @return string the result from the curl operation
     * @note This function is not implemented and only exists for compatibility with the .Net version of the API
     */
    public function sendFileRequestChunked($fileNameIn, $objIn, $cancelIn)
    {
        unset($objIn);
        unset($cancelIn);
        return $this->sendDownloadRequest($fileNameIn);
    }

    /**
     * Function validates the source identifier parameters
     *
     * Source identifier can contain fileId, fileName, folderNames, folderId
     * To be valid, a fileId OR (fileName AND (folderId OR folderNames)) must be given
     * If folderOnly is T, then fileId and fileName need not be specified
     *
     * @param boolean $folderOnly T if the validation should be for the source folder only
     * @param boolean $allowZeroIds T if the validation should allow for folderId and/or fileId to be zero and/or the folderId to be -1 (all folders)
     * @return boolean T if the parameters are valid
     */
    private function validateSourceParams($folderOnly, $allowZeroIds = false)
    {
        if ($folderOnly) {
            if ($allowZeroIds) {
                if (isset($this->params['folderId']) && (int)$this->params['folderId'] >= -1) return true;
            } else {
                if (isset($this->params['folderId']) && (int)$this->params['folderId'] > 0) return true;
            }
            if (isset($this->params['folderNames']) && is_array($this->params['folderNames']) && count($this->params['folderNames']) > 0) return true;
            throw new InvalidArgumentException("Source Parameters Invalid - folderId or folderNames MUST be specified");
        } else {
            if ($allowZeroIds) {
                if (isset($this->params['fileId']) && (int)$this->params['fileId'] >= 0) return true;
            } else {
                if (isset($this->params['fileId']) && (int)$this->params['fileId'] > 0) return true;
            }
            if (isset($this->params['fileName']) && $this->params['fileName'] != "") {
                if (isset($this->params['folderId']) && (int)$this->params['folderId'] > 0) return true;
                if (isset($this->params['folderNames']) && is_array($this->params['folderNames']) && count($this->params['folderNames']) > 0) return true;
            }
            throw new InvalidArgumentException("Source Parameters Invalid - fileId or fileName plus either folderId or folderNames MUST be specified");
        }
    }

    /**
     * Function validates the destination identifier parameters
     *
     * To be valid, destFileName AND (destFolderId OR destFolderNames) must be given
     * If folderOnly is T, then destFileName need not be given
     * If nameOnly is T, then destFolderId and destFolderNames need not be given (but folderOnly must be false)
     *
     * @param boolean $folderOnly T if the validation should be for the destination folder only
     * @param boolean $nameOnly T if the validate should be for the destination name only, if specified, folderOnly must be false
     * @return boolean T if the parameters are valid
     */
    private function validateDestParams($folderOnly, $nameOnly)
    {
        if ($folderOnly && $nameOnly) {
            throw new InvalidArgumentException("folderOnly and nameOnly cannot both be T");
        }

        if ($folderOnly) {
            if (isset($this->params['destFolderId']) && $this->params['destFolderId'] > 0) return true;
            if (isset($this->params['destFolderNames']) && is_array($this->params['destFolderNames']) && count($this->params['destFolderNames']) > 0) return true;
            throw new InvalidArgumentException("Destination Parameters Invalid - destFolderId or destFolderNames MUST be specified");
        } else {
            if (isset($this->params['destFileName']) && $this->params['destFileName'] != "") {
                if ($nameOnly === true) return true;
                if (isset($this->params['destFolderId']) && $this->params['destFolderId'] > 0) return true;
                if (isset($this->params['destFolderNames']) && is_array($this->params['destFolderNames']) && count($this->params['destFolderNames']) > 0) return true;
            }
            throw new InvalidArgumentException("Destination Parameters Invalid - destFileName plus either destFolderId or destFolderNames MUST be specified");
        }
    }

    /**
     * Function validates the output type parameters
     *
     * Source identifier must contain outputType equal to one of the APIRequest::API_OUTPUT_TYPE_X constants
     * @return boolean T if the parameters are valid
     */
    private function validateOutputParams()
    {
        if (isset($this->params['outputType']) && (int)$this->params['outputType'] >= 0) return true;
        throw new InvalidArgumentException("Source Parameters Invalid - outputType MUST be specified");
    }

    /**
     * Function validates the search parameters
     *
     * Source identifier may contain search and searchPartialMatch values unless $requireTerms is T, then search MUST be specified
     * @param boolean $requireTerms T if the check should error if terms are not specified
     * @return boolean T if the parameters are valid, otherwise exception is thrown
     * @throws InvalidArgumentException if the parameters do not validate
     */
    private function validateSearchParams($requireTerms)
    {
        if ($requireTerms) {
            if (empty($this->params['search'])) {
                throw new InvalidArgumentException("Search Terms Invalid - search parameter MUST be specified");
            }
        }
        return true;
    }

    /**
     * Function validates the version parameter
     * @return boolean T if the parameters are valid
     * @throws InvalidArgumentException if the parameters do not validate
     */
    private function validateVersionParams()
    {
        if (empty($this->params['version'])) {
            throw new InvalidArgumentException("Version Parameter Invalid - version parameter MUST be specified as a number");
        }
        return true;
    }

    /**
     * Function validates the smart folder ID parameter
     *
     * Source identifier must contain sfId
     * @return boolean T if the parameters are valid
     * @throws InvalidArgumentException for errors with invalid sfId parameter
     */
    private function validateSmartFolderId()
    {
        if (empty($this->params['sfId']) || $this->params['sfId'] <= 0) {
            throw new InvalidArgumentException("Invalid SmartFolder ID");
        }
        return true;
    }

    /**
     * Function validates the overwriteFile parameter and corresponding overwriteFileId parameter, which is required if overwriteFile is specified
     * @return boolean T if the parameters are valid
     * @throws InvalidArgumentException for errors with invalid overwriteFile or overwriteFileId parameters
     */
    public function validateOverwriteParams()
    {
        if (!empty($this->params['overwriteFile'])) {
            $overwriteFile = (int)$this->params['overwriteFile'];
            if ($overwriteFile > 1 || $overwriteFile < 0) {
                throw new InvalidArgumentException("Invalid overwriteFile value");
            }
            if ($overwriteFile == 1) {      // overwriteFileId MUST be specified
                if (empty($this->params['overwriteFileId'])) {
                    throw new InvalidArgumentException("overwriteFileId parameter must be specified with overwriteFile");
                } else if ((int)$this->params['overwriteFileId'] < 1) {
                    throw new InvalidArgumentException("Invalid value for overwriteFileId");
                }
            }
        }
        return true;
    }

    /**
     * Function validates the check cred parameters
     *
     * Source identifier can contain fileKey, accountUsername, apiid, apipw
     *
     * @param boolean $checkFileKey T if the validation should check fileKey
     * @param boolean $checkUsername T if the validation should check accountUsername
     * @param boolean $checkApiId T if the validation should check apiid
     * @param boolean $checkApiPw T if the validation should check apipw
     * @return boolean T if the parameters are valid
     */
    private function validateCredParams($checkFileKey, $checkUsername, $checkApiId, $checkApiPw)
    {
        if ($checkFileKey) {
            if (!isset($this->params['fileKey']) || $this->params['fileKey'] == "") {
                throw new InvalidArgumentException("Source Parameters Invalid - fileKey MUST be specified and not blank");
            }
        }

        if ($checkUsername) {
            if (!isset($this->params['accountUsername']) || $this->params['accountUsername'] == "") {
                throw new InvalidArgumentException("Source Parameters Invalid - accountUsername MUST be specified and not blank");
            }
        }

        if ($checkApiId) {
            if (!isset($this->params['apiid']) || $this->params['apiid'] == "") {
                throw new InvalidArgumentException("Source Parameters Invalid - apiid MUST be specified and not blank");
            }
        }

        if ($checkApiPw) {
            if (!isset($this->params['apipw']) || $this->params['apipw'] == "") {
                throw new InvalidArgumentException("Source Parameters Invalid - apipw MUST be specified and not blank");
            }
        }

        return true;
    }

    /**
     * Function validates the set permissions parameters
     *
     * Source identifier must contain permJson
     * @return boolean T if the parameters are valid
     */
    private function validateSetPermParams()
    {
        if (empty($this->params['permJson'])) {
            throw new InvalidArgumentException("Invalid permissions Json parameter");
        }
        return true;
    }

    /**
     * Function validates the check permissions parameters
     *
     * Source identifier must contain objectUserId, objectId, objectIdType, requestedAccess
     * @return boolean T if the parameters are valid
     */
    private function validateCheckPermParams()
    {
        if (empty($this->params['objectUserId']) || (int)$this->params['objectUserId'] < 1) {
            throw new InvalidArgumentException("Invalid objectUserId parameter");
        }
        if (empty($this->params['objectId']) || (int)$this->params['objectId'] < 1) {
            throw new InvalidArgumentException("Invalid objectId parameter");
        }
        if (empty($this->params['objectIdType']) || (int)$this->params['objectIdType'] < 1) {
            throw new InvalidArgumentException("Invalid objectIdType parameter");
        }
        if (empty($this->params['requestedAccess']) || (int)$this->params['requestedAccess'] < 0) {
            throw new InvalidArgumentException("Invalid requestedAccess parameter");
        }

        return true;
    }

    /**
     * Function validates the WebErase parameters
     * Source identifier must contain token_key and fileKey values
     * @param boolean $tokenOnly T if the function should validate only the token_key, otherwise, will also look at fileKey
     * @return boolean T if the parameters are valid
     */
    private function ValidateWebEraseParams($tokenOnly)
    {
        if (empty($this->params['token_key'])) {
            throw new InvalidArgumentException("Invalid token parameter");
        }

        if (!$tokenOnly) {
            if (empty($this->params['fileKey'])) {
                throw new InvalidArgumentException("Invalid fileKey parameter");
            }
        }

        return true;
    }

    /**
     * Function validates the WebEraseStore function parameters
     * Source identifier must contain token_key, fileKey, and destFolderId values
     * @return boolean T if the parameters are valid
     */
    private function ValidateWebEraseStoreParams()
    {
        if (empty($this->params['token_key'])) {
            throw new InvalidArgumentException("Invalid token parameter");
        }

        if (empty($this->params['fileKey'])) {
            throw new InvalidArgumentException("Invalid fileKey parameter");
        }

        if (empty($this->params['destFolderId']) || (int)$this->params['destFolderId'] < 1) {
            throw new InvalidArgumentException("Invalid destFolderId parameter");
        }

        return true;
    }

    /**
     * Function validates the tag(s) parameters
     * @param boolean $singleTag if True, will only for presence of 'tag' parameters, otherwise checks for presence of 'tags' parameter
     */
    public function validateSourceTags($singleTag)
    {
        if ($singleTag) {
            if (empty($this->params['tag'])) {
                throw new InvalidArgumentException("Invalid objectUserId parameter");
            }
        } else {
            if (empty($this->params['tags'])) {
                throw new InvalidArgumentException("Invalid objectUserId parameter");
            }
        }
    }

    /**
     * Function validates the input parameters before they are passed to the API endpoint
     *
     * @param string $opIn the operation to check the parameters for
     * @return boolean T if the params are validated, F otherwise
     * @throws InvalidArgumentException if the fileKey parameter is not included in the request
     */
    public function validateParams($opIn)
    {
        $opIn = mb_strtolower($opIn);
        try {
            if ($this->params == null && $opIn != "none") {
                throw new InvalidArgumentException("Parameters Can't Be Null");
            }

            if ($opIn === 'read') {
                $this->validateSourceParams(false, false);
                if ($this->params['fileKey'] == "") throw new InvalidArgumentException("Invalid fileKey Parameter");
            } elseif ($opIn === 'write') {
                $this->validateDestParams(true, false);
                $this->validateOverwriteParams();
                if ($this->params['fileKey'] == "") throw new InvalidArgumentException("Invalid fileKey Parameter");
            } elseif ($opIn === 'copy') {
                $this->validateSourceParams(false, false);
                $this->validateDestParams(false, false);
            } elseif ($opIn === 'delete') {
                $this->validateSourceParams(false, false);
            } elseif ($opIn === 'rename') {
                $this->validateSourceParams(false, false);
                $this->validateDestParams(false, true);
            } elseif ($opIn == 'move') {
                $this->validateSourceParams(false, false);
                $this->validateDestParams(true, false);
            } elseif ($opIn == "listall") {
                $this->validateSourceParams(true, true);
                $this->validateSearchParams(false);
            } elseif ($opIn === 'listfiles') {
                $this->validateSourceParams(true, true);
                $this->validateOutputParams();
                $this->validateSearchParams(false);
            } elseif ($opIn == 'listsffiles') {
                $this->validateOutputParams();
                $this->validateSmartFolderId();
            } elseif ($opIn == 'listfolders') {
                $this->validateSourceParams(true, true);
                $this->validateOutputParams();
                $this->validateSearchParams(false);
            } elseif ($opIn === 'getfolderid') {
                $this->validateSourceParams(true, false);
            } elseif ($opIn === 'createdirectory') {
                $this->validateSourceParams(true, false);
            } elseif ($opIn === 'deletedirectory') {
                $this->validateSourceParams(true, false);
            } elseif ($opIn === 'renamedirectory') {
                $this->validateSourceParams(true, false);
                $this->validateDestParams(true, false);
            } elseif ($opIn === 'movedirectory') {
                $this->validateSourceParams(true, false);
                $this->validateDestParams(true, false);
            } elseif ($opIn === 'copydirectory') {
                $this->validateSourceParams(true, false);
                $this->validateDestParams(true, false);
            } elseif ($opIn === 'getfileinfo') {
                $this->validateSourceParams(false, false);
            } elseif ($opIn === 'getfolderinfo') {
                $this->validateSourceParams(true, false);
            } elseif ($opIn === 'setfilelock') {
                $this->validateSourceParams(false, false);
            } elseif ($opIn === 'getfilelock') {
                $this->validateSourceParams(false, false);
            } elseif ($opIn === 'clearfilelock') {
                $this->validateSourceParams(false, false);
            } elseif ($opIn === 'settags') {
                $this->validateSourceParams(false, false);
                $this->validateSourceTags(false);
            } elseif ($opIn === 'gettags') {
                $this->validateSourceParams(false, false);
            } elseif ($opIn === 'addtag') {
                $this->validateSourceParams(false, false);
                $this->validateSourceTags(true);
            } elseif ($opIn === 'deletetag') {
                $this->validateSourceParams(false, false);
                $this->validateSourceTags(true);
            } elseif ($opIn === 'getsyncinfo') {
                $this->validateSourceParams(true, false);
            } elseif ($opIn === 'checkcreds') {
                $this->validateCredParams(true, true, false, false);
            } elseif ($opIn === 'checkcredsad') {
                $this->validateCredParams(true, true, false, false);
            } elseif ($opIn === 'isvaliduser') {
                $this->validateCredParams(false, true, false, false);
            } elseif ($opIn == 'setperms') {
                $this->validateSetPermParams();
            } elseif ($opIn == 'checkperms') {
                $this->ValidateCheckPermParams();
            } elseif ($opIn == 'listversions') {
                $this->validateSourceParams(false, false);
            } elseif ($opIn == 'readversion') {
                $this->validateSourceParams(false, false);
                $this->validateVersionParams();
            } elseif ($opIn == 'restoreversion') {
                $this->validateSourceParams(false, false);
            } elseif ($opIn == 'deleteversion') {
                $this->validateSourceParams(false, false);
                $this->validateVersionParams();
            } elseif ($opIn == 'weberasetoken') {
                // Intentionally blank
            } elseif ($opIn == 'weberaseprojectlist') {
                // Intentionally blank
            } elseif ($opIn == 'weberasestore') {
                $this->ValidateWebEraseStoreParams();
            } elseif ($opIn == 'weberaseretrieve') {
                $this->ValidateWebEraseParams(false);
            } elseif ($opIn == 'weberaseupdate') {
                $this->ValidateWebEraseParams(false);
            } elseif ($opIn == 'weberasedelete') {
                $this->ValidateWebEraseParams(true);
            } elseif ($opIn == 'weberaseotc') {
                $this->ValidateWebEraseParams(true);
            } elseif ($opIn == 'weberasepolling') {
                $this->ValidateWebEraseParams(true);
            } elseif ($opIn === 'none') {
                return true;        // Doesn't matter what the source and dest identifiers are
            } else {
                throw new Exception("Unrecognized Operation Specified");
            }
            $retVal = true;
        } catch (Exception $e) {
            $retVal = false;
            echo "ERROR - STASHAPI.php::ValidateParams() - Error - " . $e->getMessage() . PHP_EOL;
        }
        return $retVal;
    }

    /**
     * Returns the file name for a given path (multi-byte string aware)
     * @param string $path the path and file name
     * @return string the file name for a given path
     * @see https://www.php.net/manual/en/function.basename.php#121405
     */
    function mb_basename($path)
    {
        if (preg_match('@^.*[\\\\/]([^\\\\/]+)$@s', $path, $matches)) {
            return $matches[1];
        } else if (preg_match('@^([^\\\\/]+)$@s', $path, $matches)) {
            return $matches[1];
        }
        return '';
    }

/////////////////////////////////////////
// STASH API HELPER FUNCTIONS
/////////////////////////////////////////

    /**
     * Function reads a file from the STASH Vault
     *
     * @param array $srcIdentifier an Associative Array containing the source identifier, values which describe the file to read in the Vault
     * @param string $fileName the file path and name to write the file to in the local filesystem
     * @param integer $retCode OUTPUT, the return code returned by the server in the response
     * @return array an associative array containing the response from the server
     */
    public function getFile($srcIdentifier, $fileName, &$retCode)
    {    // Read
        $retCode = 0;
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/read";
        if (!$this->validateParams('read')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendDownloadRequest($fileName);
        $this->params = array();
        if ($res == "1") {
            $retCode = 200;
            return array("code" => 200, "message" => "OK", "fileName" => $fileName);        // Simulate a 200 OK if command succeeds
        } else {
            // Something else went wrong with the request
            // Try to decode the response
            try {
                @unlink($fileName);
                $tArr = json_decode($res, true);
                if ($tArr == null) {
                    throw new Exception("Unable to download file - " . $res);
                }
                $retCode = (isset($tArr['code']) ? $tArr['code'] : 0);
                $retVal = $tArr;
            } catch (Exception $e) {
                $retCode = -1;
                $retVal = array('code' => -1, 'message' => $e->getMessage());
            }
            return $retVal;
        }
    }

    /**
     * Function writes a file to a STASH Vault
     *
     * @param string $fileNameIn the file name and path to upload to STASH vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of where to write the file in the Vault
     * @param integer $retCode the return code in the response
     * @param integer $fileId the unique File ID (UserFile) for the newly created file
     * @param integer $fileAliasId the unique File ID (UserFileAlias) for the newly created file
     * @return array the result / output of the write operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendFileRequest fails
     */
    public function putFile($fileNameIn, $srcIdentifier, &$retCode, &$fileId, &$fileAliasId)
    {
        $retCode = 0;
        $fileId = 0;
        $fileAliasId = 0;
        $overwriteFile = false;
        $owFileId = 0;

        if (!file_exists($fileNameIn)) {
            throw new InvalidArgumentException("Incorrect Input File Path or File Does Not Exist");
        }

        $this->params = $srcIdentifier;
        if (!$this->validateParams('write')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }

        if (!empty($srcIdentifier['overwriteFile'])) {
            $overwriteFile = $srcIdentifier['overwriteFile'] == "1";
            if ($overwriteFile) {
                $owFileId = (int)$srcIdentifier['overwriteFileId'];
            }
        }

        // Check if file exists on the server before uploading it and error if it does (unless overwrite specified)
        $fileInfoIdentifier = array("fileName" => self::mb_basename($fileNameIn));
        if ($overwriteFile) {
            $fileInfoIdentifier['fileId'] = $owFileId;
        } else {
            //$fileInfoIdentifier['fileName'] = $fileNameIn;
            if (!empty($srcIdentifier['destFolderNames'])) {
                $fileInfoIdentifier['folderNames'] = $srcIdentifier['destFolderNames'];
            }
            if (!empty($srcIdentifier['destFolderId'])) {
                $fileInfoIdentifier['folderId'] = $srcIdentifier['folderId'];
            }
        }

        $this->getFileInfo($fileInfoIdentifier, $retCode);
        if ($overwriteFile && $retCode == 404) {        // File doesn't exist, but overwrite requested
            throw new Exception("Unable to Upload File, Overwrite Requested, but File Does Not Exist");
        } else if (!$overwriteFile && $retCode != 404) {       // File exists, but overwrite not requested
            // File exists, or some error occurred
            throw new Exception("Unable to Upload File, File with Same Name Already Exists in Destination Folder and Overwrite Not Requested");
        }

        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/write";
        if (!$this->validateParams('write')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendFileRequest($fileNameIn);
        $this->params = array();

        $retVal = json_decode($res, true);
        $retCode = (isset($retVal['code']) ? $retVal['code'] : 0);
        if ($retCode == 200) {
            $fileId = (isset($retVal['fileId']) ? $retVal['fileId'] : 0);
            $fileAliasId = (isset($retVal['fileAliasId']) ? $retVal['fileAliasId'] : 0);
        }
        return $retVal;
    }

    /**
     * Function writes a file to a STASH Vault in chunks
     *
     * @param string $fileNameIn the file name and path to upload to STASH vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of where to write the file in the Vault
     * @param object $objIn placeholder, not used, for status updates callback
     * @param object $cancelIn placeholder, not used, for transfer cancellation
     * @param integer $retCode OUTPUT, the return code in the response
     * @param integer $fileId OUTPUT, the unique File ID (UserFile) for the newly created file
     * @param integer $fileAliasId OUTPUT, the unique File ID (UserFileAlias) for the newly created file
     * @return array the result / output of the write operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendFileRequest fails
     * @note This function is not implemented and only exists for compatibility with the .Net version of the API
     */
    public function putFileChunked($fileNameIn, $srcIdentifier, $objIn, $cancelIn, &$retCode, &$fileId, &$fileAliasId)
    {
        unset($objIn);
        unset($cancelIn);
        return $this->putFile($fileNameIn, $srcIdentifier, $retCode, $fileId, $fileAliasId);
    }

    /**
     * Function copies a file in the Vault, creating an entirely new copy, including new files in the storage location(s)
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of where to read the file in the Vault
     * @param array $dstIdentifier an associative array containing the destination identifier, the values of where to write the file in the Vault
     * @param integer $retCode an integer containing the return code from the request
     * @param integer $fileAliasId an integer containing the unique identifier (UserFileAlias) for the file
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function copyFile($srcIdentifier, $dstIdentifier, &$retCode, &$fileAliasId)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/copy";
        if (!$this->validateParams('copy')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();
        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        $fileAliasId = (isset($tVal['fileAliasId']) ? $tVal['fileAliasId'] : 0);
        return $tVal;
    }

    /**
     * Function renames a file in the Vault
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of where to read the file in the Vault
     * @param array $dstIdentifier associative array containing the destination identifier, the values of the new filename
     * @param integer $retCode an integer containing the return code from the request
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are invalid
     * @throws Exception if sendRequest() fails
     */
    public function renameFile($srcIdentifier, $dstIdentifier, &$retCode)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/rename";
        if (!$this->validateParams('rename')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();
        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function moves a file in the Vault, it does not change the files in the storage location(s)
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of where to read the file in the Vault
     * @param array $dstIdentifier an associative array containing the destination identifier, the values of where to write the file in the Vault
     * @param integer $retCode an integer containing the return code from the request
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function moveFile($srcIdentifier, $dstIdentifier, &$retCode)
    {    // Move
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/move";
        if (!$this->validateParams('move')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function deletes a file in the Vault
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to delete in the Vault
     * @param integer $retCode an integer containing the return code from the request
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function deleteFile($srcIdentifier, &$retCode)
    {    // Delete
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/delete";
        if (!$this->validateParams('delete')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function lists all files in the user's vault, or in a specified folder in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to list
     * @param integer $retCode an integer containing the return code from the request
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function listAll($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/listall";
        if (!$this->validateParams('listall')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function lists all the files in the Vault or in a specified folder in the vault
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to list the files for in the Vault
     * @param integer $retCode contains the return code from the operation
     * @param array $fileNames contains the names of the files returned by the request
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function listFiles($srcIdentifier, &$retCode, &$fileNames)
    {    // List Files
        $fileNames = array();
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/listfiles";
        if (!$this->validateParams('listfiles')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }

        $modelOutput = ($this->params['outputType'] >= "4" && $this->params['outputType'] <= "6");

        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        if (!empty($tVal['files'])) {
            if ($modelOutput) {
                $tArray = $tVal['files'];
                foreach ($tArray as $file) {
                    if (isset($file['name'])) {
                        $fileNames[] = $file['name'];
                    } else if (isset($file['text'])) {
                        $fileNames[] = $file['text'];
                    } else {
                        $fileNames[] = "";
                    }
                }
            } else {
                $fileNames = $tVal['files'];
            }
        }

        return $tVal;
    }

    /**
     * Function lists all the files in a specified SmartFolder
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to list the files for in the Vault
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @param array $fileNames OUTPUT, array of strings, contains the names of the files returned by the request
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function listSFFiles($srcIdentifier, &$retCode, &$fileNames)
    {    // List Files
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/listsffiles";
        if (!$this->validateParams('listsffiles')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }

        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        $fileNames = (empty($tVal['files']) ? array() : $tVal['files']);

        return $tVal;
    }

    /**
     * Function lists all the folders in the Vault or for the specified folder
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to list in the Vault
     * @param integer $retCode contains the return code from the operation
     * @param array $folderNames contains the names of the folders returned by the request
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function listFolders($srcIdentifier, &$retCode, &$folderNames)
    {    // List all folders
        $folderNames = array();
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/listfolders";
        if (!$this->validateParams('listfolders')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }

        $modelOutput = ($this->params['outputType'] >= "4" && $this->params['outputType'] <= "6");

        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        if (!empty($tVal['folders'])) {
            if ($modelOutput) {
                $tArray = $tVal['folders'];
                foreach ($tArray as $folder) {
                    if (isset($folder['text'])) {
                        $folderNames[] = $folder['text'];
                    } else {
                        $folderNames[] = "";
                    }
                }
            } else {
                $folderNames = $tVal['folders'];
            }
        }

        return $tVal;
    }

    /**
     * Function returns the Folder ID of the specified directory, or 0 if not found
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to find the ID for
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function getFolderId($srcIdentifier)
    {    // Get the Internal Folder ID for the specified directory
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/getfolderid";
        if (!$this->validateParams('getfolderid')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);

        return $tVal;
    }

    /**
     * Function sets a lock on a file in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to set the lock on
     * @param integer $retCode OUTPUT, the return code from the API call
     * @param integer $fileAliasId OUTPUT, the UserFileAlias id of the file the lock was set on
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function setFileLock($srcIdentifier, &$retCode, &$fileAliasId)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/setfilelock";
        if (!$this->validateParams('setfilelock')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        if (isset($results['fileAliasId'])) {
            $fileAliasId = (int)$results['fileAliasId'];
        } else {
            $fileAliasId = 0;
        }

        return $results;
    }

    /**
     * Function gets the lock status on a file in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to get the lock status of
     * @param integer $retCode OUTPUT, the return code from the API call
     * @param integer $fileLock OUTPUT, the file lock status (0 = unlocked, 1 = locked)
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function getFileLock($srcIdentifier, &$retCode, &$fileLock)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/getfilelock";
        if (!$this->validateParams('getfilelock')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        if (isset($results['fileLock'])) {
            $fileLock = (int)$results['fileLock'];
        } else {
            $fileLock = 0;
        }

        return $results;
    }

    /**
     * Function clears the lock on a file in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to clear the lock on
     * @param integer $retCode OUTPUT, the return code from the API call
     * @param integer $fileAliasId OUTPUT, the UserFileAlias id of the file the lock was set on
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function clearFileLock($srcIdentifier, &$retCode, &$fileAliasId)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/clearfilelock";
        if (!$this->validateParams('clearfilelock')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        if (isset($results['fileAliasId'])) {
            $fileAliasId = (int)$results['fileAliasId'];
        } else {
            $fileAliasId = 0;
        }

        return $results;
    }

    /**
     * Function gets the Tags for a file in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to get the Tags on
     * @param integer $retCode OUTPUT, the return code from the API call
     * @param string $fileTags OUTPUT, the tags, as a comma separated string for the file
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function getTags($srcIdentifier, &$retCode, &$fileTags)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/gettags";
        if (!$this->validateParams('gettags')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        if (isset($results['fileTags'])) {
            $fileTags = (int)$results['fileTags'];
        } else {
            $fileTags = 0;
        }

        return $results;
    }

    /**
     * Function sets the Tags for a file in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to set the Tags on
     * @param integer $retCode OUTPUT, the return code from the API call
     * @param integer $fileAliasId OUTPUT, the UserFileAlias ID for the file the tags were retrieved for
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function setTags($srcIdentifier, &$retCode, &$fileAliasId)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/settags";
        if (!$this->validateParams('settags')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        if (isset($results['fileAliasId'])) {
            $fileAliasId = (int)$results['fileAliasId'];
        } else {
            $fileAliasId = 0;
        }

        return $results;
    }

    /**
     * Function adds the specified Tag to a file in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to set the Tag on
     * @param integer $retCode OUTPUT, the return code from the API call
     * @param integer $fileAliasId OUTPUT, the UserFileAlias ID for the file the tags were retrieved for
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function addTag($srcIdentifier, &$retCode, &$fileAliasId)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/addtag";
        if (!$this->validateParams('addtag')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        if (isset($results['fileAliasId'])) {
            $fileAliasId = (int)$results['fileAliasId'];
        } else {
            $fileAliasId = 0;
        }

        return $results;
    }

    /**
     * Function deletes the specified Tag from a file in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to delete the Tag from
     * @param integer $retCode OUTPUT, the return code from the API call
     * @param integer $fileAliasId OUTPUT, the UserFileAlias ID for the file the tags were retrieved for
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function deleteTag($srcIdentifier, &$retCode, &$fileAliasId)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/deletetag";
        if (!$this->validateParams('deletetag')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        if (isset($results['fileAliasId'])) {
            $fileAliasId = (int)$results['fileAliasId'];
        } else {
            $fileAliasId = 0;
        }

        return $results;
    }

    /**
     * Function recursively creates a folder in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to create
     * @param integer $retCode OUTPUT, the return code from the API call
     * @param integer $dirId OUTPUT, the user_folder.id of the newly created directory
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function createDirectory($srcIdentifier, &$retCode, &$dirId)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/createdirectory";
        if (!$this->validateParams('createdirectory')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        if (isset($results['folderId'])) {
            $dirId = (int)$results['folderId'];
        } else {
            $dirId = 0;
        }

        return $results;
    }

    /**
     * Function renames a folder in the vault
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to rename
     * @param array $dstIdentifier an associative array containing the destination identifier, the values to rename the folder
     * @param integer $retCode OUTPUT - the return code from the API call
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function renameDirectory($srcIdentifier, $dstIdentifier, &$retCode)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/renamedirectory";
        if (!$this->validateParams('renamedirectory')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        return $results;
    }

    /**
     * Function moves a folder in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to move
     * @param array $dstIdentifier an associative array containing the destination identifier, the values to move the folder
     * @param string $retCode the return code from the request
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function moveDirectory($srcIdentifier, $dstIdentifier, &$retCode)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/movedirectory";
        if (!$this->validateParams('movedirectory')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        return $results;
    }

    /**
     * Function copies a folder in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to copy
     * @param array $dstIdentifier an associative array containing the destination identifier, the values of the folder to copy
     * @param string $retCode the return code from the request
     * @param integer $dirId the unique identifier for the newly created folder
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function copyDirectory($srcIdentifier, $dstIdentifier, &$retCode, &$dirId)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/copydirectory";
        if (!$this->validateParams('copydirectory')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        if (isset($results['folderId'])) {
            $dirId = $results['folderId'];
        } else {
            $dirId = 0;
        }

        return $results;
    }

    /**
     * Function recursively deletes a folder in the vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which folder to delete
     * @param integer $retCode the return code from the request
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function deleteDirectory($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/deletedirectory";
        if (!$this->validateParams('deletedirectory')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        $retCode = (empty($results['code']) ? -1 : $results['code']);
        return $results;
    }

    /**
     * Function gets the file information for the specified file in the Vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to get the information for
     * @param integer $retCode OUTPUT, the return code from the function
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function getFileInfo($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/getfileinfo";
        if (!$this->validateParams('getfileinfo')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        $retCode = (empty($results['code']) ? -1 : $results['code']);

        return $results;
    }

    /**
     * Function gets the folder information for the specified folder in the Vault
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to get the information for
     * @param integer $retCode OUTPUT, the return code from the function
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function getFolderInfo($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/getfolderinfo";
        if (!$this->validateParams('getfolderinfo')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        $retCode = (empty($results['code']) ? -1 : $results['code']);

        return $results;
    }

    /**
     * Function gets sync info (path, type, hash, timestamp) for all sub-elements in specified folder
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which file to get the information for
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function getSyncInfo($srcIdentifier)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/getsyncinfo";
        if (!$this->validateParams('getsyncinfo')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        if (isset($results['syncInfo'])) {
            return $results['syncInfo'];
        } else {
            return $results;
        }
    }

    /**
     * Function gets information on the user's vault
     * @param integer $retCode an integer containing the return code from the request
     * @return array the result / output of the operation
     * @throws Exception if sendRequest() fails
     * @throws InvalidArgumentException if the input parameters are invalid
     */
    public function getVaultInfo(&$retCode)
    {
        $this->url = $this->BASE_API_URL . "api2/file/getvaultinfo";

        // No parameter validation necessary

        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function checks the provided credentials to make sure the API ID, API PW, username, and account password match a valid account
     * This function generates a failed login if the credentials are not valid for the given user account.
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which credentials to check
     * @param integer $retCode OUTPUT, the return code from the operation
     * @param string $errMsg OUTPUT, the error message, if one occurred
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function checkCreds($srcIdentifier, &$retCode, &$errMsg)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/auth/checkcreds";
        if (!$this->validateParams('checkcreds')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        $errMsg = "";
        if ($retCode != 200) {
            $errMsg = (empty($tVal['error']['extendedErrorMessage']) ? "Unknown Error" : $tVal['error']['extendedErrorMessage']);
        }
        return $results;
    }

    /**
     * Function checks the provided credentials to make sure the API ID, API PW, username, and account password match a valid account
     * This function generates a failed login if the credentials are not valid for the given user account.
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which credentials to check
     * @param integer $retCode OUTPUT, the return code from the operation
     * @param string $errMsg OUTPUT, the error message, if one occurred
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function checkCredsAD($srcIdentifier, &$retCode, &$errMsg)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/auth/adauth";
        if (!$this->validateParams('checkcredsad')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        $errMsg = "";
        if ($retCode != 200) {
            $errMsg = (empty($tVal['error']['extendedErrorMessage']) ? "Unknown Error" : $tVal['error']['extendedErrorMessage']);
        }
        return $results;
    }

    /** Function checks the connection to the Vault with the current API settings
     *
     * @param integer $retCode the return code from the operation
     * @param string $errMsg the error message, if one occurs
     * @return boolean T if the connection to the vault succeeds, F otherwise
     * @throws Exception for errors in sendRequest()
     */
    public function checkVaultConnection(&$retCode, &$errMsg)
    {
        $this->url = $this->BASE_API_URL . "api2/auth/testloopback";
        if (!$this->validateParams('none')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        if ($retCode == 200) {
            return true;
        } else {
            $errMsg = (!empty($tVal['message']) ? $tVal['message'] : " no message returned");
            return false;
        }
    }

    /**
     * Function checks the provided username and reports if its taken or not
     *
     * @param array $srcIdentifier an associative array containing the source identifier, the values of which user account to check
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function isValidUser($srcIdentifier)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/auth/isvaliduser";
        if (!$this->validateParams('isvaliduser')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        return $results;
    }

    /**
     * Function sets the access permissions for a specified folder
     *
     * @param array $srcIdentifier an associative array containing the permission values to set
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @param array $permIds OUTPUT, contains the integer IDs of the folder permission entries that were created or updated
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function setPermissions($srcIdentifier, &$retCode, &$permIds)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/setperms";
        if (!$this->validateParams('setperms')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        $permIds = (empty($tVal['permIds']) ? array() : $tVal['permIds']);

        return $tVal;
    }

    /**
     * Function checks the access permissions for a specified folder and requested access level
     *
     * @param array $srcIdentifier an associative array containing the objectId, objectIdType, and requestedAccess parameters
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @param boolean $result OUTPUT, contains the result of the permission check (T or F)
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function checkPermissions($srcIdentifier, &$retCode, &$result)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/checkperms";
        if (!$this->validateParams('checkperms')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        $result = (empty($tVal['result']) ? false : $tVal['result']);

        return $tVal;
    }

    /**
     * Function gets the list of versions for a specified file
     * @param array $srcIdentifier an associative array containing the source and version parameters
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function listVersions($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/listversions";
        if (!$this->validateParams('listversions')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function reads (gets) a specific version of a file
     * @param array $srcIdentifier an associative array containing the source and version parameters
     * @param string $fileName the file path and name to write the file to in the local filesystem
     * @param integer $retCode OUTPUT, the return code returned by the server in the response
     * @return array an associative array containing the response from the server
     * @throws Exception for missing output file directory
     */
    public function readVersion($srcIdentifier, $fileName, &$retCode)
    {    // Read
        $retCode = 0;

        $dirName = dirname($fileName);
        if (! file_exists($dirName)) { throw new Exception("Incorrect Output File Path or Path Does Not Exist"); }

        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/readversion";
        if (!$this->validateParams('readversion')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendDownloadRequest($fileName);
        $this->params = array();
        if ($res == "1") {
            $retCode = 200;
            return array("code" => 200, "message" => "OK", "fileName" => $fileName);        // Simulate a 200 OK if command succeeds
        } else {
            // Something else went wrong with the request
            // Try to decode the response
            try {
                @unlink($fileName);
                $tArr = json_decode($res, true);
                if ($tArr == null) {
                    throw new Exception("Unable to download file - " . $res);
                }
                $retCode = (isset($tArr['code']) ? $tArr['code'] : 0);
                $retVal = $tArr;
            } catch (Exception $e) {
                $retCode = -1;
                $retVal = array('code' => -1, 'message' => $e->getMessage());
            }
            return $retVal;
        }
    }

    /**
     * Function restores a specific version of te a file to the current / master file
     * @param array $srcIdentifier an associative array containing the source and version parameters
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function restoreVersion($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/restoreversion";
        if (!$this->validateParams('restoreversion')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function deletes the specified version for a specified file
     * @param array $srcIdentifier an associative array containing the source and version parameters
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function deleteVersion($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/deleteversion";
        if (!$this->validateParams('deleteversion')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function gets the parameters sent by the client and returned/echo'd by the server
     * @param array $srcIdentifier an associative array containing the parameters to send/echo
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @return array the result / output of the operation
     * @throws InvalidArgumentException if the input parameters are not valid
     * @throws Exception if sendRequest() fails
     */
    public function testLoopback($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/testloopback";
        if (!$this->validateParams('none')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function stores a file using WebErase credentials and WebErase Rules
     * @param string $fileNameIn the full path and name of the local file to store
     * @param array $srcIdentifier an associative array containing the token_key, fileKey, folderId parameters
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @param integer $fileId OUTPUT, contains the UserFile object ID of the newly stored file
     * @param integer $fileAliasId OUTPUT, contains the UserFileAlias object ID of the newly stored file
     * @param integer $otc OUTPUT, the one time code to access this file, if one time codes are enabled
     * @return array the result / output of the operation
     * @throws Exception for errors in SendFileRequest()
     * @note requires srcIdentifier to have token, folderId, fileName, and fileKey parameters defined
     */
    public function webEraseStore($fileNameIn, $srcIdentifier, &$retCode, &$fileId, &$fileAliasId, &$otc)
    {
        // Requires token_key, destFolderId, fileKey, projectId, overwriteFile, overwriteFileId
        if (!file_exists($fileNameIn)) {
            throw new InvalidArgumentException("Incorrect Input File Path or File Does Not Exist");
        }

        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/weberase/store";
        if (!$this->validateParams('weberasestore')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }

        $res = $this->sendFileRequest($fileNameIn);
        $this->params = array();

        $retVal = json_decode($res, true);
        $retCode = (isset($retVal['code']) ? $retVal['code'] : 0);
        if ($retCode == 200) {
            $fileId = (isset($retVal['fileId']) ? $retVal['fileId'] : 0);
            $fileAliasId = (isset($retVal['fileAliasId']) ? $retVal['fileAliasId'] : 0);
            $otc = (isset($retVal['otc']) ? $retVal['otc'] : 0);
        }

        return $retVal;
    }

    /**
     * Function retrieves a file using WebErase credentials and WebErase Rules
     * @param array $srcIdentifier an associative array containing the parameters needed for the API call
     * @param string $localFileName the full path and name to save the downloaded file to
     * @param boolean $polling T if the function should poll for a transaction validation retrieval
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @return array the result / output of the operation
     * @throws Exception for errors in sendRequest()
     * @note this function only requires the token_key, fileKey, api_id for retrieving a file
     */
    private function webEraseDownload($srcIdentifier, $localFileName, $polling, &$retCode)
    {
        // Requires token, api_id, fileKey
        $this->params = $srcIdentifier;
        if ($polling) {
            $this->url = $this->BASE_API_URL . "api2/weberase/polling";
        } else {
            $this->url = $this->BASE_API_URL . "api2/weberase/retrieve";
        }
        // Params are validated the same for retrieve and polling
        if (!$this->validateParams('weberaseretrieve')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendDownloadRequest($localFileName);
        $this->params = array();

        if ($res == "1") {
            // Simulate a 200 OK if command succeeds
            $tVal['code'] = "200";
            $tVal['message'] = "OK";
            $tVal['fileName'] = $localFileName;
        } else {
            $tVal = json_decode($res, true);
            $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        }
        return $tVal;
    }

    /**
     * Function retrieves a file using WebErase credentials and WebErase Rules
     * @param array $srcIdentifier an associative array containing the parameters needed for the API call
     * @param string $localFileName the full path and name to save the downloaded file to
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @return array the result / output of the operation
     * @throws Exception for errors in sendRequest()
     * @note this function only requires the token_key, fileKey, api_id for retrieving a file
     */
    public function webEraseRetrieve($srcIdentifier, $localFileName, &$retCode)
    {
        $retCode = 0;
        return $this->webEraseDownload($srcIdentifier, $localFileName, false, $retCode);
    }

    /**
     * Function retrieves a file that's waiting for transaction validation approval
     * @param array $srcIdentifier an associative array containing the parameters needed for the API call
     * @param string $localFileName the full path and name to save the downloaded file to
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @return array the result / output of the operation
     * @throws Exception for errors in sendRequest()
     * @note this function only requires the token_key, fileKey, api_id for retrieving a file
     * @note calling polling API does not trigger a transaction validation notification - it only check for approval or deny; calling webEraseRetrieve() will trigger a new set of notifications
     */
    public function webErasePolling($srcIdentifier, $localFileName, &$retCode)
    {
        $retCode = 0;
        return $this->webEraseDownload($srcIdentifier, $localFileName, true, $retCode);
    }

    /**
     * Function updates an existing file using WebErase credentials and WebErase Rules
     * @param string $fileNameIn the full path and name of the local file to update
     * @param array $srcIdentifier an associative array containing the token_key, fileKey, folderId parameters
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @param integer $fileId OUTPUT, contains the UserFile object ID of the newly stored file
     * @param integer $fileAliasId OUTPUT, contains the UserFileAlias object ID of the newly stored file
     * @return array the result / output of the operation
     * @throws Exception for errors in SendFileRequest()
     * @note requires srcIdentifier to have token, folderId, fileName, and fileKey parameters defined
     */
    public function webEraseUpdate($fileNameIn, $srcIdentifier, &$retCode, &$fileId, &$fileAliasId)
    {
        // Requires token_key, destFolderId, fileKey, projectId, overwriteFile, overwriteFileId
        if (!file_exists($fileNameIn)) {
            throw new InvalidArgumentException("Incorrect Input File Path or File Does Not Exist");
        }

        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/weberase/update";
        if (!$this->validateParams('weberaseupdate')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }

        $res = $this->sendFileRequest($fileNameIn);
        $this->params = array();

        $retVal = json_decode($res, true);
        $retCode = (isset($retVal['code']) ? $retVal['code'] : 0);
        if ($retCode == 200) {
            $fileId = (isset($retVal['fileId']) ? $retVal['fileId'] : 0);
            $fileAliasId = (isset($retVal['fileAliasId']) ? $retVal['fileAliasId'] : 0);
        }

        return $retVal;
    }

    /**
     * Function gets a new token to use with WebErase credentials
     * @param array $srcIdentifier an associative array containing the parameters needed for the API call
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @param string $token OUTPUT, contains a valid token to use in future web erase store operations
     * @return array the result / output of the operation
     * @throws Exception for errors in sendRequest()
     * @note Only the api_id parameter is needed for this request
     */
    public function webEraseToken($srcIdentifier, &$retCode, &$token)
    {
        // Parameters needed: api_id
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/weberase/token";
        if (!$this->validateParams('none')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        $token = (empty($tVal['token']) ? "" : $tVal['token']);
        return $tVal;
    }

    /**
     * Function gets the one time code for a given token
     * @param array $srcIdentifier an associative array containing the parameters needed for the API call
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @param integer $otc OUTPUT, contains the one time code for this token
     * @return array the result / output of the operation
     * @throws Exception for errors in sendRequest()
     * @note the one time code is sent with the result of a store() or only once with this function regardless of number of times called
     */
    public function webEraseOneTimeCode($srcIdentifier, &$retCode, &$otc)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/weberase/onetimecode";
        if (!$this->validateParams('weberaseotc')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        $otc = (empty($tVal['otc_code']) ? "" : $tVal['otc_code']);
        return $tVal;
    }

    /**
     * Function deletes a file using WebErase credentials and WebErase Rules
     * @param array $srcIdentifier an associative array containing the parameters needed for the API call
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @return array the result / output of the operation
     * @note this function requires an api_id and token key to delete the file
     * @throws Exception for errors in sendRequest()
     */
    public function webEraseDelete($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/weberase/delete";
        if (!$this->validateParams('weberasedelete')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        return $tVal;
    }

    /**
     * Function gets a new token to use with WebErase credentials
     * @param array $srcIdentifier an associative array containing the parameters needed for the API call
     * @param integer $retCode OUTPUT, contains the return code from the operation
     * @return array the result / output of the operation
     * @throws Exception for errors in sendRequest()
     * @note Only the api_id parameter is needed for this request
     */
    public function webEraseProjectList($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/weberase/projectlist";
        if (!$this->validateParams('none')) {
            throw new InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        return $tVal;
    }

    /**
     * Function converts a string of key=value pairs, separated by commas and returns a source or destination identifier compatible array
     * This function handles | characters to separate directory names
     * @param string $stringIn the string to parse
     * @return array the converted array for use as source or destination identifier
     */
    public function convertStringToIdentArray($stringIn)
    {
        $finalArray = array();
        $tArray = explode(",", $stringIn);
        foreach ($tArray as $val) {
            if (strpos($val, "|") !== false) {
                $tmp = explode("=", $val);
                $tmp2 = explode("|", $tmp[1]);
                $finalArray[$tmp[0]] = $tmp2;
            } else {
                $tmp = explode("=", $val);
                if ($tmp[0] == "folderNames" || $tmp[0] == "destFolderNames") {
                    $finalArray[$tmp[0]] = array($tmp[1]);
                } elseif ($tmp[0] == "fileKey") {
                    $finalArray[$tmp[0]] = $this->encryptString($tmp[1], true);
                } else {
                    $finalArray[$tmp[0]] = $tmp[1];
                }
            }
        }

        return $finalArray;
    }
}
