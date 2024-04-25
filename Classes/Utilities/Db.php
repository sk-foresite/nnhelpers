<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Storage\Typo3DbQueryParser;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * Zugriff auf die meist genutzten Datenbank-Operationen für Schreiben, Lesen, Löschen vereinfachen.
 */
class Db implements SingletonInterface 
{	
	/**
	 * QueryBuilder für eine Tabelle holen
	 * ```
	 * $queryBuilder = \nn\t3::Db()->getQueryBuilder( 'fe_users' );
	 * ```
	 * Beispiel:
	 * ```
	 * $queryBuilder = \nn\t3::Db()->getQueryBuilder( 'fe_users' );
	 * $queryBuilder->select('name')->from( 'fe_users' );
	 * $queryBuilder->andWhere( $queryBuilder->expr()->eq( 'uid', $queryBuilder->createNamedParameter(12) ));
	 * $rows = $queryBuilder->executeStatement()->fetchAllAssociative();
	 * ```
	 * @param string $table
	 * @return QueryBuilder
	 */
	public function getQueryBuilder( $table = '' ) 
	{
		$queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable( $table );
		return $queryBuilder;
	}

	/**
	 * Eine "rohe" Verbindung zur Datenbank holen.
	 * Nur in wirklichen Ausnahmefällen sinnvoll.
	 * ```
	 * $connection = \nn\t3::Db()->getConnection();
	 * $connection->fetchAll( 'SELECT * FROM tt_news WHERE 1;' );
	 * ```
	 * @return \TYPO3\CMS\Core\Database\Connection
	 */
	public function getConnection() 
	{
		$connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
		$connectionName = array_shift($connectionPool->getConnectionNames());
		return $connectionPool->getConnectionByName( $connectionName );
	}

	/**
	 * Ein oder mehrere Domain-Model/Entity anhand einer `uid` holen.
	 * Es kann eine einzelne `$uid` oder eine Liste von `$uids` übergeben werden.
	 * 
	 * Liefert das "echte" Model/Object inklusive aller Relationen, 
	 * analog zu einer Query über das Repository.
	 * 
	 * ```
	 * // Ein einzelnes Model anhand seiner uid holen
	 * $model = \nn\t3::Db()->get( 1, \Nng\MyExt\Domain\Model\Name::class );
	 * 
	 * // Ein Array an Models anhand ihrer uids holen
	 * $modelArray = \nn\t3::Db()->get( [1,2,3], \Nng\MyExt\Domain\Model\Name::class );
	 * 
	 * // Gibt auch hidden Models zurück
	 * $modelArrayWithHidden = \nn\t3::Db()->get( [1,2,3], \Nng\MyExt\Domain\Model\Name::class, true );
	 * ```
	 * @param int $uid
	 * @param string $modelType
	 * @param boolean $ignoreEnableFields
	 * @return Object
	 */
	public function get( $uid, $modelType = '', $ignoreEnableFields = false) 
	{
		if (!is_array($uid)) {
			$persistenceManager = \nn\t3::injectClass( PersistenceManager::class );
			$entity = $persistenceManager->getObjectByIdentifier($uid, $modelType, false);
			return $entity;
		}

		$dataMapper = \nn\t3::injectClass(DataMapper::class);
		$tableName = $this->getTableNameForModel( $modelType);
		$rows = $this->findByUids( $tableName, $uid, $ignoreEnableFields ); 
		
		return $dataMapper->map( $modelType, $rows);
	}

	/**
	 * Findet einen Eintrag anhand der UID.
	 * Funktioniert auch, wenn	Frontend noch nicht initialisiert wurden,
	 * z.B. während AuthentificationService läuft oder im Scheduler.
	 * ```
	 * \nn\t3::Db()->findByUid('fe_user', 12);
	 * \nn\t3::Db()->findByUid('fe_user', 12, true);
	 * ```
	 * @param string $table
	 * @param int $uid
	 * @param boolean $ignoreEnableFields
	 * @return array
	 */
	public function findByUid( $table = '', $uid = null, $ignoreEnableFields = false ) 
	{
		$rows = $this->findByValues( $table, ['uid' => $uid], false, $ignoreEnableFields );
		return $rows ? array_shift($rows) : [];
	}
	
	/**
	 * Findet Einträge anhand mehrerer UIDs.
	 * ```
	 * \nn\t3::Db()->findByUids('fe_user', [12,13]);
	 * \nn\t3::Db()->findByUids('fe_user', [12,13], true);
	 * ```
	 * @param string $table
	 * @param int|array $uids
	 * @param boolean $ignoreEnableFields
	 * @return array
	 */
	public function findByUids( $table = '', $uids = null, $ignoreEnableFields = false ) 
	{
		if (!$uids) return [];
		$rows = $this->findByValues( $table, ['uid' => $uids], false, $ignoreEnableFields );
		return $rows;
	}
	
	/**
	 * Holt ALLE Eintrag aus einer Datenbank-Tabelle.
	 * 
	 * Die Daten werden als Array zurückgegeben – das ist (leider) noch immer die absolute
	 * performanteste Art, viele Datensätze aus einer Tabelle zu holen, da kein `DataMapper`
	 * die einzelnen Zeilen parsen muss.
	 * 
	 * ```
	 * // Alle Datensätze holen. "hidden" wird berücksichtigt.
	 * \nn\t3::Db()->findAll('fe_users');
	 * 
	 * // Auch Datensätze holen, die "hidden" sind
	 * \nn\t3::Db()->findAll('fe_users', true);
	 * ```
	 * @param string $table
	 * @param boolean $ignoreEnableFields
	 * @return array
	 */
	public function findAll( $table = '', $ignoreEnableFields = false ) 
	{
		$rows = $this->findByValues( $table, [], false, $ignoreEnableFields );
		return $rows ?: [];
	}

	/**
	 * Alles persistieren.
	 * ```
	 * \nn\t3::Db()->persistAll();
	 * ```
	 * @return void
	 */	
	public function persistAll () 
	{
		$persistenceManager = \nn\t3::injectClass( PersistenceManager::class );
		$persistenceManager->persistAll();
	}

