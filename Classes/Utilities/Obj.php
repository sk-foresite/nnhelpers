<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyObjectStorage;
use TYPO3\CMS\Extbase\Persistence\Generic\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as FalFileReference;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\TypeHandlingUtility;

/**
 * Alles, was man für Objects und Models braucht.
 */
class Obj implements SingletonInterface {
   

	const END_OF_RECURSION = '%#EOR#%';

	/**
	 * 	@var mixed
	 */
	protected $initialArgument;
	
	/**
	 * 	Klasse konstruieren.
	 */
	public function __construct ( $obj = null ) {
		$this->initialArgument = $obj;
		return $this;
	}

	/**
	 * Merge eines Arrays in ein Object
	 * ```
	 * \nn\t3::Obj( \My\Doman\Model )->merge(['title'=>'Neuer Titel']);
	 * ```
	 * Damit können sogar FileReferences geschrieben / überschrieben werden.
	 * In diesem Beispiel wird `$data` mit einem existierende Model gemerged. 
	 * `falMedia` ist im Beispiel eine ObjectStorage. Das erste Element in `falMedia` exisitert 
	 * bereits in der Datenbank (`uid = 12`). Hier wird nur der Titel aktualisiert. 
	 * Das zweite Element im Array (ohne `uid`) ist neu. Dafür wird automatisch eine neue 
	 * `sys_file_reference` in der Datenbank erzeugt.
	 * ```
	 * $data = [
	 * 	'uid' => 10,
	 * 	'title' => 'Der Titel',
	 * 	'falMedia' => [
	 * 		['uid'=>12, 'title'=>'1. Bildtitel'],
	 * 		['title'=>'NEU Bildtitel', 'publicUrl'=>'fileadmin/_tests/5e505e6b6143a.jpg'],
	 * 	]
	 * ];
	 * $oldModel = $repository->findByUid( $data['uid'] );
	 * $mergedModel = \nn\t3::Obj($oldModel)->merge($data);
	 * ```
	 * __Hinweis__
	 * Um ein neues Model mit Daten aus einem Array zu erzeugen gibt
	 * es die Methode `$newModel = \nn\t3::Convert($data)->toModel( \My\Model\Name::class );`
	 * 
	 * @return Object
	 */
	public function merge( $model = null, $overlay = null ) {

		$overlay = $this->initialArgument !== null ? $model : $overlay;
		$model = $this->initialArgument !== null ? $this->initialArgument : $model;
		$schema = \nn\t3::Obj()->getClassSchema($model);
		$modelProperties = $schema->getProperties();
		
		if (!is_array($overlay)) return $model;
		
		foreach ($overlay as $propName=>$value) {

			if ($propInfo = $modelProperties[$propName] ?? false) {
				
				// Typ für Property des Models, z.B. `string`
				$propType = $this->get( $propInfo, 'type');

				if ($this->isSimpleType($propType)) {
					
					// -----
					// Es ist ein "einfacher Typ" (`string`, `int` etc.). Kann direkt gesetzt werden!

					$this->set( $model, $propName, $value );	
					continue;				
				}

				if (!class_exists($propType)) {
					\nn\t3::Exception( "Class of type `{$propType}` is not defined." );
				}

				// Es ist ein `Model`, `FileReference` etc.

				$child = \nn\t3::newClass( $propType );
				$curPropValue = $this->get( $model, $propName );

				if ($this->isFileReference($child)) {

					// -----
					// Die Property ist eine einzelne `SysFileReference` – keine `ObjectStorage`

					$curPublicUrl = \nn\t3::File()->getPublicUrl( $curPropValue );
					$publicUrl = \nn\t3::File()->getPublicUrl( $value );

					if ($curPublicUrl == $publicUrl) {
						// An der URL hat sich nichts geändert. Bisherige `SysFileReference` weiter verwenden.
						$value = $curPropValue;
					} else {

						// Neue URL. Falls bereits ein FAL am Model: Entfernen
						if ($this->isFileReference($curPropValue)) {
							$persistenceManager = GeneralUtility::makeInstance( PersistenceManager::class );
							$persistenceManager->remove( $curPropValue );
						}

						// ... und neues FAL erzeugen
						if ($value) {
							\nn\t3::Fal()->attach( $model, $propName, $value );
							continue;							
						} else {
							$value = null;
						}

					}
				}

				else if ($this->isStorage($child)) {

					// -----
					// Die Property ist eine `ObjectStorage`

					$value = $this->forceArray( $value );

					$childPropType = \nn\t3::Obj()->get($propInfo, 'elementType');

					if (!class_exists($childPropType)) {
						\nn\t3::Exception( "Class of type `{$childPropType}` is not defined." );
					}

					$storageItemInstance = \nn\t3::newClass( $childPropType );
					$isFileReference = $this->isFileReference( $storageItemInstance );

					// Array der existierende Items in der `ObjectStorage` holen. Key ist `uid` oder `publicUrl`
					$existingStorageItemsByUid = [];

					if ($curPropValue) {
						foreach ($curPropValue as $item) {
							$uid = $isFileReference ? \nn\t3::File()->getPublicUrl( $item ) : $this->get( $item, 'uid' );
							if (!isset($existingStorageItemsByUid)) {
								$existingStorageItemsByUid[$uid] = [];
							}
							$existingStorageItemsByUid[$uid][] = $item;
						}	
					}
					
					$objectStorage =  \nn\t3::newClass( get_class($child) );

					// Jedes Item in die Storage einfügen. Dabei werden bereits vorhandene Items aus der alten Storage verwendet.
					foreach ($value as $itemData) {

						$uid = false;
						// `[1, ...]`
						if (is_numeric($itemData)) $uid = $itemData;
						// `[['publicUrl'=>'bild.jpg'], ...]` oder `[['bild.jpg'], ...]`
						if (!$uid && $isFileReference) $uid = \nn\t3::File()->getPublicUrl( $itemData );
						// `[['uid'=>'1'], ...]`
						if (!$uid) $uid = $this->get( $itemData, 'uid' );

						// Gibt es das Item bereits? Dann vorhandenes Item verwenden, kein neues erzeugen!
						$arrayReference = $existingStorageItemsByUid[$uid] ?? [];
						$item = array_shift($arrayReference);	

						// Item bereits vorhanden?
						if ($item) {
							
							// ... dann das bisherige Item verwenden.
							// $item = \nn\t3::Obj( $item )->merge( $itemData );

						} else if ($isFileReference) {

							// sonst: Falls eine FileReference gewünscht ist, dann neu erzeugen!
							$item = \nn\t3::Fal()->createForModel( $model, $propName, $itemData );
							
						} else if ($uid) {

							// Alles AUSSER `FileReference` – und `uid` übergeben/bekannt? Dann das Model aus der Datenbank laden.
							$item = \nn\t3::Db()->get( $uid, $childPropType );
							
							// Model nicht in DB gefunden? Dann ignorieren.
							if (!$item) continue;

						} else {

							// Keine `FileReference` und KEINE `uid` übergeben? Dann neues Model erzeugen.
							$item = \nn\t3::newClass( $childPropType );

						}

						// Model konnte nicht erzeugt / gefunden werden? Dann ignorieren!
						if (!$item) continue;

						// Merge der neuen Overlay-Daten und ans Storage hängen
						$item = \nn\t3::Obj( $item )->merge( $itemData );
						$objectStorage->attach( $item );
					}

					$value = $objectStorage;
				}

				else if ( is_a($child, \DateTime::class, true )) {

					// -----
					// Die Property ist ein `DateTime`

					if ($value) {
						$value = (new \DateTime())->setTimestamp( $value );
					} else {
						$value = null;
					}
				}

				else {

					// -----
					// Property enthält eine einzelne Relation, ist aber weder eine `FileReference` noch eine `ObjectStorage`
					
					if ($uid = is_numeric($value) ? $value : $this->get( $value, 'uid' )) {
						$child = \nn\t3::Db()->get( $uid, get_class($child) );
						if (!$child) $value = null;
					}
					
					if ($value) {
						$value = \nn\t3::Obj( $child )->merge( $value );
					}
				}

				$this->set( $model, $propName, $value );

			}
		}

		return $model;
	}


