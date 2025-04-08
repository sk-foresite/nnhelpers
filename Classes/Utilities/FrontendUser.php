<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Session\UserSession;

use Firebase\JWT\JWT;
use TYPO3\CMS\Core\Security\JwtTrait;
use TYPO3\CMS\Core\Session\UserSessionManager;

class FrontendUser implements SingletonInterface 
{
	use JwtTrait;

	/**
	 * lokaler Cache
	 */
	protected $cache = [];
	
	/**
	 * @var \TYPO3\CMS\Core\Session\UserSession
	 */
	protected $userSession = null;

 	/**
	 * Die aktuelle User-Session holen.
	 * ```
	 * \nn\t3::FrontendUser()->getSession(); 
	 * ```
	 * @return \TYPO3\CMS\Core\Session\UserSession
	 */
	public function getSession() 
	{
		if ($session = $this->userSession) return $session;
		$request = &$GLOBALS['TYPO3_REQUEST'];
		$frontendUser = $request->getAttribute('frontend.user');
		if ($frontendUser) {
			return $frontendUser->getSession();
		}
		$userSessionManager = UserSessionManager::create('FE');
		$session = $userSessionManager->createFromRequestOrAnonymous($GLOBALS['TYPO3_REQUEST'], $this->getCookieName());

		return $this->userSession = $session;
	}
 	
 	/**
	 * Den aktuellen FE-User holen.
	 * Alias zu `\nn\t3::FrontendUser()->getCurrentUser();`
	 * ```
	 * \nn\t3::FrontendUser()->get(); 
	 * ```
	 * Existiert auch als ViewHelper:
	 * ```
	 * {nnt3:frontendUser.get(key:'first_name')}
 	 * {nnt3:frontendUser.get()->f:variable.set(name:'feUser')}
	 * ```
	 * @return array
	 */
	public function get() {
		return $this->getCurrentUser();
	}
 	
	/**
	 * Benutzergruppen des aktuellen FE-User holen.
	 * Alias zu `\nn\t3::FrontendUser()->getCurrentUserGroups();`
	 * ```
	 * // nur title, uid und pid der Gruppen laden
	 * \nn\t3::FrontendUser()->getGroups();
	 * // kompletten Datensatz der Gruppen laden
	 * \nn\t3::FrontendUser()->getGroups( true ); 
	 * ```
	 * @return array
	 */
	public function getGroups( $returnRowData = false ) {
		return $this->getCurrentUserGroups( $returnRowData );
	}

	/**
	 * Array mit den Daten des aktuellen FE-Users holen.
	 * ```
	 * \nn\t3::FrontendUser()->getCurrentUser(); 
	 * ```
	 * @return array
	 */
	public function getCurrentUser() {
		if (!$this->isLoggedIn()) return [];

		// Wenn wir ein Frontend haben, sind die fe_user-Daten global und vollständig im TSFE gespeichert
		$request = &$GLOBALS['TYPO3_REQUEST'];
		$frontendUser = $request->getAttribute('frontend.user');

		if ($frontendUser) {
			return $frontendUser->user ?? [];
		}

		// Ohne Frontend könnten wir uns z.B. in einer Middleware befinden. Nach AUTH sind die Daten evtl im Aspect.
		$context = GeneralUtility::makeInstance(Context::class);
		$userAspect = $context->getAspect('frontend.user');
		if (!$userAspect) return [];

        /** @var ?ServerRequest $request */
        if ($request = ($GLOBALS['TYPO3_REQUEST'] ?? null)) {
            return $request->getAttribute('frontend.user')->user;
        }

		$usergroupUids = array_column($this->resolveUserGroups( $userAspect->get('groupIds') ), 'uid');

		// Daten zu Standard-Darstellung normalisieren
		return [
			'uid'			=> $userAspect->get('id'),
			'username'		=> $userAspect->get('username'),
			'usergroup'		=> join(',', $usergroupUids)
		] ?? [];
	}
	