	/**
	 * Findet EINEN Eintrag anhand von gewünschten Feld-Werten.
	 * ```
	 * // SELECT * FROM fe_users WHERE email = 'david@99grad.de'
	 * \nn\t3::Db()->findOneByValues('fe_users', ['email'=>'david@99grad.de']);
	 * 
	 * // SELECT * FROM fe_users WHERE firstname = 'david' AND username = 'john'
	 * \nn\t3::Db()->findOneByValues('fe_users', ['firstname'=>'david', 'username'=>'john']);
	 * 
	 * // SELECT * FROM fe_users WHERE firstname = 'david' OR username = 'john'
	 * \nn\t3::Db()->findOneByValues('fe_users', ['firstname'=>'david', 'username'=>'john'], true);
	 * 
	 * // SELECT uid, name FROM fe_users WHERE firstname = 'david' OR username = 'john'
	 * \nn\t3::Db()->findOneByValues('fe_users', ['firstname'=>'david', 'username'=>'john'], true, false, ['uid', 'name']);
	 * ```
	 * @param string $table
	 * @param array $whereArr
	 * @param boolean $useLogicalOr
	 * @param boolean $ignoreEnableFields
	 * @param array $fieldsToGet
	 * @return array
	 */
	public function findOneByValues( $table = null, $whereArr = [], $useLogicalOr = false, $ignoreEnableFields = false, $fieldsToGet = [] ) 
	{
		$additionalQueryParams = [
			'limit' => 1
		]; 
		$result = $this->findByValues( $table, $whereArr, $useLogicalOr, $ignoreEnableFields, $fieldsToGet, $additionalQueryParams );
		return $result ? array_shift($result) : []; 
	}

	/**
	 * Findet ALLE Einträge anhand eines gewünschten Feld-Wertes.
	 * Funktioniert auch, wenn Frontend noch nicht initialisiert wurde.
	 * ```
	 * // SELECT * FROM fe_users WHERE email = 'david@99grad.de'
	 * \nn\t3::Db()->findByValues('fe_users', ['email'=>'david@99grad.de']);
	 * 
	 * // SELECT * FROM fe_users WHERE uid IN (1,2,3)
	 * \nn\t3::Db()->findByValues('fe_users', ['uid'=>[1,2,3]]);
	 * 
	 * // SELECT uid, username FROM fe_users WHERE name = 'test'
	 * \nn\t3::Db()->findByValues('fe_users', ['name'=>'test'], false, false, ['uid', 'username']);
	 * 
	 * // SELECT * FROM fe_users WHERE name = 'test' LIMIT 1
	 * \nn\t3::Db()->findByValues('fe_users', ['name'=>'test'], false, false, false, ['limit'=>1]);
	 * 
	 * // SELECT * FROM fe_users WHERE name = 'test' LIMIT 2 OFFSET 3
	 * \nn\t3::Db()->findByValues('fe_users', ['name'=>'test'], false, false, false, ['limit'=>2, 'offset'=>3]);
	 * ```
	 * @param string $table
	 * @param array $whereArr
	 * @param boolean $useLogicalOr
	 * @param boolean $ignoreEnableFields
	 * @param array|boolean $fieldsToGet
	 * @param array $additionalQueryParams
	 * @return array
	 */
	public function findByValues( $table = null, $where = [], $useLogicalOr = false, $ignoreEnableFields = false, $fieldsToGet = [], $additionalQueryParams = [] ) 
	{	
		// Nur Felder behalten, die auch in Tabelle (TCA) existieren
		$whereArr = $this->filterDataForTable( $where, $table );

		// nichts mehr übrig? Dann macht die Abfrage keinen Sinn
		if ($where && !$whereArr) {
			return [];
		}

		if (!$fieldsToGet) {
			$fieldsToGet = ['*'];
		}

		$queryBuilder = $this->getQueryBuilder( $table );
		$queryBuilder->select(...$fieldsToGet)->from( $table );

		// Alle Einschränkungen z.B. hidden oder starttime / endtime entfernen?
		if ($ignoreEnableFields) {
			$queryBuilder->getRestrictions()->removeAll();
		}

		// set LIMIT?
		if ($limit = $additionalQueryParams['limit'] ?? false) {
			$queryBuilder->setMaxResults( $limit );
		}
		
		// set LIMIT OFFSET?
		if ($offset = $additionalQueryParams['offset'] ?? false) {
			$queryBuilder->setFirstResult( $offset );
		}

		if ($whereArr) {
			foreach ($whereArr as $colName=>$v) {
				if (is_array($v)) {
					$v = $this->quote( $v );
					$expr = $queryBuilder->expr()->in($colName, $v );
					if ($uids = \nn\t3::Arrays($v)->intExplode()) {
						$this->orderBy( $queryBuilder, ["{$table}.{$colName}"=>$uids] );
					}
				} else {
					$expr = $queryBuilder->expr()->eq( $colName, $queryBuilder->createNamedParameter( $v ) );
				}
				if (!$useLogicalOr) {
					$queryBuilder->andWhere( $expr );	
				} else {
					$queryBuilder->orWhere( $expr );	
				}
			}
		}

		// "deleted" IMMER berücksichtigen!
		if ($deleteCol = $this->getDeleteColumn( $table )) {
			$queryBuilder->andWhere( $queryBuilder->expr()->eq($deleteCol, 0) );	
		}

		$rows = $queryBuilder->executeQuery()->fetchAllAssociative();
		return $rows;
	}


	/**
	 * Findet ALLE Einträge, die in der Spalte `$column` einen Wert aus dem Array `$values` enthält.	 
	 * Funktioniert auch, wenn das Frontend noch nicht initialisiert wurden.
	 * Alias zu `\nn\t3::Db()->findByValues()`
	 * 
	 * ``` 
	 * // SELECT * FROM fe_users WHERE uid IN (1,2,3)
	 * \nn\t3::Db()->findIn('fe_users', 'uid', [1,2,3]);
	 * 
	 * // SELECT * FROM fe_users WHERE username IN ('david', 'martin')
	 * \nn\t3::Db()->findIn('fe_users', 'username', ['david', 'martin']);
	 * ```
	 * @param string $table
	 * @param string $column
	 * @param array $values
	 * @param boolean $ignoreEnableFields
	 * @return array
	 */
	public function findIn( $table = '', $column = '', $values = [], $ignoreEnableFields = false ) 
	{
		if (!$values) return [];
		return $this->findByValues( $table, [$column=>$values], false, $ignoreEnableFields );
	}