	/**
	 * Prüft, ob es sich bei dem Object um ein Domain-Model handelt.
	 * ```
	 * \nn\t3::Obj()->isModel( $obj );
	 * ```
	 * @return boolean
	 */
	public function isModel ( $obj ) {
		if (!is_object($obj) || is_string($obj)) return false;
		return is_a($obj, \TYPO3\CMS\Extbase\DomainObject\AbstractEntity::class);
	}
	
	/**
	 * Prüft, ob es sich bei dem Object um eine Storage handelt.
	 * ```
	 * \nn\t3::Obj()->isStorage( $obj );
	 * ```
	 * @return boolean
	 */
	public function isStorage ( $obj ) {
		if (!is_object($obj) || is_string($obj)) return false;
		$type = get_class($obj);
		return is_a($obj, ObjectStorage::class) || $type == LazyObjectStorage::class || $type == ObjectStorage::class || $type == \TYPO3\CMS\Extbase\Persistence\ObjectStorage::class;
	}
	
	/**
	 * Prüft, ob es sich bei dem Object um eine `\TYPO3\CMS\Extbase\Domain\Model\FileReference` handelt.
	 * ```
	 * \nn\t3::Obj()->isFileReference( $obj );
	 * ```
	 * @return boolean
	 */
	public function isFileReference ( $obj ) {
		if (!is_object($obj)) return false;
		if (is_a($obj, \TYPO3\CMS\Extbase\Domain\Model\FileReference::class)) return true;
		$tableName = \nn\t3::Obj()->getTableName($obj);
		return $tableName == 'sys_file_reference';
	}
	
