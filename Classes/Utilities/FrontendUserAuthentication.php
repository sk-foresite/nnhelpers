<?php
namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Frontend-User Methoden: Von Einloggen bis Passwort-Änderung
 */
class FrontendUserAuthentication extends \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication {

	/**
     * @var string
     */
	public $id = '';

	/**
     * @var bool
     */
	public $checkPid = false;
	
	
	/**
	 * Login eines FE-Users anhand der Usernamens und Passwortes
	 * ```
	 * // Credentials überprüfen und feUser-Session starten
	 * \nn\t3::FrontendUserAuthentication()->login( '99grad', 'password' );
	 * 
	 * // Nur überprüfen, keine feUser-Session aufbauen
	 * \nn\t3::FrontendUserAuthentication()->login( '99grad', 'password', false );
	 * ```
	 * @return array
	 */
	public function login( $username = '', $password = '', $startFeUserSession = true ) {

		if (!trim($password) || !trim($username)) return [];

		$user = \nn\t3::Db()->findByValues( 'fe_users', ['username'=>$username] );

		if (!$user) return [];
		if (count($user) > 1) return [];

		$user = array_pop($user);
		if (!\nn\t3::Encrypt()->checkPassword($password, $user['password'])) {
			return [];
		}

		if (!$startFeUserSession) {
			return $user;
		}

		$request = &$GLOBALS['TYPO3_REQUEST'];
		if (!$request) $request = new \TYPO3\CMS\Core\Http\ServerRequest();

		$info = $this->getAuthInfoArray($request);
		$info['db_user']['username_column'] = 'username';

		$feUser = $this->setSession( $user );
		return $feUser;
	}
	
	
	/**
	 * Login eines FE-Users anhand eines beliebigen Feldes.
	 * Kein Passwort erforderlich.
	 * ```
	 * \nn\t3::FrontendUserAuthentication()->loginField( $value, $fieldName );
	 * ```
	 * @return array
	 */
	public function loginField( $value = null, $fieldName = 'uid') {

		if (!$value) return [];

		$user = \nn\t3::Db()->findByValues( 'fe_users', [$fieldName => $value] );
		if (!$user) return [];
		if (!count($user) > 1) return [];
		
		$user = array_pop($user);

		$request = &$GLOBALS['TYPO3_REQUEST'];
		if (!$request) $request = new \TYPO3\CMS\Core\Http\ServerRequest();
		$info = $this->getAuthInfoArray($request);
		$info['db_user']['username_column'] = 'username';

		$feUser = $this->setSession( $user );
		return $feUser;
	}
	
	/**
	 * 	Login eines FE-Users anhand einer fe_user.uid
	 *	```
	 *	\nn\t3::FrontendUserAuthentication()->loginUid( 1 );
	 *	```
	 * 	@return array
	 */
	public function loginUid( $uid = null ) {

		$uid = intval($uid);
		if (!$uid) return [];

		$user = \nn\t3::Db()->findByUid( 'fe_users', $uid );
		if (!$user) return [];

		$request = &$GLOBALS['TYPO3_REQUEST'];
		if (!$request) $request = new \TYPO3\CMS\Core\Http\ServerRequest();
		$info = $this->getAuthInfoArray($request);
		$info['db_user']['username_column'] = 'username';

		$feUser = $this->setSession( $user );
		return $feUser;
	}
	
	/**
	 * Eine neue FrontenUser-Session in der Tabelle `fe_sessions` anlegen.
	 * Es kann wahlweise die `fe_users.uid` oder der `fe_users.username` übergeben werden.
	 * 
	 * Der User wird dabei nicht automatisch eingeloggt. Stattdessen wird nur eine gültige Session
	 * in der Datenbank angelegt und vorbereitet, die Typo3 später zur Authentifizierung verwenden kann.
	 * 
	 * Gibt die Session-ID zurück.
	 * 
	 * Die Session-ID entspricht hierbei exakt dem Wert im `fe_typo_user`-Cookie - aber nicht zwingend dem 
	 * Wert, der in `fe_sessions.ses_id` gespeichert wird. Der Wert in der Datenbank wird ab TYPO3 v11
	 * gehashed.
	 * 
	 * ```
	 * $sessionId = \nn\t3::FrontendUserAuthentication()->prepareSession( 1 );
	 * $sessionId = \nn\t3::FrontendUserAuthentication()->prepareSession( 'david' );
	 * 
	 * $hashInDatabase = \nn\t3::Encrypt()->hashSessionId( $sessionId );
	 * ```
	 * 
	 * Falls die Session mit einer existierenden SessionId erneut aufgebaut werden soll, kann als optionaler,
	 * zweiter Parameter eine (nicht-gehashte) SessionId übergeben werden:
	 * 
	 * ```
	 * \nn\t3::FrontendUserAuthentication()->prepareSession( 1, 'meincookiewert' );
	 * \nn\t3::FrontendUserAuthentication()->prepareSession( 1, $_COOKIE['fe_typo_user'] );
	 * ```
	 * 
	 * @return string
	 */
	public function prepareSession( $usernameOrUid = null, $unhashedSessionId = null ) {

		if (!$usernameOrUid) return null;

		if ($uid = intval($usernameOrUid)) {
			$user = \nn\t3::Db()->findByUid('fe_users', $uid);
		} else {
			$user = \nn\t3::Db()->findOneByValues('fe_users', ['username'=>$usernameOrUid]);
		}

		if (!$user) return null;
		
		if (!$unhashedSessionId) {
			$unhashedSessionId = $this->createSessionId();
		}
		$hashedSessionId = \nn\t3::Encrypt()->hashSessionId( $unhashedSessionId );
		
		$existingSession = \nn\t3::Db()->findOneByValues('fe_sessions', ['ses_id'=>$hashedSessionId]);

		if (!$existingSession) {
			$this->id = $hashedSessionId;
			$record = $this->elevateToFixatedUserSession($unhashedSessionId, $user['uid']);
			\nn\t3::Db()->insert('fe_sessions', $record);
		} else {
			\nn\t3::Db()->update('fe_sessions', ['ses_tstamp'=>$GLOBALS['EXEC_TIME']], ['ses_id'=>$hashedSessionId]);
		}

		$request = &$GLOBALS['TYPO3_REQUEST'];
		if (!$request) $request = new \TYPO3\CMS\Core\Http\ServerRequest();
		$this->start( $request );

		return $unhashedSessionId;
	}

