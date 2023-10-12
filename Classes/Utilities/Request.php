<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Zugriff auf GET / POST Variablen, Filecontainer etc.
 */
class Request implements SingletonInterface {
   
	/**
	 * 	Merge aus $_GET und $_POST-Variablen
	 *	```
	 *	\nn\t3::Request()->GP();
	 *	```
	 * 	@return array
	 */
	public function GP ( $varName = null ) {
		$gp = [];
		ArrayUtility::mergeRecursiveWithOverrule($gp, GeneralUtility::_GET() ?: []);
		ArrayUtility::mergeRecursiveWithOverrule($gp, GeneralUtility::_POST() ?: []);
		if ($varName) {
			$val = \nn\t3::Settings()->getFromPath( $varName, $gp );
			return $val ?? null;
		}
		return $gp;
	}
	
	/**
	 * File-Uploads aus `$_FILES` holen und normalisieren.
	 * 
	 * Normalisiert folgende File-Upload-Varianten. 
	 * Enfernt leere Datei-Uploads aus dem Array.
	 * 
	 * ```
	 * <input name="image" type="file" />
	 * <input name="image[key]" type="file" />
	 * <input name="images[]" type="file" multiple="1" />
	 * <input name="images[key][]" type="file" multiple="1" />
	 * ````
	 *
	 * __Beispiele:__
	 * ALLE Datei-Infos aus `$_FILES`holen.
	 * ```
	 * \nn\t3::Request()->files();
	 * \nn\t3::Request()->files( true ); // Array erzwingen
	 * ```
	 * Datei-Infos aus `tx_nnfesubmit_nnfesubmit[...]` holen.
	 * ```
	 * \nn\t3::Request()->files('tx_nnfesubmit_nnfesubmit');
	 * \nn\t3::Request()->files('tx_nnfesubmit_nnfesubmit', true);	// Array erzwingen
	 * ```
	 * 
	 * Nur Dateien aus `tx_nnfesubmit_nnfesubmit[fal_media]` holen.
	 * ```
	 * \nn\t3::Request()->files('tx_nnfesubmit_nnfesubmit.fal_media' );	
	 * \nn\t3::Request()->files('tx_nnfesubmit_nnfesubmit.fal_media', true ); // Array erzwingen	
	 * ```
	 * @return array
	 */
	public function files( $path = null, $forceArray = false ) {
		if (!$_FILES) return [];
		
		if ($path === true) {
			$path = false;
			$forceArray = true;
		}

		$fileInfosByKey = [];

		// 'tx_nnfesubmit_nnfesubmit' => ['name' => ..., 'size' => ...] 
		foreach ($_FILES as $varName => $aspects) {

			if (!$fileInfosByKey[$varName]) {
				$fileInfosByKey[$varName] = [];
			}

			foreach ($aspects as $aspectKey => $vars) {

				// $aspectKey ist IMMER 'name' || 'tmp_name' || 'size' || 'error'
				if (!is_array($vars)) {
					
					// <input type="file" name="image" />
					if ($forceArray) {
						$fileInfosByKey[$varName][0][$aspectKey] = $vars;
					} else {
						$fileInfosByKey[$varName][$aspectKey] = $vars;
					}

				} else {

					foreach ($vars as $varKey => $varValue) {
						
						// <input type="file" name="images[]" multiple="1" />
						if (is_numeric($varKey)) {
							$fileInfosByKey[$varName][$varKey][$aspectKey] = $varValue;
						}

						if (!is_numeric($varKey)) {
							if (!is_array($varValue)) {
								// <input type="file" name="image[key]" />
								if ($forceArray) {
									$fileInfosByKey[$varName][$varKey][0][$aspectKey] = $varValue;
								} else {
									$fileInfosByKey[$varName][$varKey][$aspectKey] = $varValue;
								}
							} else {
								// <input type="file" name="images[key][]" multiple="1" />
								foreach ($varValue as $n=>$v) {
									$fileInfosByKey[$varName][$varKey][$n][$aspectKey] = $v;
								}
							}
						}
					}
				}
			}
		}

		// Leere Uploads entfernen
		foreach ($fileInfosByKey as $k=>$v) {
			if (isset($v['error']) && $v['error'] == UPLOAD_ERR_NO_FILE) {
				unset($fileInfosByKey[$k]);
			}
			if (is_array($v)) {
				foreach ($v as $k1=>$v1) {
					if (isset($v1['error']) && $v1['error'] == UPLOAD_ERR_NO_FILE) {
						unset($fileInfosByKey[$k][$k1]);
					}		
					if (is_array($v1)) {
						foreach ($v1 as $k2=>$v2) {
							if (isset($v2['error']) && $v2['error'] == UPLOAD_ERR_NO_FILE) {
								unset($fileInfosByKey[$k][$k1][$k2]);
							}		
						}
					}		
				}
			}
		}
		if (!$path) return $fileInfosByKey;
		return \nn\t3::Settings()->getFromPath( $path, $fileInfosByKey );
	}
	