	/**
	 * Prüft, ob es sich bei dem Object um eine `\TYPO3\CMS\Core\Resource\FileReference` handelt.
	 * ```
	 * \nn\t3::Obj()->isFalFile( $obj );
	 * ```
	 * @return boolean
	 */
	public function isFalFile ( $obj ) {
		if (!is_object($obj)) return false;
		if (is_a($obj, \TYPO3\CMS\Core\Resource\FileReference::class)) return true;
		return false;
	}
	
	/**
	 * Prüft, ob es sich bei dem Object um ein `\TYPO3\CMS\Core\Resource\File` handelt.
	 * ```
	 * \nn\t3::Obj()->isFile( $obj );
	 * ```
	 * @return boolean
	 */
	public function isFile ( $obj ) {
		if (!is_object($obj)) return false;
		if (is_a($obj, \TYPO3\CMS\Core\Resource\File::class)) return true;
		return false;
	}
	

	/**
	 * Prüft, ob es sich bei dem Object um eine SysCategory handelt.
	 * Berücksichtigt alle Modelle, die in `sys_category` gespeichert werden.
	 * ```
	 * \nn\t3::Obj()->isSysCategory( $obj );
	 * 
	 * $cat = new \GeorgRinger\News\Domain\Model\Category();
	 * \nn\t3::Obj()->isSysCategory( $cat );
	 * ```
	 * @return boolean
	 */
	public function isSysCategory ( $obj ) {
		if (!is_object($obj)) return false;
		if (is_a($obj, \TYPO3\CMS\Extbase\Domain\Model\Category::class)) return true;
		$tableName = \nn\t3::Obj()->getTableName($obj);
		return $tableName == 'sys_category';
	}
	
	
	/**
	 * 	Gibt den DB-Tabellen-Namen für ein Model zurück
	 *
	 *	```
	 *	$model = new \Nng\MyExt\Domain\Model\Test;
	 *	\nn\t3::Obj()->getTableName( $model );	// 'tx_myext_domain_model_test'
	 *	\nn\t3::Obj()->getTableName( Test::class );	// 'tx_myext_domain_model_test'
	 *	```
	 * 	@return string
	 */
	public function getTableName ( $modelClassName = null ) {
		if (is_object($modelClassName)) {
			$modelClassName = get_class( $modelClassName );
		}
		$tableName = '';
		$dataMapper = GeneralUtility::makeInstance( DataMapper::class );
		try {
			$tableName = $dataMapper->getDataMap($modelClassName)->getTableName();
		} catch ( \Exception $e ) {
		} catch ( \Error $e ) {
			// silent
		}

		return $tableName;
	}

	/**
	 * Infos zum classSchema eines Models holen
	 * ```
	 * \nn\t3::Obj()->getClassSchema( \My\Model\Name::class );
	 * \nn\t3::Obj()->getClassSchema( $myModel );
	 * ```
	 * return DataMap
	 */
	public function getClassSchema( $modelClassName = null ) {
		if (is_object($modelClassName)) {
			$modelClassName = get_class( $modelClassName );
		}
		if ($cache = \nn\t3::Cache()->get($modelClassName, true)) {
			return $cache;
		}
		$reflectionService = GeneralUtility::makeInstance( ReflectionService::class);
		$schema = $reflectionService->getClassSchema($modelClassName);

		return \nn\t3::Cache()->set( $modelClassName, $schema, true );
	}
	
