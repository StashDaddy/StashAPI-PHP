<?php
/**
 * STASH API - PHP Implementation
 *
 * A PHP wrapper to interact with the STASH API from within PHP
 *
*/
namespace STASHAPI\v1;

use Exception;

class STASHAPI {

	const API_VERSION = "1.0";		// API Version
	const API_ID_LENGTH = 32;		// API_ID string length
	const API_PW_LENGTH = 32;		// API_PW string length (minimum)
	const API_SIG_LENGTH = 32;		// API_SIGNATURE string length (minimum)

	const BASE_API_URL = "https://www.stage.stashbusiness.com/";

	const ENC_ALG = 'aes-256-cbc';		// Encryption algorithm for use in encryptString & decryptString()

	private $api_id;			// The API_ID for your account
	private $api_pw;			// The API_PW for your account
	private $api_signature;			// The sha256 hmac signature for the request
	private $api_version;			// The API version which generated the request
	private $api_timestamp;			// The timestamp for when the request was generated
	private $verbosity;			// Set to T to generate echo logging message

	public $url;				// The URL to send the request to
	public $params;				// Associative array of parameters to send with the request

	/**
	 * STASHAPI Empty Constructor
	*/
	public function __construct($verbosity) {
		$this->api_version = self::API_VERSION;
		$this->verbosity = (bool)$verbosity;
	}

	/**
	 * Returns the constants in the class
	 *
	 * @return array|NULL
	*/
	public static function getConstants() {
		$oClass = new ReflectionClass(__CLASS__);
		return $oClass->getConstants();
	}

	/**
         * Returns a string representation of this object consiting of the API version
	 *
	 * @return string
	*/
	public function __toString() {
		return "STASHAPI Object - Version: " . $this->api_version . " ID: " . $this->api_id;
	}

	/**
	 * Returns the version for this API
	 *
	 * @return string
	*/
	public function getVersion() {
		return $this->api_version;
	}