	/**
	 * Umkehrung zu `\nn\t3::Db()->findIn()`:
	 * 
	 * Findet ALLE Einträge, die in der Spalte `$column` NICHT einen Wert aus dem Array `$values` enthält.	 
	 * Funktioniert auch, wenn das Frontend noch nicht initialisiert wurden.
	 * 
	 * ``` 
	 * // SELECT * FROM fe_users WHERE uid NOT IN (1,2,3)
	 * \nn\t3::Db()->findNotIn('fe_users', 'uid', [1,2,3]);
	 * 
	 * // SELECT * FROM fe_users WHERE username NOT IN ('david', 'martin')
	 * \nn\t3::Db()->findNotIn('fe_users', 'username', ['david', 'martin']);
	 * ```
	 * @param string $table
	 * @param string $colName
	 * @param array $values
	 * @param boolean $ignoreEnableFields
	 * @return array
	 */
	public function findNotIn( $table = '', $colName = '', $values = [], $ignoreEnableFields = false ) 
	{
		$queryBuilder = $this->getQueryBuilder( $table );
		$queryBuilder->select('*')->from( $table );

		// Alle Einschränkungen z.B. hidden oder starttime / endtime entfernen?
		if ($ignoreEnableFields) {
			$queryBuilder->getRestrictions()->removeAll();
		}

		// "deleted" IMMER berücksichtigen!
		if ($deleteCol = $this->getDeleteColumn( $table )) {
			$queryBuilder->andWhere( $queryBuilder->expr()->eq($deleteCol, 0) );	
		}
		
		$values = $this->quote( $values );

		$expr = $queryBuilder->expr()->notIn( $colName, $values );
		$queryBuilder->andWhere( $expr );

		$rows = $queryBuilder->executeQuery()->fetchAllAssociative();
		return $rows;
	}

	/**
	 * Sortierung für ein Repository oder einen Query setzen.
	 * ```
	 * $ordering = ['title' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING];
	 * \nn\t3::Db()->orderBy( $queryOrRepository, $ordering );
	 *
	 * // asc und desc können als synonym verwendet werden 
	 * $ordering = ['title' => 'asc'];
	 * $ordering = ['title' => 'desc'];
	 * \nn\t3::Db()->orderBy( $queryOrRepository, $ordering );
	 * ```
	 * Kann auch zum Sortieren nach einer Liste von Werten (z.B. `uids`) verwendet werden.
	 * Dazu wird ein Array für den Wert des einzelnen orderings übergeben:
	 * ```
	 * $ordering = ['uid' => [3,7,2,1]];
	 * \nn\t3::Db()->orderBy( $queryOrRepository, $ordering );
	 * ```
	 * @param mixed $queryOrRepository
	 * @param array $ordering
	 * @return mixed
	 */
	public function orderBy( $queryOrRepository, $ordering = [] ) 
	{
		$isQueryObject = get_class( $queryOrRepository ) == Query::class;
		$isQueryBuilderObject = get_class( $queryOrRepository) == QueryBuilder::class;

		if ($isQueryObject) {
			// ToDo!
		} else if ($isQueryBuilderObject) {
			foreach ($ordering as $colName => $ascDesc) {
				if (is_array($ascDesc)) {
					foreach ($ascDesc as &$v) {
						$v = $queryOrRepository->createNamedParameter( $v );
					}
					$queryOrRepository->add('orderBy', "FIELD({$colName}," . implode(',', $ascDesc) . ')', true );
				} else {

					// 'asc' und 'desc' können als Synonym verwendet werden
					if (strtolower($ascDesc) == 'asc') {
						$ascDesc = \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING;
					}
					if (strtolower($ascDesc) == 'desc') {
						$ascDesc = \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING;
					}

					$queryOrRepository->addOrderBy( $colName, $ascDesc );
				}
			}
		} else {
			$queryOrRepository->setDefaultOrderings( $ordering );
		}

		return $queryOrRepository;
	}

	/**
	 * Entfernt Default-Constraints zur StoragePID, hidden und/oder deleted
	 * zu einer Query oder Repository.
	 * ```
	 * \nn\t3::Db()->ignoreEnableFields( $entryRepository );
	 * \nn\t3::Db()->ignoreEnableFields( $query );
	 * ```
	 * Beispiel für eine Custom Query:
	 * ```
	 * $table = 'tx_myext_domain_model_entry';
	 * $queryBuilder = \nn\t3::Db()->getQueryBuilder( $table );
	 * $queryBuilder->select('uid','title','hidden')->from( $table );
	 * \nn\t3::Db()->ignoreEnableFields( $queryBuilder, true, true );
	 * $rows = $queryBuilder->executeQuery()->fetchAllAssociative();
	 * ```
	 * Sollte das nicht reichen oder zu kompliziert werden, siehe:
	 * ```
	 * \nn\t3::Db()->statement();
	 * ```
	 * @param mixed $queryOrRepository
	 * @param boolean $ignoreStoragePid
	 * @param boolean $ignoreHidden
	 * @param boolean $ignoreDeleted
	 * @param boolean $ignoreStartEnd
	 * @return mixed
	 */
    public function ignoreEnableFields ( $queryOrRepository, $ignoreStoragePid = true, $ignoreHidden = false, $ignoreDeleted = false, $ignoreStartEnd = false ) 
	{
		$isQueryObject = get_class( $queryOrRepository ) == Query::class;
		$isQueryBuilderObject = get_class( $queryOrRepository) == QueryBuilder::class;

		if ($isQueryObject) {
			$query = $queryOrRepository;
		} else if ($isQueryBuilderObject) {

			// s. https://bit.ly/3fFvM18
			$restrictions = $queryOrRepository->getRestrictions();
			if ($ignoreStartEnd) {
				$restrictions->removeByType( \TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction::class );
				$restrictions->removeByType( \TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction::class );
			}
			if ($ignoreHidden) {
				$hiddenRestrictionClass = \nn\t3::injectClass( \TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction::class );
				$restrictions->removeByType( get_class( $hiddenRestrictionClass ) );
			}
			if ($ignoreDeleted) {
				$deletedRestrictionClass = \nn\t3::injectClass( \TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction::class );
				$restrictions->removeByType( get_class($deletedRestrictionClass) );
			}
			return $queryOrRepository;

		} else {
			$query = $queryOrRepository->createQuery();
		}

		$querySettings = $query->getQuerySettings();

		$ignoreHidden = $ignoreHidden === true ? true : $querySettings->getIgnoreEnableFields();
		$ignoreDeleted = $ignoreDeleted === true ? true : $querySettings->getIncludeDeleted();

		$querySettings->setRespectStoragePage( !$ignoreStoragePid );
		$querySettings->setIgnoreEnableFields( $ignoreHidden );
		$querySettings->setIncludeDeleted( $ignoreDeleted );

		if (!$isQueryObject) {
			$queryOrRepository->setDefaultQuerySettings( $querySettings );
		}

		return $query;
	}