	/**
	 * Infos zu den Argumenten einer Methode holen.
	 * Berücksichtigt auch das per `@param` angegebene Typehinting, z.B. zu `ObjectStorage<ModelName>`.
	 * ```
	 * \nn\t3::Obj()->getMethodArguments( \My\Model\Name::class, 'myMethodName' );
	 * \nn\t3::Obj()->getMethodArguments( $myClassInstance, 'myMethodName' );
	 * ```
	 * Gibt als Beispiel zurück:
	 * ```
	 * 'varName' => [
	 * 	'type' => 'Storage<Model>', 
	 * 	'storageType' => 'Storage', 
	 * 	'elementType' => 'Model', 
	 *  'optional' => true, 
	 *  'defaultValue' => '123'
	 * ]
	 * ```
	 * return array
	 */
	public function getMethodArguments( $className = null, $methodName = null ) {

		$result = [];
		$method = $this->getClassSchema( $className )->getMethod( $methodName );

		$parameters = $method->getParameters();
		if (!$parameters) return [];

		foreach ($parameters as $param) {
			
			$paramType = $param->getType();
			$typeInfo = $this->parseType( $paramType );
			
			$result[$param->getName()] = [
				'type' 			=> $paramType,
				'simple' 		=> $typeInfo['simple'],
				'storageType' 	=> $typeInfo['type'],
				'elementType' 	=> $typeInfo['elementType'],
				'optional' 		=> $param->isOptional(),
				'defaultValue'	=> $param->getDefaultValue()
			];
		}	

		return $result;
	}

	/**
	 * Einen String mit Infos zu `ObjectStorage<Model>` parsen.
	 * ```
	 * \nn\t3::Obj()->parseType( 'string' );
	 * \nn\t3::Obj()->parseType( 'Nng\Nnrestapi\Domain\Model\ApiTest' );
	 * \nn\t3::Obj()->parseType( '\TYPO3\CMS\Extbase\Persistence\ObjectStorage<Nng\Nnrestapi\Domain\Model\ApiTest>' );
	 * ```
	 * Git ein Array mit Infos zurück:
	 * `type` ist dabei nur gesetzt, falls es ein Array oder eine ObjectStorage ist.
	 * `elementType` ist immer der Typ des Models oder das TypeHinting der Variable
	 *
	 * ```
	 * [
	 * 	'elementType' => 'Nng\Nnrestapi\Domain\Model\ApiTest', 
	 * 	'type' => 'TYPO3\CMS\Extbase\Persistence\ObjectStorage',
	 * 	'simple' => FALSE
	 * ]
	 * ```
	 *  
	 * @return array
	 */
	public function parseType( $paramType = '' ) {
		
		if (!$paramType || !trim($paramType)) {
			return ['elementType'=>'', 'type'=>'', 'simple'=>true ];
		}

		if (class_exists(TypeHandlingUtility::class)) {
			$typeInfo = \TYPO3\CMS\Extbase\Utility\TypeHandlingUtility::parseType( $paramType );
		} else {
			preg_match( '/([^<]*)<?([^>]*)?>?/', $paramType, $type );
			$typeInfo = [
				'elementType' => ltrim($type[2], '\\'), 
				'type' => ltrim($type[1], '\\')
			];
		}

		if (!$typeInfo['elementType']) {
			$typeInfo['elementType'] = $typeInfo['type'];
			$typeInfo['type'] = '';
		}

		$typeInfo['simple'] = $this->isSimpleType($typeInfo['elementType']);

		return $typeInfo;
	}

	/**
	 * Prüft, ob es sich bei einem Typ (string) um einen "einfachen" Typ handelt.
	 * Einfache Typen sind alle Typen außer Models, Klassen etc. - also z.B. `array`, `string`, `boolean` etc.
	 * ```
	 * $isSimple = \nn\t3::Obj()->isSimpleType( 'string' );							// true
	 * $isSimple = \nn\t3::Obj()->isSimpleType( \My\Extname\ClassName::class );		// false
	 * ```
	 * @return boolean
	 */
	public function isSimpleType( $type = '' ) {
		return in_array($type, ['array', 'string', 'float', 'double', 'integer', 'int', 'boolean', 'bool']);
	}

