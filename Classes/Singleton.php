<?php

namespace Nng\Nnhelpers;

/**
 * Einfaches Singleton-Pattern.
 * Funktioniert auch im Kontext, bei denen das Typo3 SingletonInterface versagt.
 * 
 * Die eigene Klasse extended das Singleton:
 * ```
 * class MyClass extends \Nng\Nnhelpers\Singleton {
 * 	...
 * }
 * ```
 * 
 * Die Instanzen dann ausschließlich über `injectClass` oder `newClass` instanziieren!
 * ```
 * $instance = \nn\t3::injectClass( \Nng\Whatever\MyClass::class );
 * ```
 * 
 */
abstract class Singleton {

	private static $instances = [];

	private function __construct() {}

	public static function makeInstance( $args = null ) {
	   	$className = get_called_class();
		if (!isset( self::$instances[ $className ] ) ) {
			self::$instances[ $className ] = new $className( $args );
		}
		return self::$instances[ $className ];
	}

	public function __clone() {}
}
