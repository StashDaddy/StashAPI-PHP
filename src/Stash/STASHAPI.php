<?php
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

use Exception;

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
     * @param String $apiId , the API ID
     * @param String $apiPw , the API PW
     * @param string $urlIn - the base url (e.g. 127.0.0.1, or https://www.stashbusiness.com) if different from the default
     * @param Boolean, verbosity, T to generate log messages from STASHAPI functions
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
        $this->BASE_API_URL = (! empty($urlIn) ? $urlIn : self::BASE_URL);
        if (substr_compare($this->BASE_API_URL, "/", -1, 1) !== 0) {
            $this->BASE_API_URL .= "/";
        }
    }

    /**
     * Returns the constants in the class
     * @throws \ReflectionException if failed to get constants
     * @return array|NULL
     */
    public static function getConstants()
    {
        $oClass = new \ReflectionClass(__CLASS__);
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
     * Checks the parameter and value for sanity and compliance with rules
     *
     * @param array, the parameter name => value
     * @return Boolean, T if all the parameters are deemed valid
     */
    public static function isValid(array $dataIn)
    {
        try {
            foreach ($dataIn as $idx => $val) {
                if ($idx === "api_id") {                    // API_ID is 32 chars, a-f,0-9 (hex chars only)
                    if (mb_strlen($val) != self::STASHAPI_ID_LENGTH)
                        throw new Exception($idx . " Must Be " . self::STASHAPI_ID_LENGTH . " Characters in Length");
                    if (preg_match('/[^abcdef0-9]/i', $val))
                        throw new Exception($idx . " Has Invalid Characters, only a-f and 0-9 are allowed");
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
            echo "Invalid Parameter - " . $idx . ", Reason: " . $e->getMessage() . "\n\r";
            $retVal = false;
        }
        return $retVal;
    }

    /**
     * api_id setter
     *
     * @param string, your api_id
     * @throws Exception if idIn is invalid or does not match syntax requirements
     * @return Object/STASHAPI
     */
    public function setId($idIn)
    {
        if ($this->verbosity) echo "- setId - " . $idIn . "\n\r";

        if (!STASHAPI::isValid(array("api_id" => $idIn))) {
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
     * @param string, your api_pw
     * @return Object/STASHAPI
     */
    public function setPw($pwIn)
    {
        if ($this->verbosity) echo "- setPw - " . $pwIn . "\n\r";
        $this->api_pw = $pwIn;
        return $this;
    }

    /**
     * api_pw getter
     * @return String, the API_PW for this instance
     */
    public function getPw()
    {
        return $this->api_pw;
    }

    /**
     * api_timestamp setter
     *
     * @return Object/STASHAPI
     */
    public function setTimestamp()
    {
        if ($this->verbosity) echo "- setTimestamp - " . time() . "\n\r";
        $this->api_timestamp = (int)time();
        return $this;
    }

    /**
     * api_timestamp getter
     * @return Integer, the API_timestamp for this instance
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
     * @throws Exception for invalid inputs
     * @return Boolean, T if the function succeeds
     */
    public function setSignature(array $dataIn)
    {
        if ($this->verbosity) echo "- setSignature - dataIn: " . print_r($dataIn, true);

        if (!isset($dataIn['url']) || $dataIn['url'] == "") throw new Exception("Input array missing url for signature calculation");
        if (!isset($dataIn['api_version']) || $dataIn['api_version'] == "") throw new Exception("Input array missing api_version for signature calculation");
        if (!isset($dataIn['api_id']) || $dataIn['api_id'] == "") throw new Exception("Input array missing api_id for signature calculation");
        if (!isset($dataIn['api_timestamp']) || $dataIn['api_timestamp'] == "") throw new Exception("Input array missing api_timestamp for signature calculation");
        if (isset($dataIn['api_signature'])) unset($dataIn['api_signature']);

        $strToSign = http_build_query($dataIn);
        $sig = hash_hmac('sha256', $strToSign, $this->getPw());

        $this->api_signature = $sig;
        return true;
    }

    /**
     * api_signature getter
     * @return String, the API_signature for this instance
     */
    public function getSignature()
    {
        return $this->api_signature;
    }

    /**
     * Function encrypts a string with your API_PW
     * @param String, the string to encrypt
     * @param Bool, T if the function should return hexbits instead of raw
     * @return String, the encrypted string
     */
    public function encryptString($strIn, $returnHexBits)
    {
        if ($strIn == "") return "";
        if ($this->api_pw == "") return "";

        if (strlen($this->api_pw) < 32) {
            throw new \InvalidArgumentException("API_PW must be at least 32 characters");
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
     * @param String, the string to decrypt
     * @param Bool, T if the input string is hexbits and should be converted back to binary before decryption
     * @return String, the decrypted string
     */
    public function decryptString($strIn, $inHexBits)
    {
        if ($strIn == "") return "";
        if ($this->api_pw == "") return "";

        if (strlen($this->api_pw) < 32) {
            throw new \InvalidArgumentException("API_PW must be at least 32 characters");
        }

        if ($inHexBits) {
            $strIn = hex2bin($strIn);
        }
        $ivsize = openssl_cipher_iv_length(self::ENC_ALG);
        $rawoption = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true;

        if (strlen($strIn) < $ivsize) {
            throw new \UnexpectedValueException("Insufficient Input Data to Decrypt", 400);
        }

        $iv = substr($strIn, 0, $ivsize);
        $ct = substr($strIn, $ivsize, strlen($strIn) - $ivsize);

        return openssl_decrypt($ct, self::ENC_ALG, substr($this->api_pw, 0, 32), $rawoption, $iv);
    }

    /**
     * Function builds and sends an API request
     * @throws Exception for invalid URLs
     * @return String, the result from the curl operation
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // Skip verifying peer certificate
        }

        # Return response instead of printing.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        # Send request.
        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($this->verbosity) echo "- sendRequest Complete - Result: " . $result . " Error: " . $err . "\n\r";
        return $result;
    }

    /**
     * Function builds and sends an API download request
     * The result will be stored in a file, specified by $fileNameIn
     *
     * @param String, the filename to save the downloaded data to
     * @return String, the result from the curl operation
     * @throws \UnexpectedValueException if $this->url is invalid
     */
    public function sendDownloadRequest($fileNameIn)
    {
        if ($this->verbosity) echo "- sendDownloadRequest -\n\r";
        if ($this->url == "") throw new \UnexpectedValueException("Invalid URL");
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
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // Skip verifying peer certificate
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
     * @param String, the full path and name of the file to upload
     * @return String, the result from the curl operation
     * @throws \Exception if setSignature fails
     * @throws \UnexpectedValueException if $this->url is invalid
     * @throws \InvalidArgumentException if the fileNameIn parameter is empty or the file doesn't exist
     */
    public function sendFileRequest($fileNameIn)
    {
        if ($this->verbosity) echo "- sendFileRequest -\n\r";

        if ($this->url == "") throw new \UnexpectedValueException("Invalid URL");
        if ($fileNameIn == "" || (!file_exists($fileNameIn))) throw new \InvalidArgumentException("A Filename Must be Specified or File Does Not Exist");

        $apiParams['url'] = $this->url;
        $apiParams['api_version'] = $this->getVersion();
        $apiParams['api_id'] = $this->getId();
        $this->setTimestamp();
        $apiParams['api_timestamp'] = $this->getTimestamp();
        $this->setSignature(array_merge($apiParams, $this->params));        // Sign request
        $apiParams['api_signature'] = $this->getSignature();

        $header = array('Content-Type: multipart/form-data');
        $ch = curl_init($this->url);
        $cFile = new \CURLFile($fileNameIn, "application/octet-stream", $fileNameIn);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        // Define the CURL_IGNORE_SSL_ERRORS constant if you want to skip SSL verification (not recommended)
        if (defined("CURL_IGNORE_SSL_ERRORS") && CURL_IGNORE_SSL_ERRORS) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // Skip verifying peer certificate
        }

        //$fields['file'] = '@' . $fileNameIn;
        $mergedArrays = array_merge($apiParams, $this->params);
        $fields['params'] = json_encode($mergedArrays);
        $fields['file'] = $cFile;

        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        // Send request.
        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($this->verbosity) echo "- sendFileRequest Complete - Result: " . $result . " Error: " . $err . "\n\r";
        return $result;
    }

    /**
     * Function validates the source identifier parameters
     *
     * Source identifier can contain fileId, fileName, folderNames, folderId
     * To be valid, a fileId OR (fileName AND (folderId OR folderNames)) must be given
     * If folderOnly is T, then fileId and fileName need not be specified
     *
     * @param boolean $folderOnly - T if the validation should be for the source folder only
     * @param boolean $allowZeroIds - T if the validation should allow for folderId and/or fileId to be zero and/or the folderId to be -1 (all folders)
     * @return Boolean, T if the parameters are valid
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
            throw new \InvalidArgumentException("Source Parameters Invalid - folderId or folderNames MUST be specified");
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
            throw new \InvalidArgumentException("Source Parameters Invalid - fileId or fileName plus either folderId or folderNames MUST be specified");
        }
    }

    /**
     * Function validates the destination identifier parameters
     *
     * To be valid, destFileName AND (destFolderId OR destFolderNames) must be given
     * If folderOnly is T, then destFileName need not be given
     * If nameOnly is T, then destFolderId and destFolderNames need not be given (but folderOnly must be false)
     *
     * @param Boolean, T if the validation should be for the destination folder only
     * @param Boolean, T if the validate should be for the destination name only, if specified, folderOnly must be false
     * @return Boolean, T if the parameters are valid
     */
    private function validateDestParams($folderOnly, $nameOnly)
    {
        if ($folderOnly && $nameOnly) {
            throw new \InvalidArgumentException("folderOnly and nameOnly cannot both be T");
        }

        if ($folderOnly) {
            if (isset($this->params['destFolderId']) && $this->params['destFolderId'] > 0) return true;
            if (isset($this->params['destFolderNames']) && is_array($this->params['destFolderNames']) && count($this->params['destFolderNames']) > 0) return true;
            throw new \InvalidArgumentException("Destination Parameters Invalid - destFolderId or destFolderNames MUST be specified");
        } else {
            if (isset($this->params['destFileName']) && $this->params['destFileName'] != "") {
                if ($nameOnly === true) return true;
                if (isset($this->params['destFolderId']) && $this->params['destFolderId'] > 0) return true;
                if (isset($this->params['destFolderNames']) && is_array($this->params['destFolderNames']) && count($this->params['destFolderNames']) > 0) return true;
            }
            throw new \InvalidArgumentException("Destination Parameters Invalid - destFileName plus either destFolderId or destFolderNames MUST be specified");
        }
    }

    /**
     * Function validates the output type parameters
     *
     * Source identifier must contain outputType equal to one of the APIRequest::API_OUTPUT_TYPE_X constants
     * @return Boolean, T if the parameters are valid
     */
    private function validateOutputParams()
    {
        if (isset($this->params['outputType']) && (int)$this->params['outputType'] >= 0) return true;
        throw new \InvalidArgumentException("Source Parameters Invalid - outputType MUST be specified");
    }

    /**
     * Function validates the search parameters
     *
     * Source identifier may contain search and searchPartialMatch values unless $requireTerms is T, then search MUST be specified
     * @param boolean $requireTerms - T if the check should error if terms are not specified
     * @return boolean, T if the parameters are valid
     */
    private function validateSearchParams($requireTerms)
    {
        if ($requireTerms) {
            if (empty($this->params['search'])) {
                throw new \InvalidArgumentException("Search Terms Invalid - search parameter MUST be specified");
            }
        }
        return true;
    }

    /**
     * Function validates the smart folder ID parameter
     *
     * Source identifier must contain sfId
     * @return boolean, T if the parameters are valid
     * @throws \InvalidArgumentException for errors with invalid sfId parameter
     */
    private function validateSmartFolderId()
    {
        if (empty($this->params['sfId']) || $this->params['sfId'] <= 0) {
            throw new \InvalidArgumentException("Invalid SmartFolder ID");
        }
        return true;
    }

    /**
     * Function validates the overwriteFile parameter and corresponding overwriteFileId parameter, which is required if overwriteFile is specified
     * @return boolean T if the parameters are valid
     * @throws \InvalidArgumentException for errors with invalid overwriteFile or overwriteFileId parameters
     */
    public function validateOverwriteParams()
    {
        if (! empty($this->params['overwriteFile'])) {
            $overwriteFile = (int)$this->params['overwriteFile'];
            if ($overwriteFile > 1 || $overwriteFile < 0) { throw new \InvalidArgumentException("Invalid overwriteFile value"); }
            if ($overwriteFile == 1) {      // overwriteFileId MUST be specified
                if (empty($this->params['overwriteFileId'])) {
                    throw new \InvalidArgumentException("overwriteFileId parameter must be specified with overwriteFile");
                } else if ((int)$this->params['overwriteFileId'] < 1) {
                    throw new \InvalidArgumentException("Invalid value for overwriteFileId");
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
     * @param Boolean $checkFileKey T if the validation should check fileKey
     * @param Boolean $checkUsername T if the validation should check accountUsername
     * @param Boolean $checkApiId T if the validation should check apiid
     * @param Boolean $checkApiPw T if the validation should check apipw
     * @return Boolean, T if the parameters are valid
     */
    private function validateCredParams($checkFileKey, $checkUsername, $checkApiId, $checkApiPw)
    {
        if ($checkFileKey) {
            if (!isset($this->params['fileKey']) || $this->params['fileKey'] == "") {
                throw new \InvalidArgumentException("Source Parameters Invalid - fileKey MUST be specified and not blank");
            }
        }

        if ($checkUsername) {
            if (!isset($this->params['accountUsername']) || $this->params['accountUsername'] == "") {
                throw new \InvalidArgumentException("Source Parameters Invalid - accountUsername MUST be specified and not blank");
            }
        }

        if ($checkApiId) {
            if (!isset($this->params['apiid']) || $this->params['apiid'] == "") {
                throw new \InvalidArgumentException("Source Parameters Invalid - apiid MUST be specified and not blank");
            }
        }

        if ($checkApiPw) {
            if (!isset($this->params['apipw']) || $this->params['apipw'] == "") {
                throw new \InvalidArgumentException("Source Parameters Invalid - apipw MUST be specified and not blank");
            }
        }

        return true;
    }

    /**
     * Function validates the set permissions parameters
     *
     * Source identifier must contain permJson
     * @return boolean, T if the parameters are valid
     */
    private function validateSetPermParams()
    {
        if (empty($this->params['permJson'])) {
            throw new \InvalidArgumentException("Invalid permissions Json parameter");
        }
        return true;
    }

    /**
     * Function validates the check permissions parameters
     *
     * Source identifier must contain objectUserId, objectId, objectIdType, requestedAccess
     * @return boolean, T if the parameters are valid
     */
    private function validateCheckPermParams()
    {
        if (empty($this->params['objectUserId']) || (int)$this->params['objectUserId'] < 1) {
            throw new \InvalidArgumentException("Invalid objectUserId parameter");
        }
        if (empty($this->params['objectId']) || (int)$this->params['objectId'] < 1) {
            throw new \InvalidArgumentException("Invalid objectId parameter");
        }
        if (empty($this->params['objectIdType']) || (int)$this->params['objectIdType'] < 1) {
            throw new \InvalidArgumentException("Invalid objectIdType parameter");
        }
        if (empty($this->params['requestedAccess']) || (int)$this->params['requestedAccess'] < 0) {
            throw new \InvalidArgumentException("Invalid requestedAccess parameter");
        }

        return true;
    }

    /**
     * Function validates the input parameters before they are passed to the API endpoint
     *
     * @param String, the operation to check the parameters for
     * @return Boolean, T if the params are validated, F otherwise
     * @throws \InvalidArgumentException if the fileKey parameter is not included in the request
     */
    public function validateParams($opIn)
    {
        $opIn = mb_strtolower($opIn);
        try {
            if ($this->params == null && $opIn != "none") { throw new \InvalidArgumentException("Parameters Can't Be Null"); }

            if ($opIn === 'read') {
                $this->validateSourceParams(false, false);
                if ($this->params['fileKey'] == "") throw new \InvalidArgumentException("Invalid fileKey Parameter");
            } elseif ($opIn === 'write') {
                $this->validateDestParams(true, false);
                $this->validateOverwriteParams();
                if ($this->params['fileKey'] == "") throw new \InvalidArgumentException("Invalid fileKey Parameter");
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
            } elseif ($opIn === 'getsyncinfo') {
                $this->validateSourceParams(true, false);
            } elseif ($opIn === 'checkcreds') {
                $this->validateCredParams(true, true, false, false);
            } elseif ($opIn === 'isvaliduser') {
                $this->validateCredParams(false, true, false, false);
            } elseif ($opIn == 'setperms') {
                $this->validateSetPermParams();
            } elseif ($opIn == 'checkperms') {
                $this->ValidateCheckPermParams();
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
     * @param $path
     * @return string
     * @see https://www.php.net/manual/en/function.basename.php#121405
     */
    function mb_basename($path) {
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
     * @param array, an Associative Array containing the source identifier, values which describe the file to read in the Vault
     * @param String, the file path and name to write the file to in the local filesystem
     * @param Integer, OUTPUT, the return code returned by the server in the response
     * @return array, an associative array containing the response from the server
     */
    public function getFile($srcIdentifier, $fileName, &$retCode)
    {    // Read
        $retCode = 0;
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/read";
        if (!$this->validateParams('read')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param string $fileNameIn - the file name and path to upload to STASH vault
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of where to write the file in the Vault
     * @param integer $retCode - the return code in the response
     * @param integer $fileId - the unique File ID (UserFile) for the newly created file
     * @param integer $fileAliasId - the unique File ID (UserFileAlias) for the newly created file
     * @return array the result / output of the write operation
     * @throws \InvalidArgumentException if the input parameters are not valid
     * @throws \Exception if sendFileRequest fails
     */
    public function putFile($fileNameIn, $srcIdentifier, &$retCode, &$fileId, &$fileAliasId)
    {
        $retCode = 0; $fileId = 0; $fileAliasId = 0;
        $overwriteFile = false; $owFileId = 0;

        if (!file_exists($fileNameIn)) {
            throw new \InvalidArgumentException("Incorrect Input File Path or File Does Not Exist");
        }

        $this->params = $srcIdentifier;
        if (! $this->validateParams('write')) { throw new \InvalidArgumentException("Invalid Input Parameters"); }

        if (! empty($srcIdentifier['overwriteFile'])) {
            $overwriteFile = $srcIdentifier['overwriteFile'] == "1";
            if ($overwriteFile) {
                $owFileId = (int)$srcIdentifier['overwriteFileId'];
            }
        }

        // Check if file exists on the server before uploading it and error if it does (files can't be overwritten)
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
        } else if (! $overwriteFile && $retCode != 404) {       // File exists, but overwrite not requested
            // File exists, or some error occurred
            throw new Exception("Unable to Upload File, File with Same Name Already Exists in Destination Folder and Overwrite Not Requested");
        }

        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/write";
        if (!$this->validateParams('write')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * Function copies a file in the Vault, creating an entirely new copy, including new files in the storage location(s)
     *
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of where to read the file in the Vault
     * @param array $dstIdentifier - an associative array containing the destination identifier, the values of where to write the file in the Vault
     * @param integer $retCode - an integer containing the return code from the request
     * @param integer $fileAliasId - an integer containing the unique identifier (UserFileAlias) for the file
     * @return array, the result / output of the operation
     * @throws \InvalidArgumentException if the input parameters are not valid
     * @throws \Exception if sendRequest() fails
     */
    public function copyFile($srcIdentifier, $dstIdentifier, &$retCode, &$fileAliasId)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/copy";
        if (!$this->validateParams('copy')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of where to read the file in the Vault
     * @param array $dstIdentifier - associative array containing the destination identifier, the values of the new filename
     * @param integer $retCode - an integer containing the return code from the request
     * @return array, the result / output of the operation
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     */
    public function renameFile($srcIdentifier, $dstIdentifier, &$retCode)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/rename";
        if (!$this->validateParams('rename')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of where to read the file in the Vault
     * @param array $dstIdentifier - an associative array containing the destination identifier, the values of where to write the file in the Vault
     * @param integer $retCode - an integer containing the return code from the request
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function moveFile($srcIdentifier, $dstIdentifier, &$retCode)
    {    // Move
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/move";
        if (!$this->validateParams('move')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which file to delete in the Vault
     * @param integer $retCode - an integer containing the return code from the request
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function deleteFile($srcIdentifier, &$retCode)
    {    // Delete
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/delete";
        if (!$this->validateParams('delete')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        return $tVal;
    }

    /**
     * Function lists all files in the user's vault, or in a specified folder in the vault
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which folder to list
     * @param integer $retCode - an integer containing the return code from the request
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function listAll($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/listall";
        if (!$this->validateParams('listall')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which folder to list the files for in the Vault
     * @param integer $retCode - contains the return code from the operation
     * @param array $fileNames - contains the names of the files returned by the request
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function listFiles($srcIdentifier, &$retCode, &$fileNames)
    {    // List Files
        $fileNames = array();
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/listfiles";
        if (!$this->validateParams('listfiles')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }

        $modelOutput = ($this->params['outputType'] >= "4" && $this->params['outputType'] <= "6");

        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        //$tVal = json_decode($res,true);
        if (! empty($tVal['files'])) {
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

        //return json_decode($res,true);
        return $tVal;
    }

    /**
     * Function lists all the files in a specified SmartFolder
     *
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which folder to list the files for in the Vault
     * @param integer $retCode - OUTPUT, contains the return code from the operation
     * @param array/string $fileNames - OUTPUT, contains the names of the files returned by the request
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function listSFFiles($srcIdentifier, &$retCode, &$fileNames)
    {    // List Files
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/listsffiles";
        if (!$this->validateParams('listsffiles')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which folder to list in the Vault
     * @param integer $retCode - contains the return code from the operation
     * @param array $folderNames - contains the names of the folders returned by the request
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function listFolders($srcIdentifier, &$retCode, &$folderNames)
    {    // List all folders
        $folderNames = array();
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/listfolders";
        if (!$this->validateParams('listfolders')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }

        $modelOutput = ($this->params['outputType'] >= "4" && $this->params['outputType'] <= "6");

        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);

        if (! empty($tVal['folders'])) {
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
     * @param array, an associative array containing the source identifier, the values of which folder to find the ID for
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function getFolderId($srcIdentifier)
    {    // Get the Internal Folder ID for the specified directory
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/getfolderid";
        if (!$this->validateParams('getfolderid')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);

        return $tVal;
    }

    /**
     * Function recursively creates a folder in the vault
     * @param array, an associative array containing the source identifier, the values of which folder to create
     * @param integer $retCode - OUTPUT, the return code from the API call
     * @param integer $dirId - OUTPUT, the user_folder.id of the newly created directory
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function createDirectory($srcIdentifier, &$retCode, &$dirId)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/createdirectory";
        if (!$this->validateParams('createdirectory')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which folder to rename
     * @param array $dstIdentifier - an associative array containing the destination identifier, the values to rename the folder
     * @param integer $retCode - OUTPUT - the return code from the API call
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function renameDirectory($srcIdentifier, $dstIdentifier, &$retCode)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/renamedirectory";
        if (!$this->validateParams('renamedirectory')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        return $results;
    }

    /**
     * Function moves a folder in the vault
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which folder to move
     * @param array $dstIdentifier - an associative array containing the destination identifier, the values to move the folder
     * @param string $retCode - the return code from the request
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function moveDirectory($srcIdentifier, $dstIdentifier, &$retCode)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/movedirectory";
        if (!$this->validateParams('movedirectory')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);

        $retCode = (empty($results['code']) ? -1 : $results['code']);

        return $results;
    }

    /**
     * Function copies a folder in the vault
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which folder to copy
     * @param array $dstIdentifier - an associative array containing the destination identifier, the values of the folder to copy
     * @param string $retCode - the return code from the request
     * @param integer $dirId - the unique identifier for the newly created folder
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function copyDirectory($srcIdentifier, $dstIdentifier, &$retCode, &$dirId)
    {
        $this->params = array_merge($srcIdentifier, $dstIdentifier);
        $this->url = $this->BASE_API_URL . "api2/file/copydirectory";
        if (!$this->validateParams('copydirectory')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which folder to delete
     * @param integer $retCode - the return code from the request
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function deleteDirectory($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/deletedirectory";
        if (!$this->validateParams('deletedirectory')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        $retCode = (empty($results['code']) ? -1 : $results['code']);
        return $results;
    }

    /**
     * Function gets the file information for the specified file in the Vault
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which file to get the information for
     * @param integer $retCode OUTPUT, the return code from the function
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function getFileInfo($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/getfileinfo";
        if (!$this->validateParams('getfileinfo')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        $retCode = (empty($results['code']) ? -1 : $results['code']);

        return $results;
    }

    /**
     * Function gets the folder information for the specified folder in the Vault
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which file to get the information for
     * @param integer $retCode OUTPUT, the return code from the function
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function getFolderInfo($srcIdentifier, &$retCode)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/getfolderinfo";
        if (!$this->validateParams('getfolderinfo')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        $retCode = (empty($results['code']) ? -1 : $results['code']);

        return $results;
    }

    /**
     * Function gets sync info (path, type, hash, timestamp) for all sub-elements in specified folder
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which file to get the information for
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
     */
    public function getSyncInfo($srcIdentifier)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/getsyncinfo";
        if (!$this->validateParams('getsyncinfo')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param integer $retCode - an integer containing the return code from the request
     * @throws \InvalidArgumentException if the input parameters are invalid
     * @throws \Exception if sendRequest() fails
     * @return array, the result / output of the operation
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
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which credentials to check
     * @return array, the result / output of the operation
     * @throws \InvalidArgumentException if the input parameters are not valid
     * @throws \Exception if sendRequest() fails
     */
    public function checkCreds($srcIdentifier)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/auth/checkcreds";
        if (!$this->validateParams('checkcreds')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        return $results;
    }

    /** Function checks the connection to the Vault with the current API settings
     *
     * @param integer $retCode the return code from the operation
     * @param string $errMsg the error message, if one occurs
     * @return boolean T if the connection to the vault succeeds, F otherwise
     * @throws \Exception for errors in sendRequest()
     */
    public function checkVaultConnection(&$retCode, &$errMsg) {
        $this->url = $this->BASE_API_URL . "api2/auth/testloopback";
        if (!$this->validateParams('none')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param array $srcIdentifier - an associative array containing the source identifier, the values of which user account to check
     * @return array, the result / output of the operation
     * @throws \InvalidArgumentException if the input parameters are not valid
     * @throws \Exception if sendRequest() fails
     */
    public function isValidUser($srcIdentifier)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/auth/isvaliduser";
        if (!$this->validateParams('isvaliduser')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $results = json_decode($res, true);
        return $results;
    }

    /**
     * Function sets the access permissions for a specified folder
     *
     * @param array $srcIdentifier - an associative array containing the permission values to set
     * @param integer $retCode - OUTPUT, contains the return code from the operation
     * @param array $permIds - OUTPUT, contains the integer IDs of the folder permission entries that were created or updated
     * @return array the result / output of the operation
     * @throws \InvalidArgumentException if the input parameters are not valid
     * @throws \Exception if sendRequest() fails
     */
    public function setPermissions($srcIdentifier, &$retCode, &$permIds)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/setperms";
        if (!$this->validateParams('setperms')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
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
     * @param array $srcIdentifier - an associative array containing the objectId, objectIdType, and requestedAccess parameters
     * @param integer $retCode - OUTPUT, contains the return code from the operation
     * @param array $result - OUTPUT, contains the result of the permission check (T or F)
     * @return array the result / output of the operation
     * @throws \InvalidArgumentException if the input parameters are not valid
     * @throws \Exception if sendRequest() fails
     */
    public function checkPermissions($srcIdentifier, &$retCode, &$result)
    {
        $this->params = $srcIdentifier;
        $this->url = $this->BASE_API_URL . "api2/file/checkperms";
        if (!$this->validateParams('checkperms')) {
            throw new \InvalidArgumentException("Invalid Input Parameters");
        }
        $res = $this->sendRequest();
        $this->params = array();

        $tVal = json_decode($res, true);
        $retCode = (empty($tVal['code']) ? -1 : $tVal['code']);
        $result = (empty($tVal['result']) ? false : $tVal['result']);

        return $tVal;
    }

}