	/**
	 * Konvertiert ein Object in ein Array
	 * Bei Memory-Problemen wegen Rekursionen: Max-Tiefe angebenen!
	 * 
	 * ```
	 * \nn\t3::Obj()->toArray($obj, 2, ['uid', 'title']);
	 * \nn\t3::Obj()->toArray($obj, 1, ['uid', 'title', 'parent.uid']);
	 * ```
	 * @param mixed $obj 			ObjectStorage, Model oder Array das Konvertiert werden soll
	 * @param integer $depth 		Tiefe, die konvertiert werden soll. Bei rekursivem Konvertieren unbedingt nutzen
	 * @param array $fields 		nur diese Felder aus dem Object / Array zurückgeben
	 * @param boolean $addClass 	'__class' mit Infos zur Klasse ergänzen?
	 * 
	 * @return array
	 */
	public function toArray ( $obj, $depth = 3, $fields = [], $addClass = false ) {

		if ($obj === null) {
			return null;
		}

		$isSimpleType = $this->isSimpleType( gettype($obj) );
		$isStorage = !$isSimpleType && $this->isStorage($obj);

		if ($depth < 0) {
			return $isSimpleType && !is_array($obj) ? $obj : self::END_OF_RECURSION;
		}

		if ($isSimpleType && !is_array($obj)) {
			return $obj;
		}
		
		$type = is_object($obj) ? get_class($obj) : false;
		$final = [];
		$depth--;

		if (is_a($obj, \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult::class)) {
			
			$obj = $obj->toArray();

		} else if (is_a($obj, \DateTime::class)) {

			// DateTime in UTC konvertieren
			$utc = $obj->getTimestamp();
			return $utc;

		} else if ($isStorage) {
			
			// StorageObject in einfaches Array konvertieren
			$obj = $this->forceArray( $obj );
			if ($addClass) $obj['__class'] = ObjectStorage::class;

		} else if (\nn\t3::Obj()->isSysCategory($obj)) {

			//$categoryData = ['uid' => $obj->getUid(), 'title'=>$obj->getTitle(), 'parent'=>$parent];
			$categoryData = [];
			foreach ($this->getKeys($obj) as $k) {
				if ($k == 'parent') {
					$categoryData[$k] = $this->toArray( $obj->getParent(), $depth, $fields, $addClass );
					continue;
				}
				$categoryData[$k] = ObjectAccess::getProperty($obj, $k);
			}
			if ($addClass) $categoryData['__class'] = $type;
			return $categoryData;
			
		} else if (\nn\t3::Obj()->isFalFile($obj)) {

			// SysFile in Array konvertieren
			$falData = ['uid' => $obj->getUid(), 'title'=>$obj->getTitle(), 'publicUrl'=>$obj->getPublicUrl()];
			if ($addClass) $falData['__class'] = $type;
			return $falData;

		} else if (\nn\t3::Obj()->isFileReference($obj)) {
			
			// FileReference in einfaches Array konvertieren
			$map = ['uid', 'pid', 'title', 'alternative', 'link', 'description', 'size', 'publicUrl', 'crop', 'type'];
			$falData = [];
			if ($originalResource = $obj->getOriginalResource()) {
				$props = $originalResource->getProperties();
				$props['publicUrl'] = $originalResource->getPublicUrl();
				foreach ($map as $k=>$v) {
					$falData[$v] = $props[$v];
				}
			}

			// Falls FAL nicht über Backend erzeugt wurde, fehlt evtl. das Feld "crop". Also: mit default nachrüsten
			if (!$falData['crop']) {
				$falData['crop'] = json_encode(['default'=>['cropArea' => ['x'=>0, 'y'=>0, 'width'=>1, 'height'=>1]]]);
			}

			if ($addClass) $falData['__class'] = FalFileReference::class;
			return $falData;

		} else if ($type) {

			// Alle anderen Objekte
			$keys = $fields ?: $this->getKeys($obj);

			foreach ($keys as $field) {
				$val = $this->prop($obj, $field);
				$val = $this->toArray($val, $depth, $fields, $addClass);
				if ($val === self::END_OF_RECURSION) continue;
				$final[$field] = $val;
			}
			return $final;

		}

		foreach ($obj as $k=>$v) {
			$val = $this->toArray( $v, $depth, $fields, $addClass );
			if ($val === self::END_OF_RECURSION) continue;
			$final[$k] = $val;
		}

		return $final;		
	}
	