	/**
	 * Wandelt ein Array oder eine kommaseparierte Liste mit Benutzergrupen-UIDs in 
	 * `fe_user_groups`-Daten aus der Datenbank auf. Prüft auf geerbte Untergruppe.
	 * ```
	 * \nn\t3::FrontendUser()->resolveUserGroups( [1,2,3] );
	 * \nn\t3::FrontendUser()->resolveUserGroups( '1,2,3' );
	 * ```
	 * @return array
	 */
	public function resolveUserGroups( $arr = [], $ignoreUids = [] ) {

		$arr = \nn\t3::Arrays( $arr )->intExplode();
		if (!$arr) return [];

		return GeneralUtility::makeInstance(\TYPO3\CMS\Core\Authentication\GroupResolver::class)->resolveGroupsForUser(['usergroup'=>join(',', $arr)], 'fe_groups');
	}

	/**
	 * Benutzergruppen des aktuellen FE-Users als Array holen.
	 * Die uids der Benutzergruppen werden im zurückgegebenen Array als Key verwendet. 
	 * ```
	 * // Minimalversion: Per default gibt Typo3 nur title, uid und pid zurück
	 * \nn\t3::FrontendUser()->getCurrentUserGroups();			// [1 => ['title'=>'Gruppe A', 'uid' => 1, 'pid'=>5]] 
	 * 
	 * // Mit true kann der komplette Datensatz für die fe_user_group aus der DB gelesen werden
	 * \nn\t3::FrontendUser()->getCurrentUserGroups( true );	// [1 => [... alle Felder der DB] ] 
	 * ```
	 * @return array
	 */
	public function getCurrentUserGroups( $returnRowData = false ) {
		if (!$this->isLoggedIn()) return [];

		$request = &$GLOBALS['TYPO3_REQUEST'];
		$frontendUser = $request->getAttribute('frontend.user');

		if ($frontendUser) {

			// Wenn wir ein Frontend haben...
			$rawGroupData = $frontendUser->groupData;
			$groupDataByUid = [];
			foreach ($rawGroupData['uid'] as $i=>$uid) {
				$groupDataByUid[$uid] = [];
				if ($returnRowData) {
					$groupDataByUid[$uid] = \nn\t3::Db()->findByUid('fe_groups', $uid);
				}
				foreach ($rawGroupData as $field=>$arr) {
					$groupDataByUid[$uid][$field] = $arr[$i];
				}
			}
			return $groupDataByUid;	

		}

		// ... oder in einem Kontext ohne Frontend sind (z.B. einer Middleware)
		$context = GeneralUtility::makeInstance(Context::class);
		$userAspect = $context->getAspect('frontend.user');
		if (!$userAspect) return [];
		$userGroups = $this->resolveUserGroups($userAspect->get('groupIds'));
		if ($returnRowData) {
			return \nn\t3::Arrays($userGroups)->key('uid')->toArray() ?: [];
		} else {
			return \nn\t3::Arrays($userGroups)->key('uid')->pluck(['uid', 'title', 'pid'])->toArray();
		}

		return [];
	}
	
	/**
	 * Prüft, ob der aktuelle Frontend-User innerhalb einer bestimmte Benutzergruppe ist.
	 * ```
	 * \nn\t3::FrontendUser()->isInUserGroup( 1 );
	 * \nn\t3::FrontendUser()->isInUserGroup( ObjectStorage<FrontendUserGroup> );
	 * \nn\t3::FrontendUser()->isInUserGroup( [FrontendUserGroup, FrontendUserGroup, ...] );
	 * \nn\t3::FrontendUser()->isInUserGroup( [['uid'=>1, ...], ['uid'=>2, ...]] );
	 * ```
	 * @return boolean
	 */
	public function isInUserGroup( $feGroups = null ) {
		if (!$this->isLoggedIn()) return false;
		$groupsByUid = $this->getCurrentUserGroups();
		$feGroupUids = [];
		if (is_int( $feGroups)) {
			$feGroupUids = [$feGroups];
		} else {
			foreach ($feGroups as $obj) {
				$uid = false;
				if (is_numeric($obj)) $uid = $obj;
				if (is_array($obj) && isset($obj['uid'])) $uid = $obj['uid'];
				if (is_object($obj) && method_exists($obj, 'getUid')) $uid = $obj->getUid();
				if ($uid) $feGroupUids[] = $uid;
			}
		}
		$matches = array_intersect( array_keys($groupsByUid), $feGroupUids );
		return count($matches) > 0;
	}
	