	/**
	 * Datenbank-Eintrag aktualisieren. Schnell und einfach.
	 * Das Update kann entweder per Tabellenname und Daten-Array passieren.
	 * Oder man übergibt ein Model.
	 * 
	 * Beispiele:
	 * ```
	 * // UPDATES table SET title='new' WHERE uid=1 
	 * \nn\t3::Db()->update('table', ['title'=>'new'], 1);
	 * 
	 * // UPDATES table SET title='new' WHERE uid IN (1,2,3) 
	 * \nn\t3::Db()->update('table', ['title'=>'new'], ['uid'=>[1,2,3]);
	 * 
	 * // UPDATE table SET title='new' WHERE email='david@99grad.de' AND pid=12
	 * \nn\t3::Db()->update('table', ['title'=>'new'], ['email'=>'david@99grad.de', 'pid'=>12, ...]);
	 * ```
	 * 
	 * Mit `true` statt einer `$uid` werden ALLE Datensätze der Tabelle geupdated.
	 * ```
	 * // UPDATE table SET test='1' WHERE 1
	 * \nn\t3::Db()->update('table', ['test'=>1], true);
	 * ```
	 * 
	 * Statt einem Tabellenname kann auch ein einfach Model übergeben werden.
	 * Das Repository wird automatisch ermittelt und das Model direkt persistiert.
	 * ```
	 * $model = $myRepo->findByUid(1);
	 * \nn\t3::Db()->update( $model );
	 * ```
	 * @param mixed $tableNameOrModel
	 * @param array $data
	 * @param int $uid
	 * @return mixed
	 */
    public function update ( $tableNameOrModel = '', $data = [], $uid = null ) 
	{	
		if (\nn\t3::Obj()->isModel( $tableNameOrModel )) {
			$persistenceManager = \nn\t3::injectClass( PersistenceManager::class );
			$persistenceManager->update( $tableNameOrModel );
			$persistenceManager->persistAll();
			$this->fixFileReferencesForModel( $tableNameOrModel );
			return $tableNameOrModel;
		}

		$queryBuilder = $this->getQueryBuilder( $tableNameOrModel );
        $queryBuilder->getRestrictions()->removeAll();
		$queryBuilder->update( $tableNameOrModel );

		$data = $this->filterDataForTable( $data, $tableNameOrModel );
		if (!$data) return false;

		foreach ($data as $k=>$v) {
			$queryBuilder->set( $k, $v );
		}

		if ($uid !== true) {	
			if (is_numeric($uid)) {
				$uid = ['uid' => $uid];
			}	
			foreach ($uid as $k=>$v) {
				if (is_array($v)) {
					$v = $this->quote( $v );
					$queryBuilder->andWhere(
						$queryBuilder->expr()->in( $k, $queryBuilder->createNamedParameter($v, Connection::PARAM_STR_ARRAY) )
					);
				} else {
					$queryBuilder->andWhere(
						$queryBuilder->expr()->eq( $k, $queryBuilder->createNamedParameter($v) )
					);
				}

			}	
		}		
		return $queryBuilder->executeStatement();
	}
	
	/**
	 * Datenbank-Eintrag einfügen. Simpel und idiotensicher.
	 * Entweder kann der Tabellenname und ein Array übergeben werden - oder ein Domain-Model.
	 * 
	 * Einfügen eines neuen Datensatzes per Tabellenname und Daten-Array:
	 * ```
	 * $insertArr = \nn\t3::Db()->insert('table', ['bodytext'=>'...']);
	 * ```
	 * 
	 * Einfügen eines neuen Models. Das Repository wird automatisch ermittelt.
	 * Das Model wird direkt persistiert.
	 * ```
	 * $model = new \My\Nice\Model();
	 * $persistedModel = \nn\t3::Db()->insert( $model );
	 * ```
	 * @param mixed $tableNameOrModel
	 * @param array $data
	 * @return mixed 
	 */
    public function insert ( $tableNameOrModel = '', $data = [] ) 
	{
		if (\nn\t3::Obj()->isModel( $tableNameOrModel )) {
			$persistenceManager = \nn\t3::injectClass( PersistenceManager::class );
			$persistenceManager->add( $tableNameOrModel );
			$persistenceManager->persistAll();
			$this->fixFileReferencesForModel( $tableNameOrModel );
			return $tableNameOrModel;
		}

		$data = $this->filterDataForTable( $data, $tableNameOrModel );

		$queryBuilder = $this->getQueryBuilder( $tableNameOrModel );
		$queryBuilder->insert( $tableNameOrModel )
			->values($data)->executeStatement();
		$data['uid'] = $queryBuilder->getConnection()->lastInsertId();
		return $data;
	}
	