	/**
	 * 	Einzelne Properties eines Objects oder Arrays holen
	 *	```
	 *	\nn\t3::Obj()->props( $obj, ['uid', 'pid'] );
	 *	\nn\t3::Obj()->props( $obj, 'uid' );
	 *	```
	 *	@return array
	 */
	public function props ( $obj, $keys = [] ) {
		if (is_string($keys)) {
			$keys = [$keys];
		}
		$arr = [];
		foreach ($keys as $k) {
			$arr[$k] = $this->prop( $obj, $k );
		}
		return $arr;
	}

	/**
	 * Zugriff auf einen Key in einem Object oder Array.
	 * Der Key kann auch ein Pfad sein, z.B. "img.0.uid"
	 * 
	 * \nn\t3::Obj()->prop( $obj, 'img.0.uid' );
	 * 
	 * @param mixed $obj Model oder Array
	 * @param string $key der Key, der geholt werden soll
	 * 
	 * @return mixed
	 */	
	public function prop ( $obj, $key ) {
		if ($key == '') return '';
		$key = explode('.', $key);
		if (count($key) == 1) return $this->accessSingleProperty($obj, $key[0]);
		
		foreach ($key as $k) {
			$obj = $this->accessSingleProperty($obj, $k);
			if (!$obj) return '';
		}
		return $obj;
	}
	
	
	/**
	 * 	Setzt einen Wert in einem Object oder Array.
	 *	```
	 *	\nn\t3::Obj()->set( $obj, 'title', $val );
	 *	```
	 *
	 *	@param mixed $obj 			Model oder Array
	 *	@param string $key 			der Key / Property
	 *	@param mixed $val 			der Wert, der gesetzt werden soll
	 *	@param boolean $useSetter 	setKey()-Methode zum Setzen verwenden
	 * 
	 *	@return mixed
	 */	
	public function set( $obj, $key = '', $val = '', $useSetter = true) {

		$settable = ObjectAccess::isPropertySettable($obj, $key);

		if (!$settable) {
			$key = GeneralUtility::underscoredToLowerCamelCase( $key );
			$settable = ObjectAccess::isPropertySettable($obj, $key);
		}

		if ($settable) {
			if (is_object($obj)) {
				$modelProperties = $this->getProps($obj);
		
				if ($type = $modelProperties[$key] ?? false) {

					// SysFileReference wird gebraucht, aber SysFile wurde übergeben?
					if (is_a($type, FalFileReference::class, true )) {
						
						if (!$val) {
							$val = '';
						} else if ($this->isFile( $val )) {
							$val = \nn\t3::Fal()->fromFalFile( $val );
						}
					}

					// ObjectStorage soll geleert werden?
					if (is_a($type, ObjectStorage::class, true )) {
						$val = new ObjectStorage();
					}

					switch ($type) {
						case 'int':
							$val = (int)$val;
							break;
						case 'float':
							$val = (float)$val;
							break;
					}
				}
			}
	
			if (in_array($key, ['deleted', 'hidden'])) $val = $val ? true : false;
			ObjectAccess::setProperty($obj, $key, $val, !$useSetter);
		}
		return $obj;
	}
	