	/**
	 * Login eines FE-Users anhand einer Session-ID.
	 * 
	 * Die Session-ID entspricht dem TYPO3 Cookie `fe_typo_user`. In der Regel gibt es für
	 * jede Fe-User-Session einen Eintrag in der Tabelle `fe_sessions`. Bis zu Typo3 v10 entsprach
	 * die Spalte `ses_id` exakt dem Cookie-Wert. 
	 * 
	 * Ab Typo3 v10 wird der Wert zusätzlich gehashed.
	 * 
	 * Siehe auch `\nn\t3::Encrypt()->hashSessionId( $sessionId );`
	 * ```
	 * \nn\t3::FrontendUserAuthentication()->loginBySessionId( $sessionId );
	 * ```
	 * @return array
	 */
	public function loginBySessionId( $sessionId = '' ) {
		if (!trim($sessionId)) return [];
		$sessionId = \nn\t3::Encrypt()->hashSessionId( $sessionId );
		$session = \nn\t3::Db()->findOneByValues( 'fe_sessions', ['ses_id'=>$sessionId] );
		if (!$session) return [];
		if ($feUserUid = $session['ses_userid']) {
			return $this->loginUid( $feUserUid );
		}
		return [];
	}


	/**
	 * 	Login eines FE-Users anhand der Usernamens
	 *	```
	 *	\nn\t3::FrontendUserAuthentication()->loginByUsername( '99grad' );
	 *	```
	 * 	@return array
	 */
	public function loginByUsername( $username = '' ) {

		if (!trim($username)) return [];

		$user = \nn\t3::Db()->findByValues( 'fe_users', ['username'=>$username] );
		if (!$user) return [];
		if (count($user) > 1) return [];
		$user = $user[0];

		$request = &$GLOBALS['TYPO3_REQUEST'];
		if (!$request) $request = new \TYPO3\CMS\Core\Http\ServerRequest();
		$info = $this->getAuthInfoArray($request);
		$info['db_user']['username_column'] = 'username';
		//$info['db_user']['username_column'] = 'email';

		$feUser = $this->setSession( $user );
		return $feUser;
	}


	/**
	 * Session-Data setzen, Gruppen-Daten holen
	 * @return void
	 */
	private function setSession($user_db) {

		$cookieName = \nn\t3::Environment()->getLocalConf('FE.cookieName') ?: 'fe_typo_user';
		
		if (!isset($GLOBALS['TSFE'])) {
			$request = &$GLOBALS['TYPO3_REQUEST'];
			if (!$request) $request = new \TYPO3\CMS\Core\Http\ServerRequest();
			$frontendUser = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication::class);
			$frontendUser->start( $request );

			$session = $frontendUser->createUserSession( $user_db );
			$sessionId = $session->getIdentifier();
			\nn\t3::FrontendUser()->setCookie( $sessionId );
			return $user_db;
		}

		$request = &$GLOBALS['TYPO3_REQUEST'];
		$frontendUser = $request->getAttribute('frontend.user');
		$userSession = $frontendUser->createUserSession($user_db);


		$userSessionManager = \TYPO3\CMS\Core\Session\UserSessionManager::create('FE');
		$fixatedUserSession = $userSessionManager->elevateToFixatedUserSession( $userSession, $user_db['uid'], true );
		$sessionId = $fixatedUserSession->getIdentifier();
		\nn\t3::FrontendUser()->setCookie( $sessionId );
		
		$frontendUser->user = $user_db;
		$frontendUser->fetchGroupData( $GLOBALS['TYPO3_REQUEST'] ?? null );

		$context = GeneralUtility::makeInstance(Context::class);
		$alternativeGroups = [];
		$userAspect = GeneralUtility::makeInstance(UserAspect::class, $frontendUser, $alternativeGroups);
		$context->setAspect('frontend.user', $userAspect);

		return $frontendUser->user;
	}
	
	/**
	 * Passwort eines FE-Users ändern.
	 * ```
	 * \nn\t3::FrontendUserAuthentication()->setPassword( 12, '123Passwort#$' );
	 * \nn\t3::FrontendUserAuthentication()->setPassword( $frontendUserModel, '123Passwort#$' );
	 * ```
	 * @return boolean
	 */
	public function setPassword( $feUserUid = null, $password = null ) {
		
		if (!$password || !$feUserUid) return false;
		if (!is_numeric($feUserUid)) $feUserUid = \nn\t3::Obj()->get( $feUserUid, 'uid' );

		$saltedPassword = \nn\t3::Encrypt()->password( $password );
		\nn\t3::Db()->update( 'fe_users', [
			'password' => $saltedPassword,
			'pwchanged' => time(),
		], $feUserUid);
		
		return true;
	}
}