	/**
	 * 	Request-URI zurückgeben. Im Prinzip die URL / der GET-String 
	 * 	in der Browser URL-Leiste, der in `$_SERVER['REQUEST_URI']`
	 * 	gespeichert wird.
	 *	```
	 *	\nn\t3::Request()->getUri();
	 *	```
	 * 	@return string
	 */
	public function getUri ( $varName = null ) {
		return GeneralUtility::getIndpEnv('REQUEST_URI');
	}

	/**
	 * Sendet einen POST Request (per CURL) an einen Server.
	 * ```
	 * \nn\t3::Request()->POST( 'https://...', ['a'=>'123'] );
	 * \nn\t3::Request()->POST( 'https://...', ['a'=>'123'], ['Accept-Encoding'=>'gzip, deflate'] );
	 * ```
	 * @param string $url
	 * @param array $postData
	 * @param array $headers
	 * @return array
	 */
	public function POST( $url = '', $postData = [], $headers = [], $requestType = 'POST' ) {

		// ['Accept-Encoding'=>'gzip'] --> ['Accept-Encoding: gzip']
		array_walk( $headers, function (&$v, $k) {
			if (!is_numeric($k)) $v = $k . ': ' . $v;
		});
		
		if (is_array($postData)) {
			$postData = http_build_query($postData);
		}

		$headers = array_values($headers);
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData );

		// follow redirects
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestType);
		
		$headers[] = 'Content-Type: application/x-www-form-urlencoded';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		curl_close($ch);

		if ($httpcode >= 300) {
			return [
				'error'		=> true,
				'status' 	=> $httpcode,
				'content'	=> $error,
				'response'	=> @json_decode($result, true) ?: $result,
			];
		}
		
		return [
			'error'		=> false,
			'status' 	=> 200, 
			'content' 	=> $result
		];
	}

	/**
	 * Sendet ein JSON per POST an einen Server
	 * 
	 * ```
	 * \nn\t3::Request()->JSON( 'https://...', ['a'=>'123'] );
	 * ```
	 *  
	 * @param string $url
	 * @param array $data
	 * @param array $headers
	 * @return array
	 */
   	public function JSON( $url = '', $data = [], $headers = [] ) 
	{
		return $this->POST( $url, json_encode($data), $headers );
   	}

	/**
	 * Sendet einen GET Request (per curl) an einen Server
	 * ```
	 * \nn\t3::Request()->GET( 'https://...', ['a'=>'123'] );
	 * \nn\t3::Request()->GET( 'https://...', ['a'=>'123'], ['Accept-Encoding'=>'gzip, deflate'] );
	 * 
	 * // falls 'a'=>[1,2,3] als a=1&a=2&a=3 gesendet werdne soll, statt a[]=1&a[]=2&a[]=3
	 *  \nn\t3::Request()->GET( 'https://...', ['a'=>[1,2,3]], [], true );
	 * ```
	 * @param string $url
	 * @param array $queryParams
	 * @param array $headers
	 * @return array
	 */
	public function GET( $url = '', $queryParams = [], $headers = [], $dontNestArrays = false ) {

		// ['Accept-Encoding'=>'gzip'] --> ['Accept-Encoding: gzip']
		array_walk( $headers, function (&$v, $k) {
			if (!is_numeric($k)) $v = $k . ': ' . $v;
		});

		$headers = array_values($headers);
		$url = $this->mergeGetParams($url, $queryParams, $dontNestArrays);
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers );

		// follow redirects
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		$result = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		curl_close($ch);

		if ($httpcode >= 300) {
			return [
				'error'		=> true,
				'status' 	=> $httpcode,
				'content'	=> $error,
				'response'	=> @json_decode($result, true) ?: $result,
			];
		}
		
		return [
			'error'		=> false,
			'status' 	=> 200, 
			'content' 	=> $result,
		];
	}
	
	/**
	 * Sendet einen DELETE Request (per curl) an einen Server
	 * ```
	 * \nn\t3::Request()->DELETE( 'https://...', ['a'=>'123'] );
	 * \nn\t3::Request()->DELETE( 'https://...', ['a'=>'123'], ['Accept-Encoding'=>'gzip, deflate'] );
	 * ```
	 * @param string $url
	 * @param array $queryParams
	 * @param array $headers
	 * @return array
	 */
	public function DELETE( $url = '', $queryParams = [], $headers = [] ) {

		// ['Accept-Encoding'=>'gzip'] --> ['Accept-Encoding: gzip']
		array_walk( $headers, function (&$v, $k) {
			if (!is_numeric($k)) $v = $k . ': ' . $v;
		});

		$headers = array_values($headers);
		$url = $this->mergeGetParams($url, $queryParams);
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers );

		// follow redirects
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		$result = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		curl_close($ch);

		if ($httpcode >= 300) {
			return [
				'error'		=> true,
				'status' 	=> $httpcode,
				'content'	=> $error,
				'response'	=> @json_decode($result, true) ?: $result,
			];
		}
		
		return [
			'error'		=> false,
			'status' 	=> 200, 
			'content' 	=> $result,
		];
	}
	
	/**
	 * Sendet einen PUT Request (per curl) an einen Server
	 * ```
	 * \nn\t3::Request()->PUT( 'https://...', ['a'=>'123'] );
	 * \nn\t3::Request()->PUT( 'https://...', ['a'=>'123'], ['Accept-Encoding'=>'gzip, deflate'] );
	 * ```
	 * @param string $url
	 * @param array $data
	 * @param array $headers
	 * @return array
	 */
	public function PUT( $url = '', $data = [], $headers = [] ) 
	{
		return $this->POST( $url, $data, $headers, 'PUT' );
	}

	/**
	 * Sendet einen PUT Request (per curl) an einen Server als JSON
	 * ```
	 * \nn\t3::Request()->PUT_JSON( 'https://...', ['a'=>'123'] );
	 * \nn\t3::Request()->PUT_JSON( 'https://...', ['a'=>'123'], ['Accept-Encoding'=>'gzip, deflate'] );
	 * ```
	 * @param string $url
	 * @param array $data
	 * @param array $headers
	 * @return array
	 */
	public function PUT_JSON( $url = '', $data = [], $headers = [] ) 
	{
		return $this->POST( $url, json_encode($data), $headers, 'PUT' );
	}

	/**
	 * ```
	 * \nn\t3::Request()->mergeGetParams( 'https://...', ['a'=>[1,2,3]] )
	 * 
	 * // falls 'a'=>[1,2,3] als a=1&a=2&a=3 gesendet werden soll, statt a[]=1&a[]=2&a[]=3
	 * \nn\t3::Request()->mergeGetParams( 'https://...', ['a'=>[1,2,3]], true )
	 * ```
	 * @param string $url
	 * @param array $getParams
	 * @param bool $dontNestArrays
	 * @return string
	 */
	public function mergeGetParams( $url = '', $getParams = [], $dontNestArrays = false ) 
	{
		$parts = parse_url($url);
		$getP = [];
		if ($parts['query'] ?? false) {
			parse_str($parts['query'], $getP);
		}

		ArrayUtility::mergeRecursiveWithOverrule($getP, $getParams, true, true );
		$uP = explode('?', $url);

		$params = GeneralUtility::implodeArrayForUrl('', $getP);

		if ($dontNestArrays) {
			$params = preg_replace('/\[[0-9]*\]/', '', $params);
		}

		$outurl = $uP[0] . ($params ? '?' . substr($params, 1) : '');
		return $outurl;
	}

	/** 
	 * Den Authorization-Header aus dem Request auslesen.
	 * ```
	 * \nn\t3::Request()->getAuthorizationHeader();
	 * ```
	 * Wichtig: Wenn das hier nicht funktioniert, fehlt in der .htaccess 
	 * wahrscheinlich folgende Zeile:
	 * ```
	 * # nnhelpers: Verwenden, wenn PHP im PHP-CGI-Mode ausgeführt wird
	 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
	 * ```
	 * @return string
	 */
	public function getAuthorizationHeader(){

		$headers = null;
		if (isset($_SERVER['Authorization'])) {
			$headers = trim($_SERVER['Authorization']);
		} else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
			$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
		} elseif (function_exists('apache_request_headers')) {
			$requestHeaders = apache_request_headers();
			foreach ($requestHeaders as $k=>$v) {
				$requestHeaders[ucwords($k)] = $v;
			}
			if (isset($requestHeaders['Authorization'])) {
				$headers = trim($requestHeaders['Authorization']);
			}
		}

		return $headers;
	}

	/**
	 * Den Basic Authorization Header aus dem Request auslesen.
	 * Falls vorhanden, wird der Username und das Passwort zurückgeben.
	 * ```
	 * $credentials = \nn\t3::Request()->getBasicAuth(); // ['username'=>'...', 'password'=>'...']
	 * ```
	 * Beispiel-Aufruf von einem Testscript aus:
	 * ```
	 * echo file_get_contents('https://username:password@www.testsite.com');
	 * ```
	 * @return array
	 */
	public function getBasicAuth() {

		$username = '';
		$password = '';

		if (isset($_SERVER['PHP_AUTH_USER'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];
		} else {
			$check = ['HTTP_AUTHENTICATION', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'];
			foreach ($check as $key) {
				$value = $_SERVER[$key] ?? false;
				$isBasic = strpos(strtolower($value), 'basic') === 0;
				if ($value && $isBasic) {
					$decodedValue = base64_decode(substr($value, 6));
					[$username, $password] = explode(':', $decodedValue) ?: ['', ''];
					break;
				}
			}
		}

		if (!$username && !$password) return [];
		return ['username'=>$username, 'password'=>$password];
	}

	/**
	 * Den `Bearer`-Header auslesen.
	 * Wird u.a. verwendet, um ein JWT (Json Web Token) zu übertragen.
	 * ```
	 * \nn\t3::Request()->getBearerToken();
	 * ```
	 * @return string|null
	 */
	public function getBearerToken() {
		$headers = $this->getAuthorizationHeader();
		if (!empty($headers)) {
			if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
				return $matches[1];
			}
		}
		return null;
	}

	/**
	 * Den JWT (Json Web Token) aus dem Request auslesen, validieren und bei
	 * erfolgreichem Prüfen der Signatur den Payload des JWT zurückgeben.
	 * ```
	 * \nn\t3::Request()->getJwt();
	 * ```
	 * @return array|string
	 */
	public function getJwt() {
		$jwt = $this->getBearerToken();
		return \nn\t3::Encrypt()->parseJwt($jwt);
	}
}