	/**
	 * 	Zugriff auf einen Wert in dem Object anhand des Keys
	 * 	Alias zu `\nn\t3::Obj()->accessSingleProperty()`
	 *	```
	 *	\nn\t3::Obj()->get( $obj, 'title' );
	 *	\nn\t3::Obj()->get( $obj, 'falMedia' );
	 *	\nn\t3::Obj()->get( $obj, 'fal_media' );
	 *	```
	 *
	 *	@param mixed $obj 			Model oder Array
	 *	@param string $key 			der Key / Property
	 * 
	 *	@return mixed
	 */	
	public function get( $obj, $key = '' ) {
		return $this->accessSingleProperty($obj, $key);
	}
	
	
	/**
	 * Zugriff auf einen Key in einem Object oder Array
	 * key muss einzelner String sein, kein Pfad
	 * 
	 * \nn\t3::Obj()->accessSingleProperty( $obj, 'uid' );
	 * \nn\t3::Obj()->accessSingleProperty( $obj, 'fal_media' );
	 * \nn\t3::Obj()->accessSingleProperty( $obj, 'falMedia' );
	 * 
	 * @param mixed $obj Model oder Array
	 * @param string $key der Key, der geholt werden soll
	 * 
	 * @return mixed
	 */	
	public function accessSingleProperty ( $obj, $key ) {
		if ($key == '') return '';

		if (is_object($obj)) {
			
			if (is_numeric($key)) {
				$obj = $this->forceArray($obj);
				return $obj[intval($key)];
			}

			$gettable = ObjectAccess::isPropertyGettable($obj, $key);
			if ($gettable) return ObjectAccess::getProperty($obj, $key);

			$camelCaseKey = GeneralUtility::underscoredToLowerCamelCase( $key );
			$gettable = ObjectAccess::isPropertyGettable($obj, $camelCaseKey);
			if ($gettable) return ObjectAccess::getProperty($obj, $camelCaseKey);

			return $obj->$key ?? null;

		} else {
			if (is_array($obj)) return $obj[$key] ?? null;
		}
		return [];
	}
	
	
	/**
	 * 	Zugriff auf ALLE Keys, die in einem Object zu holen sind
	 * 	```
	 *	\nn\t3::Obj()->getKeys( $model );									// ['uid', 'title', 'text', ...]
	 *	\nn\t3::Obj()->getKeys( $model );									// ['uid', 'title', 'text', ...]
	 *	\nn\t3::Obj()->getKeys( \Nng\MyExt\Domain\Model\Demo::class );		// ['uid', 'title', 'text', ...]
	 *	```
	 *	@param mixed $obj Model, Array oder Klassen-Name
	 * 	@return array
	 */	
	public function getKeys ( $obj ) {
		if (is_string($obj) && class_exists($obj)) {
			$obj = new $obj();	
		}
		$keys = [];
		if (is_object($obj)) {
			return ObjectAccess::getGettablePropertyNames($obj);
		} else if (is_array($obj)) {
			return array_keys($obj);
		}
		return [];
	}
	
	/**
	 * Alle keys eines Objektes holen, die einen SETTER haben.
	 * Im Gegensatz zu `\nn\t3::Obj()->getKeys()` werden nur die Property-Keys
	 * zurückgegeben, die sich auch setzen lassen, z.B. über `setNameDerProp()`
	 * 
	 * @return array
	 */
	public function getSetableKeys( $obj ) {
		$props = $this->getProps( $obj, null, true );
		return array_keys( $props );
	}
	