	/**
	 * Checks the parameter and value for sanity and compliance with rules
	 *
	 * @param, Array, the parameter name => value
	 * @return Boolean, T if all the parameters are deemed valid
	*/
	public static function isValid(Array $dataIn) {
		try {
			foreach ($dataIn as $idx=>$val) {
				if ($idx === "api_id") {		// API_ID is 32 chars, a-z,0-9
					if (mb_strlen($val) != self::API_ID_LENGTH)
						throw new Exception($idx . " Must Be " . self::API_ID_LENGTH . " Characters in Length");
					if (preg_match('/[^a-zA-Z0-9]/i', $val))
						throw new Exception($idx . " Has Invalid Characters, only a-z and 0-9 are allowed");
				} elseif ($idx === "api_pw") {
					if (mb_strlen($val) <  self::API_PW_LENGTH)
						throw new Exception($idx . " Must Be at Least " . self::API_PW_LENGTH . " Characters in Length");
					if (preg_match('/[^abcdef0-9]/i', $val))
						throw new Exception($idx . " Has Invalid Characters, only a-f and 0-9 are allowed");
				} elseif ($idx === "api_signature") {
					if (mb_strlen($val) <  self::API_SIG_LENGTH)
						throw new Exception($idx . " Must Be at Least " . self::API_SIG_LENGTH . " Characters in Length");
					if (preg_match('/[^abcdef0-9]/i', $val))
						throw new Exception($idx . " Has Invalid Characters, only a-f and 0-9 are allowed");
				} elseif ($idx === "api_timestamp") {
					if (! is_int($val))
						throw new Exception($idx . " Must be an Integer Value");
					if ($val < 1)
						throw new Exception($idx . " Must be Greater Than 0");
				} elseif ($idx === "api_version") {
					if ($val != self::API_VERSION)
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
					if (! is_array($val))
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
	 * @return Object/STASHAPI
	*/
	public function setId($idIn) {
		if ($this->verbosity) echo "- setId - " . $idIn . "\n\r";

		$errMsg = "";
		if (! STASHAPI::isValid(array("api_id"=>$idIn))) {
			throw new Exception("Invalid API ID");
		}
		$this->api_id = $idIn;
		return $this;
	}

	/**
	 * api_id getter
	 * @return String, the API_ID for this instance
	*/
	public function getId() {
		return $this->api_id;
	}

	/**
	 * api_pw setter
	 *
	 * @param string, your api_pw
	 * @return Object/STASHAPI
	*/
	public function setPw($pwIn) {
		if ($this->verbosity) echo "- setPw - " . $pwIn . "\n\r";
		$this->api_pw = $pwIn;
		return $this;
	}

	/**
	 * api_pw getter
	 * @return String, the API_PW for this instance
	*/
	public function getPw() {
		return $this->api_pw;
	}

	/**
	 * api_timestamp setter
	 *
	 * @return Object/STASHAPI
	*/
	public function setTimestamp() {
		if ($this->verbosity) echo "- setTimestamp - " . time() . "\n\r";
		$this->api_timestamp = (int)time();
		return $this;
	}

	/**
	 * api_timestamp getter
	 * @return Integer, the API_timestamp for this instance
	*/
	public function getTimestamp() {
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
	 *
	 * @return Boolean, T if the function succeeds
	*/
	public function setSignature(Array $dataIn) {
		if ($this->verbosity) echo "- setSignature - dataIn: " . print_r($dataIn,true);

		if (! isset($dataIn['url']) || $dataIn['url'] == "") throw new Exception("Input array missing url for signature calculation");
		if (! isset($dataIn['api_version']) || $dataIn['api_version'] == "") throw new Exception("Input array missing api_version for signature calculation");
		if (! isset($dataIn['api_id']) || $dataIn['api_id'] == "") throw new Exception("Input array missing api_id for signature calculation");
		if (! isset($dataIn['api_timestamp']) || $dataIn['api_timestamp'] == "") throw new Exception("Input array missing api_timestamp for signature calculation");
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
	public function getSignature() {
		return $this->api_signature;
	}

	/**
	 * Function encrypts a string with your API_PW
	 * @param String, the string to encrypt
	 * @param Bool, T if the function should return hexbits instead of raw
	 * @return String, the encrypted string
	*/
	public function encryptString($strIn, $returnHexBits) {
		$retVal = "";
		if ($strIn == "") return "";
		if ($this->api_pw == "") return "";

		if (mb_strlen($this->api_pw, '8bit') < 32) { throw new \InvalidArgumentException("API_PW must be at least 32 characters"); }

		$ivsize = openssl_cipher_iv_length(self::ENC_ALG);
		$iv = openssl_random_pseudo_bytes($ivsize);

		$rawoption = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true;
		$ct = openssl_encrypt($strIn, self::ENC_ALG, mb_substr($this->api_pw,0,32), $rawoption, $iv);

		if ($returnHexBits) {
			return bin2hex($iv.$ct);
		} else {
			return $iv.$ct;
		}
	}

	/**
	 * Function decrypts a string with your API_PW
	 * @param String, the string to decrypt
	 * @param Bool, T if the input string is hexbits and should be converted back to binary before decryption
	 * @return String, the decrypted string
	*/
	public function decryptString($strIn, $inHexBits) {
		$retVal = "";
		if ($strIn == "") return "";
		if ($this->api_pw == "") return "";

		if (mb_strlen($this->api_pw,'8bit') < 32) { throw new \InvalidArgumentException("API_PW must be at least 32 characters"); }

		if ($inHexBits) {
			$strIn = hex2bin($strIn);
		}
		$ivsize = openssl_cipher_iv_length(self::ENC_ALG);
		$rawoption = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true;

		if (mb_strlen($strIn, '8bit') < $ivsize) { throw new \UnexpectedValueException("Insufficient Input Data to Decrypt", 400); }

		$iv = mb_substr($strIn, 0, $ivsize, '8bit');
		$ct = mb_substr($strIn, $ivsize, mb_strlen($strIn) - $ivsize, '8bit');

		return $retVal = openssl_decrypt($ct, self::ENC_ALG, mb_substr($this->api_pw,0,32), $rawoption, $iv);
	}

	/**
	 * Function builds and sends an API request
	 *
	 * @return String, the result from the curl operation
	*/
	public function sendRequest() {
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
			$this->setSignature(array_merge($apiParams,$this->params));
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
	*/
	public function sendDownloadRequest($fileNameIn) {
		if ($this->verbosity) echo "- sendDownloadRequest -\n\r";

		if ($this->url == "") throw new Exception("Invalid URL");

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
			$this->setSignature(array_merge($apiParams,$this->params));
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

		// Send request.
		$result = curl_exec($ch);
		$err = curl_error($ch);
		curl_close($ch);
		if ($this->verbosity) echo "- sendRequest Complete - Result: " . $result . " Error: " . $err . "\n\r";
		return $result;
	}

	/**
	 * Function builds and sends an API request to upload a file
	 *
	 * The input parameters for this function must include a FILE
	 *
	 * @param String, the full path and name of the file to upload
	 * @return String, the result from the curl operation
	*/
	public function sendFileRequest($fileNameIn) {
		if ($this->verbosity) echo "- sendFileRequest -\n\r";

		if ($this->url == "") throw new \UnexpectedValueException("Invalid URL");
		if ($fileNameIn == "" || (! file_exists($fileNameIn))) throw new \InvalidArgumentException("A Filename Must be Specified or File Does Not Exist");

		$apiParams['url'] = $this->url;
		$apiParams['api_version'] = $this->getVersion();
		$apiParams['api_id'] = $this->getId();
		$this->setTimestamp();
		$apiParams['api_timestamp'] = $this->getTimestamp();
		$this->setSignature(array_merge($apiParams,$this->params));		// Sign request
		$apiParams['api_signature'] = $this->getSignature();

		$header = array('Content-Type: multipart/form-data');
		$ch = curl_init($this->url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);

		$fields['file'] = '@'.$fileNameIn;
		$mergedArrays = array_merge($apiParams, $this->params);
		$fields['params'] = json_encode($mergedArrays);

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
	 * @param Boolean, T if the validation should be for the source folder only
	 * @return Boolean, T if the parameters are valid
	*/
	private function validateSourceParams($folderOnly) {
		if ($folderOnly) {
			if (isset($this->params['folderId']) && $this->params['folderId'] > 0) return true;
			if (isset($this->params['folderNames']) && is_array($this->params['folderNames']) && count($this->params['folderNames']) > 0) return true;
			throw new \InvalidArgumentException("Source Parameters Invalid - folderId or folderNames MUST be specified");
		} else {
			if (isset($this->params['fileId']) && $this->params['fileId'] > 0) return true;
			if (isset($this->params['fileName']) && $this->params['fileName'] != "") {
				if (isset($this->params['folderId']) && $this->params['folderId'] > 0) return true;
				if (isset($this->params['folderNames']) && is_array($this->params['folderNames']) && count($this->params['folderNames']) > 0) return true;
			}
			throw new \InvalidArgumentException("Source Parameters Invalid - fileId or fileName plus either folderId or folderNames MUST be specified");
		}
	}

	/**
	 * Function validates the destionation identifier parameters
	 *
	 * To be valid, destFileName AND (destFolderId OR destFolderNames) must be given
	 * If folderOnly is T, then destFileName need not be given
	 * If nameOnly is T, then destFolderId and destFolderNames need not be given (but folderOnly must be false)
	 *
	 * @param Boolean, T if the validation should be for the destination folder only
	 * @param Boolean, T if the validate should be for the destination name only, if specified, folderOnly must be false
	 * @return Boolean, T if the parameters are valid
 	*/
	private function validateDestParams($folderOnly, $nameOnly) {
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
	 * Function validates the input parameters before they are passed to the API endpoint
	 *
	 * @param String, the operation to check the parameters for
	 * @return Boolean, T if the params are validated, F otherwise
	*/
	public function validateParams($opIn) {
		$retVal = false;
		$opIn = mb_strtolower($opIn);
		try {
			if ($opIn === 'read') {
				$this->validateSourceParams(false);
				if ($this->params['fileKey'] == "") throw new \InvalidArgumentException("Invalid fileKey Parameter");
			} elseif ($opIn === 'write') {
				$this->validateDestParams(true,false);
				if ($this->params['fileKey'] == "") throw new \InvalidArgumentException("Invalid fileKey Parameter");
			} elseif ($opIn === 'copy' || $opIn === 'move') {
				$this->validateSourceParams(false);
				$this->validateDestParams(false,false);
			} elseif ($opIn === 'delete') {
				$this->validateSourceParams(false);
			} elseif ($opIn === 'rename') {
				$this->validateSourceParams(false);
				$this->validateDestParams(false,true);
			} elseif ($opIn === 'listfilesdir') {
				$this->validateSourceParams(true);
			} elseif ($opIn === 'none') {
				return true;		// Doesn't matter what the source and dest identifiers are
			}
			$retVal = true;
		} catch (Exception $e) {
			$retVal = false;
			echo $e->getMessage();
		}
		return $retVal;
	}

/////////////////////////////////////////
// STASH API HELPER FUNCTIONS
/////////////////////////////////////////

	/**
	 * Function reads a file from the STASH Vault
	 *
	 * @param Array, an Associative Array containing the source identifier, values which describe the file to read in the Vault
	 * @param String, the file path and name to write the file to in the local filesystem
	*/
	public function getFile($srcIdentifier, $fileName) {	// Read
		$this->params = $srcIdentifier;
		$this->url = self::BASE_API_URL . "api2/file/read";
		if (! $this->validateParams('read')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendDownloadRequest($fileName);
		$this->params = array();
		if ($res == "1") {
			return array("code"=>200,"message"=>"OK","fileName"=>$fileName);		// Simulate a 200 OK if command succeeds
		} else {
			return array("code"=>0,"message"=>"An Error Occurred While Downloading File");	 // Something else went wrong with the request
		}
	}

	/**
	 * Function writes a file to a STASH Vault
	 *
	 * @param String, the file name and path to upload to STASH vault
	 * @param Array, an associative array containing the source identifier, the values of where to write the file in the Vault
	 * @return Array, the result / output of the write operation
	*/
	public function putFile($fileNameIn, $srcIdentifier) {	// Write
		$this->params = $srcIdentifier;
		$this->url = self::BASE_API_URL . "api2/file/write";
		if (! $this->validateParams('write')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendFileRequest($fileNameIn);
		$this->params = array();
		return json_decode($res,true);
	}

	/**
	 * Function copies a file in the Vault, creating an entirely new copy, including new files in the storage location(s)
	 *
	 * @param Array, an associative array containing the source identifier, the values of where to read the file in the Vault
	 * @param Array, an associative array containing the destination identifier, the values of where to write the file in the Vault
	 * @return Array, the result / output of the operation
	*/
	public function copyFile($srcIdentifier, $dstIdentifier) {	// Copy
		$this->params = array_merge($srcIdentifier, $dstIdentifier);
		$this->url = self::BASE_API_URL . "api2/file/copy";
		if (! $this->validateParams('copy')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendRequest();
		$this->params = array();
		return json_decode($res,true);
	}

	/**
	 * Function renames a file in the Vault
	 *
	 * @param Array, an associative array containing the source identifier, the values of where to read the file in the Vault
	 * @param Array, an associative array containing the destination identifier, the values of the new filename
	 * @return Array, the result / output of the operation
	*/
	public function renameFile($srcIdentifier, $dstIdentifier) {	// Rename
		$this->params = array_merge($srcIdentifier, $dstIdentifier);
		$this->url = self::BASE_API_URL . "api2/file/rename";
		if (! $this->validateParams('rename')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendRequest();
		$this->params = array();
		return json_decode($res,true);
	}

	/**
	 * Function moves a file in the Vault, it does not change the files in the storage location(s)
	 *
	 * @param Array, an associative array containing the source identifier, the values of where to read the file in the Vault
	 * @param Array, an associative array containing the destination identifier, the values of where to write the file in the Vault
	 * @return Array, the result / output of the operation
	*/
	public function moveFile($srcIdentifier, $dstIdentifier) {	// Move
		$this->params = array_merge($srcIdentifier, $dstIdentifier);
		$this->url = self::BASE_API_URL . "api2/file/move";
		if (! $this->validateParams('move')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendRequest();
		$this->params = array();
		return json_decode($res,true);
	}

	/**
	 * Function deletes a file in the Vault
	 *
	 * @param Array, an associative array containing the source identifier, the values of which file to delete in the Vault
	 * @return Array, the result / output of the operation
	*/
	public function deleteFile($srcIdentifier) {	// Delete
		$this->params = $srcIdentifier;
		$this->url = self::BASE_API_URL . "api2/file/delete";
		if (! $this->validateParams('delete')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendRequest();
		return json_decode($res,true);
	}

	/**
	 * Function lists all the files in the Vault
	 *
	 * @return Array, the result / output of the operation
	*/
	public function listFiles() {	// List Files
		$this->url = self::BASE_API_URL . "api2/file/listfiles";
		if (! $this->validateParams('none')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendRequest();
		$this->params = array();
		return json_decode($res,true)['files'];
	}

	/**
	 * Function lists all the files with their paths in the Vault
	 *
	 * @return Array, the result / output of the operation
	*/
	public function listFilesPaths() {	// List Files with their Paths
		$this->url = self::BASE_API_URL . "api2/file/listfilespaths";
		if (! $this->validateParams('none')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendRequest();
		$this->params = array();
		return json_decode($res,true)['files'];
	}

	/**
	 * Function lists all the folders in the Vault
	 *
	 * @return Array, the result / output of the operation
	*/
	public function listFolders() {	// List all folders
		$this->url = self::BASE_API_URL . "api2/file/listfolders";
		if (! $this->validateParams('none')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendRequest();
		$this->params = array();
		return json_decode($res,true)['folders'];
	}

	/**
	 * Function lists all files in the specified directory in the Vault
	 *
	 * @param Array, an associative array containing the source identifier, the values of which folder to list the files for in the Vault
	 * @return Array, the result / output of the operation
	*/
	public function listFilesDir($srcIdentifier) {	// List Files for a given Directory
		$this->params = $srcIdentifier;
		$this->url = self::BASE_API_URL . "api2/file/listfilesdir";
		if (! $this->validateParams('listfilesdir')) {
			throw new \InvalidArgumentException("Invalid Input Parameters");
		}
		$res = $this->sendRequest();
		$this->params = array();
		return json_decode($res,true)['files'];
	}
}
?>