	/**
	 * Alle existierende User-Gruppen zurückgeben.
	 * Gibt ein assoziatives Array zurück, key ist die `uid`, value der `title`.
	 * ```
	 * \nn\t3::FrontendUser()->getAvailableUserGroups();
	 * ```
	 * Alternativ kann mit `true` der komplette Datensatz für die Benutzergruppen 
	 * zurückgegeben werden: 
	 * ```
	 * \nn\t3::FrontendUser()->getAvailableUserGroups( true );
	 * ```
	 * @return array
	 */
	public function getAvailableUserGroups( $returnRowData = false ) {

		if (!($userGroupsByUid = $this->cache['userGroupsByUid'] ?? false)) {
			$userGroups = \nn\t3::Db()->findAll('fe_groups');			
			$userGroupsByUid = \nn\t3::Arrays( $userGroups )->key('uid');
			$userGroupsByUid = $this->cache['userGroupsByUid'] = $userGroupsByUid->toArray();
		}

		if ($returnRowData) {
			return $userGroupsByUid;
		}
		
		return \nn\t3::Arrays($userGroupsByUid)->pluck('title')->toArray();
	}

	/**
	 * Prüft, ob der User aktuell als FE-User eingeloggt ist.
	 * Früher: isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->loginUser
	 * ```
	 * // Prüfen nach vollständiger Initialisierung des Front/Backends
	 * \nn\t3::FrontendUser()->isLoggedIn();
	 * 
	 * // Prüfen anhand des JWT, z.B. in einem eID-script vor Authentifizierung
	 * \nn\t3::FrontendUser()->isLoggedIn( $request );
	 * ```
	 * @param ServerRequest $request
	 * @return boolean
	 */
	public function isLoggedIn( $request = null ) {

		if ($request) {
			$cookieName = $this->getCookieName();
			$jwt = $request->getCookieParams()[$cookieName] ?? false;
			$identifier = false;
			if ($jwt) {
				try {
					$identifier = \TYPO3\CMS\Core\Session\UserSession::resolveIdentifierFromJwt($jwt);
				} catch( \Exception $e ) {}
			}
			if ($identifier) return true;
		}

		// Context `frontend.user.isLoggedIn` scheint in Middleware nicht zu gehen. Fallback auf TSFE. 
		$request = &$GLOBALS['TYPO3_REQUEST'];
		$frontendUser = $request->getAttribute('frontend.user');
		$loginUserFromTsfe = isset($frontendUser) && isset($frontendUser->user['uid']);

		$context = GeneralUtility::makeInstance(Context::class);
		return $context->getPropertyFromAspect('frontend.user', 'isLoggedIn') || $loginUserFromTsfe;
	}

	/**
	 * UID des aktuellen Frontend-Users holen
	 * ```
	 * $uid = \nn\t3::FrontendUser()->getCurrentUserUid();
	 * ```
	 * @return int
	 */
	public function getCurrentUserUid(){
		if (!($user = $this->getCurrentUser())) return null;
		return $user['uid'];
	}

	/**
	 * Session-ID des aktuellen Frontend-Users holen
	 * ```
	 * $sessionId = \nn\t3::FrontendUser()->getSessionId();
	 * ```
	 * @return string
	 */
	public function getSessionId()
	{
		if ($session = $this->getSession()) {
			if ($sessionId = $session->getIdentifier()) {
				return $sessionId;
			}
		}
		return $_COOKIE[$this->getCookieName()] ?? null;
	}