	/**
	 * 	Liste der Properties eines Objects oder Models mit Typ zurückgeben.
	 * 	```
	 *	\nn\t3::Obj()->getProps( $obj );			// ['uid'=>'integer', 'title'=>'string' ...]
	 *	\nn\t3::Obj()->getProps( $obj, true );		// ['uid'=>[type=>'integer', 'private'=>TRUE]]
	 *	\nn\t3::Obj()->getProps( $obj, 'default' );	// ['uid'=>TRUE]
	 *	\nn\t3::Obj()->getProps( \Nng\MyExt\Domain\Model\Demo::class );
	 *	```
	 *	@param mixed $obj 				Model oder Klassen-Name
	 *	@param mixed $key 				Wenn TRUE wird Array mit allen Infos geholt, z.B. auch default-Wert etc.
	 *	@param boolean $onlySettable 	Nur properties holen, die auch per setName() gesetzt werden können 
	 * 	@return array
	 */	
	public function getProps ( $obj, $key = 'type', $onlySettable = true ) {
		if (is_string($obj) && class_exists($obj)) {
			$obj = new $obj();
		}
		$schema = $this->getClassSchema( $obj );
		$properties = $schema->getProperties();

		if ($onlySettable) {
			$settables = array_flip(ObjectAccess::getSettablePropertyNames($obj));
			foreach ($properties as $k=>$p) {
				if (isset($settables[$k]) && !$settables[$k]) unset( $properties[$k] );
			}
		}

		$flatProps = [];
		foreach ($properties as $name=>$property) {
			$flatProps[$name] = $this->accessSingleProperty( $property, $key );
		}
		return $flatProps;
	}


	
	/**
	 * Konvertiert zu Array
	 * 
	 * @param mixed $obj
	 * 
	 * @return array
	 */	
	public function forceArray($obj) {
		if (!$obj) return [];
		if ($this->isStorage($obj)) {
			$tmp = [];
			foreach ($obj as $k=>$v) {
				$tmp[] = $v;
			}
			return $tmp;
		}
		return is_array($obj) ? $obj : [$obj];
	}
	
	
	/**
	 * Vergleicht zwei Objekte, gibt Array mit Unterschieden zurück.
	 * Existiert eine Property von objA nicht in objB, wird diese ignoriert.
	 * 
	 * ```
	 * // gibt Array mit Unterschieden zurück
	 * \nn\t3::Obj()->diff( $objA, $objB );
	 * 
	 * // ignoriert die Felder uid und title
	 * \nn\t3::Obj()->diff( $objA, $objB, ['uid', 'title'] );
	 * 
	 * // Vergleicht NUR die Felder title und bodytext
	 * \nn\t3::Obj()->diff( $objA, $objB, [], ['title', 'bodytext'] );
	 * 
	 * // Optionen
	 * \nn\t3::Obj()->diff( $objA, $objB, [], [], ['ignoreWhitespaces'=>true, 'ignoreTags'=>true, 'ignoreEncoding'=>true] );
	 * ```
	 *
	 * @param mixed $objA				Ein Object, Array oder Model
	 * @param mixed $objB				Das zu vergleichende Object oder Model
	 * @param array $fieldsToIgnore	    Liste der Properties, die ignoriert werden können. Leer = keine
	 * @param array $fieldsToCompare	Liste der Properties, die verglichen werden sollen. Leer = alle
	 * @param boolean $options  		Optionen / Toleranzen beim Vergleichen
	 * 									`ignoreWhitespaces` => Leerzeichen ignorieren
	 * 									`ignoreEncoding`	=> UTF8 / ISO-Encoding ignorieren
	 * 									`ignoreTags`		=> HTML-Tags ignorieren
	 * 
	 * @return array
	 */	
	public function diff( $objA, $objB, $fieldsToIgnore = [], $fieldsToCompare = [], $options = [] ) {

		$arrA = $this->toArray( $objA );
		$arrB = $this->toArray( $objB );

		// Keine Felder zum Vergleich angegeben? Dann alle nehmen
		if (!$fieldsToCompare) {
			$fieldsToCompare = array_keys( $arrA );
		}

		// Felder, die ignoriert werden sollen abziehen.
		$fieldsToCheck = array_diff( $fieldsToCompare, $fieldsToIgnore );

		$diff = [];
		foreach ($fieldsToCheck as $k=>$fieldName) {

			$hasDiff = false;
			$valA = $arrA[$fieldName];
			$valB = $arrB[$fieldName] ?? null;

			// Property existiert nur in objA? Dann ignorieren
			if (!isset($arrB[$fieldName])) continue;

			if (is_array($valA)) {

				// Vergleich eines Arrays
				$isStorage = is_array(\nn\t3::Arrays($valA)->first()) || is_array(\nn\t3::Arrays($valB)->first());
				if ($isStorage && count($valA) != count($valB)) {
					$hasDiff = true;
				}

				if ($arrDiff = $this->diff( $valA, $valB )) {
					$hasDiff = true;
				}

			} else {

				// Einfacher String-Vergleich

				$cmpA = $valA;
				$cmpB = $valB;
				
				if (is_string($cmpA) && is_string($cmpB)) {
					if ($options['ignoreWhitespaces'] ?? false) {
						$cmpA = preg_replace('/[\s\t]/', '', $cmpA);
						$cmpB = preg_replace('/[\s\t]/', '', $cmpB);
					}
					if ($options['ignoreTags'] ?? false) {
						$cmpA = strip_tags($cmpA);
						$cmpB = strip_tags($cmpB);
					}
					if ($options['ignoreEncoding'] ?? false) {
						$cmpA = \nn\t3::Convert($cmpA)->toUTF8();
						$cmpB = \nn\t3::Convert($cmpB)->toUTF8();
					}
				}

				$hasDiff = $cmpA != $cmpB;
			}

			// Gab es einen Unterschied? Dann diff-Array befüllen
			if ($hasDiff) {
				$diff[$fieldName] = [
					'from'	=> $valA, 
					'to'	=> $valB, 
				];
			} 

		}

		return $diff;
	}

}