	/**
	 * Datenbank-Eintrag erstellen ODER einen vorhandenen Datensatz updaten.
	 * 
	 * Entscheidet selbstständig, ob der Eintrag per `UPDATE` oder `INSERT` in die Datenbank
	 * eingefügt bzw. ein vorhandener Datensatz aktualisiert werden muss. Die Daten werden 
	 * direkt persistiert!
	 * 
	 * Beispiel für Übergabe eines Tabellennamens und eines Arrays:
	 * ```
	 * // keine uid übergeben? Dann INSERT eines neuen Datensatzes
	 * \nn\t3::Db()->save('table', ['bodytext'=>'...']);
	 * 
	 * // uid übergeben? Dann UPDATE vorhandener Daten
	 * \nn\t3::Db()->save('table', ['uid'=>123, 'bodytext'=>'...']);
	 * ```
	 * 
	 * Beispiel für Übergabe eines Domain-Models:
	 * ```
	 * // neues Model? Wird per $repo->add() eingefügt
	 * $model = new \My\Nice\Model();
	 * $model->setBodytext('...');
	 * $persistedModel = \nn\t3::Db()->save( $model );
	 * 
	 * // vorhandenes Model? Wird per $repo->update() aktualisiert
	 * $model = $myRepo->findByUid(123);
	 * $model->setBodytext('...');
	 * $persistedModel = \nn\t3::Db()->save( $model );
	 * ```
	 * 
	 * @param mixed $tableNameOrModel
	 * @param array $data
	 * @return mixed 
	 */
    public function save( $tableNameOrModel = '', $data = [] ) 
	{
		if (\nn\t3::Obj()->isModel( $tableNameOrModel )) {
			$uid = \nn\t3::Obj()->get( $tableNameOrModel, 'uid' );
			$method = $uid ? 'update' : 'insert';
		} else {
			$uid = $data['uid'] ?? false;
			$method = $uid && $this->findByUid( $tableNameOrModel, $uid ) ? 'update' : 'insert';
		}
		return $this->$method( $tableNameOrModel, $data );
	}

	/**
	 * "Repariert" die SysFileReferences für Modelle, die eine Property haben,
	 * die statt einer `ObjectStorage<FileReference>` nur eine `FileReference`
	 * referenzieren. Zum aktuellen Zeitpunkt ist es unklar, weshalb TYPO3 diese zwar
	 * in der Tabelle `sys_file_reference` persistiert, aber das Feld `tablenames`
	 * leert – bzw. `uid_foreign` nicht setzt. Bei einer `ObjectStorage<FileReference>`
	 * tritt das Problem nicht auf.
	 * ```
	 * // muss direkt nach dem persistieren des Models passieren
	 * \nn\t3::Db()->fixFileReferencesForModel( $model );
	 * ```
	 */
	public function fixFileReferencesForModel( $model ) 
	{
		$props = \nn\t3::Obj()->getProps( $model );
		$modelTableName = \nn\t3::Obj()->getTableName( $model );

		foreach ($props as $field=>$prop) {
			if (is_a($prop, \TYPO3\CMS\Extbase\Domain\Model\FileReference::class, true)) {
				$sysFile = \nn\t3::Obj()->get($model, $field);
				
				if (!$sysFile) continue;
				$resource = $sysFile->getOriginalResource();
				$uid = $resource->getUid();
				if (!$uid) {
					$result = $this->insert($sysFile);
					$uid = $result->getUid();
				}

				if (!$resource) continue;
				$uidForeign =  $resource->getProperty('uid_foreign');
				$tableName = $resource->getProperty('tablenames');

				if (!$uidForeign || !$tableName) {
					$this->update('sys_file_reference', [
						'uid_foreign'	=> $model->getUid(),
						'tablenames'	=> $modelTableName,
					], $uid);
				}

			}
		}
	}

	/**
	 * Sortiert Ergebnisse eines Queries nach einem Array und bestimmten Feld.
	 * Löst das Problem, dass eine `->in()`-Query die Ergebnisse nicht
	 * in der Reihenfolge der übergebenen IDs liefert. Beispiel:
	 * `$query->matching($query->in('uid', [3,1,2]));` kommt nicht zwingend
	 * in der Reihenfolge `[3,1,2]` zurück.
	 * ```
	 * $insertArr = \nn\t3::Db()->sortBy( $storageOrArray, 'uid', [2,1,5]);
	 * ```
	 * @param mixed $objectArray
	 * @param string $fieldName
	 * @param array $uidList
	 * @return array 
	 */
    public function sortBy ( $objectArray, $fieldName = 'uid', $uidList = [] ) 
	{
		if (method_exists( $objectArray, 'toArray')) {
			$objectArray = $objectArray->toArray();
		}
		usort( $objectArray, function ($a, $b) use ( $uidList, $fieldName ) {
			$p1 = array_search( \nn\t3::Obj()->accessSingleProperty($a, $fieldName), $uidList );
			$p2 = array_search( \nn\t3::Obj()->accessSingleProperty($b, $fieldName), $uidList );
			return $p1 > $p2 ? 1 : -1;
		});
		return $objectArray;
	}
	
	/**
	 * Datenbank-Eintrag löschen. Klein und Fein.
	 * Es kann entweder ein Tabellenname und die UID übergeben werden - oder ein Model.
	 * 
	 * Löschen eines Datensatzes per Tabellenname und uid oder einem beliebigen Constraint:
	 * ```
	 * // Löschen anhand der uid
	 * \nn\t3::Db()->delete('table', $uid);
	 * 
	 * // Löschen anhand eines eigenen Feldes
	 * \nn\t3::Db()->delete('table', ['uid_local'=>$uid]);
	 * 
	 * // Eintrag komplett und unwiderruflich löschen (nicht nur per Flag deleted = 1 entfernen)
	 * \nn\t3::Db()->delete('table', $uid, true);
	 * 
	 * ```
	 * 
	 * Löschen eines Datensatzes per Model:
	 * ```
	 * \nn\t3::Db()->delete( $model );
	 * ```
	 * @param mixed $table
	 * @param array $constraint
	 * @param boolean $reallyDelete
	 * @return mixed 
	 */
    public function delete ( $table = '', $constraint = [], $reallyDelete = false ) 
	{
		if (\nn\t3::Obj()->isModel($table)) {
			$model = $table;
			$repository = $this->getRepositoryForModel( $model );
			$repository->remove( $model );
			$this->persistAll();
			return $model;
		}

		if (!$constraint) return false;
		if (is_numeric($constraint)) {
			$constraint = ['uid' => $constraint];
		}

		$deleteColumn = $reallyDelete ? false : $this->getDeleteColumn( $table );
		if ($deleteColumn) {
			return $this->update( $table, [$deleteColumn => 1], $constraint );
		}

		$queryBuilder = $this->getQueryBuilder( $table );

		$queryBuilder->delete($table);
		foreach ($constraint as $k=>$v) {
			$queryBuilder->andWhere(
				$queryBuilder->expr()->eq( $k, $queryBuilder->createNamedParameter($v))
			);
		}

		return $queryBuilder->executeStatement();
	}