	/**
	 * Sprach-UID des aktuellen Users holen
	 * ```
	 * $languageUid = \nn\t3::FrontendUser()->getLanguage();
	 * ```
	 * @return int
	 */
	public function getLanguage(){
		return \nn\t3::Environment()->getLanguage();
	}

	/**
	 * Prüft, ob der User eine bestimmte Rolle hat.
	 * ```
	 * \nn\t3::FrontendUser()->hasRole( $roleUid );
	 * ```
	 * @param $role
	 * @return bool
	 */
	public function hasRole($roleUid){
		if (!$this->isLoggedIn()) return false;
		$userGroupsByUid = $this->getCurrentUserGroups();
		return $userGroupsByUid[$roleUid] ?? false;
	}

	/**
	 * User manuell einloggen.
	 * ab v10: Alias zu `\nn\t3::FrontendUserAuthentication()->loginByUsername( $username );`
	 * ```
	 * \nn\t3::FrontendUser()->login('99grad');
	 * ```
	 * @param $username
	 * @param $password
	 * @throws \ReflectionException
	 */
	public function login( $username, $password = null )
	{
		if ($password !== null) {
			die('Please use \nn\t3::FrontendUserAuthentication()->login()');
		}
		$user = \nn\t3::FrontendUserAuthentication()->loginByUsername( $username );		
		return $user ?: [];
	}
	
	/**
	 * Aktuellen FE-USer manuell ausloggen
	 * ```
	 * \nn\t3::FrontendUser()->logout();
	 * ```
	 * @return void
	 */
	public function logout() {
		if (!$this->isLoggedIn()) return false;
		
		// In der MiddleWare ist der FE-User evtl. noch nicht initialisiert...
		$request = &$GLOBALS['TYPO3_REQUEST'];
		$frontendUser = $request->getAttribute('frontend.user');
		if ($frontendUser && $frontendUser->logoff) {
			$frontendUser->logoff();
		}	


		// Session-Daten aus Tabelle `fe_sessions` löschen
		if ($sessionManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Session\SessionManager::class)) {
			$sessionBackend = $sessionManager->getSessionBackend('FE');
			if ($sessionId = $this->getSessionId()) {
				$sessionBackend->remove( $sessionId );	
			}
		}

		// ... aber Cookie löschen geht immer!
		$this->removeCookie();