	/**
	 * Die DSGVO-Variante des Löschens.
	 * 
	 * Radikales entfernen aller Spuren eines Datensatzen inkl. der physischen SysFiles,
	 * die mit dem Model verknüpft sind. Mit Vorsicht zu verwenden, da keine Relationen
	 * auf das zu löschende Model geprüft werden.
	 * ```
	 * \nn\t3::deleteWithAllFiles( $model );
	 * ```
	 * @param \TYPO3\CMS\Extbase\DomainObject\AbstractEntity $model
	 * @return void
	 */
	public function deleteWithAllFiles( $model ) 
	{
		$tableName = $this->getTableNameForModel( $model );
		$uid = $model->getUid();
		if (!$tableName || !$uid) return;

		\nn\t3::Fal()->deleteForModel( $model );
		\nn\t3::Db()->delete($tableName, $uid, true);
	}

	/**
	 * Datenbank-Tabelle leeren.
	 * Löscht alle Einträge in der angegebenen Tabelle und setzt den Auto-Increment-Wert auf `0` zurück.
	 * ```
	 * \nn\t3::Db()->truncate('table');
	 * ```
	 * @param string $table
	 * @return boolean 
	 */
    public function truncate ( $table = '' ) 
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable( $table );
		return $connection->truncate( $table );
	}

	/**
	 * Eine "rohe" Query an die Datenbank absetzen.
	 * Näher an der Datenbank geht nicht. Du bist für alles selbst verantwortlich.
	 * Injections steht nur Deine (hoffentlich ausreichende :) Intelligenz entgegen.
	 * 
	 * Hilft z.B. bei Abfragen von Tabellen, die nicht Teil der Typo3 Installation sind und
	 * daher über den normal QueryBuilder nicht erreicht werden könnten.
	 * 
	 * ```
	 * // Variablen IMMER über escapen!
	 * $keyword = \nn\t3::Db()->quote('suchbegriff');
	 * $rows = \nn\t3::Db()->statement( "SELECT * FROM tt_news WHERE bodytext LIKE '%${keyword}%'");
	 * 
	 * // oder besser gleich prepared statements verwenden:
	 * $rows = \nn\t3::Db()->statement( 'SELECT * FROM tt_news WHERE bodytext LIKE :str', ['str'=>"%${keyword}%"] );
	 * ```
	 * 
	 * Bei einem `SELECT` Statement werden die Zeilen aus der Datenbank als Array zurückgegeben.
	 * Bei allen anderen Statements (z.B. `UPDATE` oder `DELETE`) wird die Anzahl der betroffenen Zeilen zurückgegeben.
	 * 
	 * @param string $statement
	 * @param array $params
	 * @return mixed
	 */
	public function statement( $statement = '', $params = [] ) 
	{
		$connection = $this->getConnection();

		// exec / fetchAll --> @siehe https://bit.ly/3ltPF0S

		if (stripos($statement, 'select ') !== false) {
			$result = $connection->fetchAllAssociative( $statement, $params );
		} else {
			$result = $connection->exec( $statement, $params );
		}

		return $result;
	}

	/**
	 * Ein Ersatz für die `mysqli_real_escape_string()` Methode.
	 * 
	 * Sollte nur im Notfall bei Low-Level Queries verwendet werden. 
	 * Besser ist es, `preparedStatements` zu verwenden.
	 * 
	 * Funktioniert nur bei SQL, nicht bei DQL.
	 * ```
	 * $sword = \nn\t3::Db()->quote('test');			// => 'test'
	 * $sword = \nn\t3::Db()->quote("test';SET");		// => 'test\';SET'
	 * $sword = \nn\t3::Db()->quote([1, 'test', '2']);  // => [1, "'test'", '2']
	 * $sword = \nn\t3::Db()->quote('"; DROP TABLE fe_user;#');
	 * ```
	 * @param string|array $value
	 * @return string|array
	 */
	public function quote( $value = '' ) 
	{
		if (is_array($value)) {
			foreach ($value as &$val) {
				if (!is_numeric($val)) {
					$val = $this->quote($val);
				}
			}
			return $value;
		}
		return $this->getConnection()->quote( $value );
	}

	/**
	 * Gelöschten Datenbank-Eintrag wiederherstellen. 
	 * Dazu wird der Flag für "gelöscht" (`deleted`) wieder auf `0` gesetzt wird.
	 * 
	 * ```
	 * \nn\t3::Db()->undelete('table', $uid);
	 * \nn\t3::Db()->undelete('table', ['uid_local'=>$uid]);
	 * ```
	 * @param string $table
	 * @param array $constraint
	 * @return boolean 
	 */
    public function undelete ( $table = '', $constraint = [] ) 
	{
		if (!$constraint) return false;
		if (is_numeric($constraint)) {
			$constraint = ['uid' => $constraint];
		}
		if ($deleteColumn = $this->getDeleteColumn( $table )) {
			return $this->update( $table, [$deleteColumn => 0], $constraint );
		}
		return false;
	}

	/**
	 * Existiert eine bestimmte DB-Tabelle?
	 * ```
	 * $exists = \nn\t3::Db()->tableExists('table');
	 * ```
	 * @return boolean
	 */
	public function tableExists ( $table = '' ) 
	{
		return isset($GLOBALS['TCA'][$table]);
	}

	/**
	 * Alle Tabellen-Spalten (TCA) für bestimmte Tabelle holen
	 * ```
	 * // Felder anhand des TCA-Arrays holen
	 * \nn\t3::Db()->getColumns( 'tablename' );
	 * 
	 * // Felder über den SchemaManager ermitteln
	 * \nn\t3::Db()->getColumns( 'tablename', true );
	 * ```
	 * @param string $table
	 * @param boolean $useSchemaManager
	 * @return array
	 */
	public function getColumns ( $table = '', $useSchemaManager = false ) 
	{
		$cols = isset($GLOBALS['TCA'][$table]) ? $GLOBALS['TCA'][$table]['columns'] : [];
		
		// Diese Felder sind nicht ausdrücklich im TCA, aber für Abfrage legitim
		if ($cols) {
			$cols = \nn\t3::Arrays( $cols )->merge(['uid'=>'uid', 'pid'=>'pid', 'tstamp'=>'tstamp', 'crdate'=>'crdate', 'endtime'=>'endtime', 'starttime'=>'starttime', 'deleted'=>'deleted', 'disable'=>'disable']);
		}

		// Keine cols ermittelt, weil nicht  im TCA registriert – oder Abfrage erzwungen
		if (!$cols || $useSchemaManager) {
			$cols = GeneralUtility::makeInstance(ConnectionPool::class)
				->getConnectionForTable($table)
				->getSchemaManager()
				->listTableColumns($table);
		}

		foreach ($cols as $k=>$v) {
			$cols[GeneralUtility::underscoredToLowerCamelCase($k)] = $v;
		}

		return $cols;
	}
	
	/**
	 * Eine Tabellen-Spalte (TCA) für bestimmte Tabelle holen
	 * ```
	 * \nn\t3::Db()->getColumn( 'tablename', 'fieldname' );
	 * ```
	 * @param string $table
	 * @param string $colName
	 * @param boolean $useSchemaManager
	 * @return array
	 */
	public function getColumn ( $table = '', $colName = '', $useSchemaManager = false ) 
	{
		$cols = $this->getColumns( $table, $useSchemaManager );
		return $cols[$colName] ?? [];
	}

	/**
	 * Felder einer Tabelle nach einem bestimmten Typ holen
	 * ```
	 * \nn\t3::Db()->getColumnsByType( 'tx_news_domain_model_news', 'slug' );
	 * ```
	 * @param string $table
	 * @param string $colType
	 * @param boolean $useSchemaManager
	 * @return array
	 */
	public function getColumnsByType( $table = '', $colType = '', $useSchemaManager = false ) 
	{
		$cols = $this->getColumns( $table, $useSchemaManager );
		$results = [];
		foreach ($cols as $fieldName=>$col) {
			$type = $col['config']['type'] ?? false;
			$fieldName = GeneralUtility::camelCaseToLowerCaseUnderscored( $fieldName );
			if ($type == $colType) {
				$results[$fieldName] = array_merge(['fieldName'=>$fieldName], $col);
			}
		}
		return $results;
	}

	/**
	 * Delete-Column für bestimmte Tabelle holen.
	 * 
	 * Diese Spalte wird als Flag für gelöschte Datensätze verwendet.
	 * Normalerweise: `deleted` = 1
	 * 
	 * @param string $table
	 * @return string
	 */
	public function getDeleteColumn ( $table = '' ) 
	{
		$ctrl = $GLOBALS['TCA'][$table]['ctrl'] ?? [];
		return $ctrl['delete'] ?? false;
	}
	
	/**
	 * In key/val-Array nur Elemente behalten, deren keys auch 
	 * in TCA für bestimmte Tabelle existieren
	 * 
	 * @param array $data
	 * @param string $table
	 * @return array
	 */
	public function filterDataForTable ( $data = [], $table = '' ) 
	{
		$tcaColumns = $this->getColumns( $table );
		$existingCols = array_intersect( array_keys($data), array_keys($tcaColumns));

		foreach ($data as $k=>$v) {
			if (!in_array($k, $existingCols)) {
				unset($data[$k]);
			}
		}
		return $data;
	}

	/**
	 * Lokalisiertes Label eines bestimmten TCA Feldes holen
	 * 
	 * @param string $column
	 * @param string $table
	 * @return string
	 */
	public function getColumnLabel ( $column = '', $table = '' ) 
	{
		$tca = $this->getColumns( $table );
		$label = $tca[$column]['label'] ?? '';
		if ($label && ($LL = LocalizationUtility::translate($label))) return $LL;
		return $label;
	}
	
	/**
	 * Constraint für sys_category / sys_category_record_mm zu einem QueryBuilder hinzufügen.
	 * Beschränkt die Ergebnisse auf die angegebenen Sys-Categories-UIDs.
	 * 
	 * ```
	 * $queryBuilder = \nn\t3::Db()->getQueryBuilder( $table );
	 * \nn\t3::Db()->setSysCategoryConstraint( $queryBuilder, [1,3,4], 'tx_myext_tablename', 'categories' );
	 * ```
	 * @param \TYPO3\CMS\Core\Database\Query\QueryBuilder $querybuilder
	 * @param array $sysCategoryUids
	 * @param string $tableName
	 * @param string $categoryFieldName
	 * @param boolean $useNotIn
	 * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
	 */
	public function setSysCategoryConstraint ( &$queryBuilder = null, $sysCategoryUids = [], $tableName = '', $categoryFieldName = 'categories', $useNotIn = false ) 
	{
		if (!$sysCategoryUids) return $queryBuilder;

		$and = [
			$queryBuilder->expr()->eq('categoryMM.tablenames', $queryBuilder->expr()->literal($tableName)),
			$queryBuilder->expr()->eq('categoryMM.fieldname', $queryBuilder->expr()->literal($categoryFieldName))
		];

		if (!$useNotIn) {
			$and[] = $queryBuilder->expr()->in( 'categoryMM.uid_local', $sysCategoryUids );
			$queryBuilder->andWhere(...$and);
		} else {			
			$and[] = $queryBuilder->expr()->notIn('categoryMM.uid_local', $sysCategoryUids);
			$queryBuilder->andWhere(
				$queryBuilder->expr()->orX(
					$queryBuilder->expr()->isNull('categoryMM.uid_foreign'),
					$queryBuilder->expr()->andX(...$and)
				)
			);
		}

		$queryBuilder->leftJoin(
			$tableName,
			'sys_category_record_mm',
			'categoryMM',
			$queryBuilder->expr()->eq('categoryMM.uid_foreign', $tableName . '.uid')
		)->groupBy('uid');

		return $queryBuilder;
	}

	/**
	 * Contraint auf Datensätze beschränken, die NICHT in eine der angegebenen Kategorien sind.
	 * Gegenteil und Alias zu `\nn\t3::Db()->setSysCategoryConstraint()`
	 * 
	 * ```
	 * $queryBuilder = \nn\t3::Db()->getQueryBuilder( $table );
	 * \nn\t3::Db()->setNotInSysCategoryConstraint( $queryBuilder, [1,3,4], 'tx_myext_tablename', 'categories' );
	 * ```
	 * @param \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder
	 * @param array $sysCategoryUids
	 * @param string $tableName
	 * @param string $categoryFieldName
	 * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
	 */
	public function setNotInSysCategoryConstraint( &$queryBuilder = null, $sysCategoryUids = [], $tableName = '', $categoryFieldName = 'categories' ) 
	{
		return $this->setSysCategoryConstraint( $queryBuilder, $sysCategoryUids, $tableName, $categoryFieldName, true );
	}

	/**
	 * Constraint für sys_file_reference zu einem QueryBuilder hinzufügen.
	 * Beschränkt die Ergebnisse darauf, ob es eine FAL-Relation gibt.
	 *
	 * ```
	 * $queryBuilder = \nn\t3::Db()->getQueryBuilder( $table );
	 * 
	 * // Nur Datensätze holen, die für falfield mindestes eine SysFileReference haben
	 * \nn\t3::Db()->setFalConstraint( $queryBuilder, 'tx_myext_tablename', 'falfield' );
	 * 
	 * // ... die KEINE SysFileReference für falfield haben
	 * \nn\t3::Db()->setFalConstraint( $queryBuilder, 'tx_myext_tablename', 'falfield', false );
	 * 
	 * // ... die GENAU 2 SysFileReferences haben
	 * \nn\t3::Db()->setFalConstraint( $queryBuilder, 'tx_myext_tablename', 'falfield', 2 );
	 * 
	 * // ... die 2 oder weniger (less than or equal) SysFileReferences haben
	 * \nn\t3::Db()->setFalConstraint( $queryBuilder, 'tx_myext_tablename', 'falfield', 2, 'lte' );
	 * ```
	 * 
	 * @param \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder
	 * @param string $tableName
	 * @param string $falFieldName
	 * @param boolean $numFal
	 * @param boolean $operator
	 * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
	 */
	public function setFalConstraint( &$queryBuilder = null, $tableName = '', $falFieldName = '', $numFal = true, $operator = false ) 
	{	
		if ($operator === false) {
			if ($numFal === 0 || $numFal === 1) {
				$operator = 'eq';
			}
			if ($numFal === true) 	{
				$numFal = 1;
				$operator = 'gte';
			}
			if ($numFal === false) 	{
				$numFal = 0;
			}
		}
		if ($operator === false) {
			$operator = 'eq';
		}

		$groupName = 'cnt_' . preg_replace('[^a-zA-Z0-1]', '', $falFieldName);

		$subQuery = $this->getQueryBuilder( 'sys_file_reference' )
			->selectLiteral('COUNT(s.uid)')
			->from('sys_file_reference', 's')
			->andWhere($queryBuilder->expr()->eq('s.fieldname', $queryBuilder->createNamedParameter($falFieldName)))
			->andWhere($queryBuilder->expr()->eq('s.uid_foreign', $queryBuilder->quoteIdentifier($tableName.'.uid')))
			->getSql();

		$queryBuilder
			->addSelectLiteral("({$subQuery}) AS {$groupName}")
			->having( $queryBuilder->expr()->{$operator}($groupName, $numFal) );
		return $queryBuilder;
		
	}

	/**
	 * Tabellen-Name für ein Model (oder einen Model-Klassennamen) holen.
	 * Alias zu `\nn\t3::Obj()->getTableName()`
	 * ```
	 * // tx_myext_domain_model_entry
	 * \nn\t3::Db()->getTableNameForModel( $myModel );
	 * 
	 * // tx_myext_domain_model_entry
	 * \nn\t3::Db()->getTableNameForModel( \My\Domain\Model\Name::class );
	 * ```
	 * @param mixed $className 
	 * @return string
	 */
	public function getTableNameForModel( $className = null ) 
	{
		return \nn\t3::Obj()->getTableName( $className );
	}
	
	/**
	 * Instanz des Repositories für ein Model (oder einen Model-Klassennamen) holen.
	 * ```
	 * \nn\t3::Db()->getRepositoryForModel( \My\Domain\Model\Name::class );
	 * \nn\t3::Db()->getRepositoryForModel( $myModel );
	 * ```
	 * @param mixed $className
	 * @return \TYPO3\CMS\Extbase\Persistence\Repository
	 */
	public function getRepositoryForModel( $className = null ) 
	{
		if (!is_string($className)) $className = get_class($className);
		$repositoryName = \TYPO3\CMS\Core\Utility\ClassNamingUtility::translateModelNameToRepositoryName( $className );		
		return \nn\t3::injectClass( $repositoryName );
	}

	/**
	 * Debug des `QueryBuilder`-Statements.
	 * 
	 * Gibt den kompletten, kompilierten Query als lesbaren String aus, so wie er später in der Datenbank 
	 * ausgeführt wird z.B. `SELECT * FROM fe_users WHERE ...`
	 * 
	 * ```
	 * // Statement direkt im Browser ausgeben
	 * \nn\t3::Db()->debug( $query );
	 * 
	 * // Statement als String zurückgeben, nicht automatisch ausgeben
	 * echo \nn\t3::Db()->debug( $query, true );
	 * ```
	 * @param mixed $query
	 * @param boolean $return
	 * @return string
	 */
	public function debug ( $query = null, $return = false ) 
	{
		if( !($query instanceof QueryBuilder) ) {
			$queryParser = \nn\t3::injectClass(Typo3DbQueryParser::class);
			$query = $queryParser->convertQueryToDoctrineQueryBuilder($query);
		}

		$dcValues = $query->getParameters();
		$dcValuesFull = [];
		foreach ($dcValues as $k=>$v) {
			if (is_array($v)) {
				foreach ($v as &$n) {
					if (!is_numeric($n)) {
						$n = "'" . addslashes($n) . "'";
					}
				}
				$v = join(',', $v);
			} else if (!is_numeric($v)) {
				$v = "'" . addslashes($v) . "'";
			}
			$dcValuesFull[":{$k}"] = $v;
		}

		// Sicherstellen, dass zuerst `:value55` vor `:value5` ersetzt wird 
		uksort($dcValuesFull, function($a, $b) {
			return strlen($a) > strlen($b) ? -1 : 1;
		});

		$str = $query->getSQL();
		$str = str_replace( array_keys($dcValuesFull), array_values($dcValuesFull), $str );
		
		if (!$return) echo $str;
		return $str;
	}
}