		// ToDo: Replace with Signal/Slot when deprecated
		if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['logout_confirmed'] ?? false) {
			$_params = array();
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['logout_confirmed'] as $_funcRef) {
				if ($_funcRef) GeneralUtility::callUserFunction($_funcRef, $_params, $this);
			}
		}
	}

	/**
	 * Passwort eines FE-Users ändern.
	 * Alias zu `\nn\t3::FrontendUserAuthentication()->setPassword()`.
	 * ```
	 * \nn\t3::FrontendUser()->setPassword( 12, '123passwort$#' );
	 * \nn\t3::FrontendUser()->setPassword( $frontendUserModel, '123Passwort#$' );
	 * ```
	 * @return boolean
	 */
	public function setPassword( $feUserUid = null, $password = null ) {
		return \nn\t3::FrontendUserAuthentication()->setPassword( $feUserUid, $password );
	}

	/**
	 * Cookie-Name des Frontend-User-Cookies holen.
	 * Üblicherweise `fe_typo_user`, außer es wurde in der LocalConfiguration geändert.
	 * ```
	 * \nn\t3::FrontendUser()->getCookieName();
	 * ```
	 * return string
	 */
	public function getCookieName() {
		if ($cookieName = $GLOBALS['TYPO3_CONF_VARS']['FE']['cookieName'] ?? false) {
			return $cookieName;
		}
		return \nn\t3::Environment()->getLocalConf('FE.cookieName');
	}

	/**
	 * Aktuellen `fe_typo_user`-Cookie manuell löschen
	 * ```
	 * \nn\t3::FrontendUser()->removeCookie()
	 * ```
	 * @return void
	 */
	public function removeCookie() {
		$cookieName = $this->getCookieName();
		\nn\t3::Cookies()->add( $cookieName, '', -1 );
	}

	/**
	 * Den `fe_typo_user`-Cookie manuell setzen.
	 * 
	 * Wird keine `sessionID` übergeben, sucht Typo3 selbst nach der Session-ID des FE-Users.
	 * 
	 * Bei Aufruf dieser Methode aus einer MiddleWare sollte der `Request` mit übergeben werden.
	 * Dadurch kann z.B. der globale `$_COOKIE`-Wert und der `cookieParams.fe_typo_user` im Request 
	 * vor Authentifizierung über `typo3/cms-frontend/authentication` in einer eigenen MiddleWare
	 * gesetzt werden. Hilfreich, falls eine Crossdomain-Authentifizierung erforderlich ist (z.B.
	 * per Json Web Token / JWT).
	 * 
	 * ```
	 * \nn\t3::FrontendUser()->setCookie();
	 * \nn\t3::FrontendUser()->setCookie( $sessionId );
	 * \nn\t3::FrontendUser()->setCookie( $sessionId, $request );
	 * ```
	 * @return void
	 */
	public function setCookie( $sessionId = null, &$request = null ) 
	{
		if (!$sessionId) {
			$sessionId = $this->getSessionId();
		}

		$jwt = self::encodeHashSignedJwt(
            [
                'identifier' => $sessionId,
                'time' => (new \DateTimeImmutable())->format(\DateTimeImmutable::RFC3339),
            ],
            self::createSigningKeyFromEncryptionKey(UserSession::class)
        );

		$cookieName = $this->getCookieName();
		$_COOKIE[$cookieName] = $jwt;
		\nn\t3::Cookies()->add( $cookieName, $jwt );
	}

	/**
	 * Holt den aktuellen `fe_typo_user` Cookie.
	 * ```
	 * $cookie = \nn\t3::FrontendUser()->getCookie();
	 * ```
	 * @return string
	 */
	public function getCookie() 
	{
		$cookieName = $this->getCookieName();

		if ($request = &$GLOBALS['TYPO3_REQUEST']) {
			$cookieParams = $request->getCookieParams();
			if ($value = $cookieParams[$cookieName] ?? false) {
				return $value;
			}
		}

		if ($cookie = $_COOKIE[$cookieName] ?? false) {
			return $cookie;
		}

		if ($cookies = \nn\t3::Cookies()->getAll()) {
			return $cookies[$cookieName]['value'] ?? '';
		}

		return '';
	}

	/**
	 * Session-Data für FE-User holen
	 * ```
	 * \nn\t3::FrontendUser()->getSessionData('shop')
	 * ```
	 * @return mixed
	 */
	public function getSessionData( $key = null ) 
	{	
		$session = $this->getSession();
		if (!$session) return $key ? '' : [];
		return $session->get( $key ) ?: [];
	}

	/**
	 * Session-Data für FE-User setzen
	 * ```
	 * // Session-data für `shop` mit neuen Daten mergen (bereits existierende keys in `shop` werden nicht gelöscht)
	 * \nn\t3::FrontendUser()->setSessionData('shop', ['a'=>1]);
	 * 
	 * // Session-data für `shop` überschreiben (`a` aus dem Beispiel oben wird gelöscht)
	 * \nn\t3::FrontendUser()->setSessionData('shop', ['b'=>1], false);
	 * ```
	 * @return mixed
	 */
	public function setSessionData( $key = null, $val = null, $merge = true ) 
	{
		$session = $this->getSession();
		$sessionData = $merge ? $this->getSessionData( $key ) : [];

		if (is_array($val)) {
			ArrayUtility::mergeRecursiveWithOverrule( $sessionData, $val );
		} else {
			$sessionData = $val;
		}
		$session->set( $key, $sessionData );
		$request = &$GLOBALS['TYPO3_REQUEST'];
		$frontendUser = $request->getAttribute('frontend.user');
		$frontendUser->storeSessionData();
		return $sessionData;
	}

}
