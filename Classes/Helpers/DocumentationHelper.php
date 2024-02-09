<?php namespace Nng\Nnhelpers\Helpers;

use \Nng\Nnhelpers\Helpers\MarkdownHelper;

/**
 * Diverse Methoden zum Parsen von PHP-Quelltexten und Kommentaren im
 * Quelltext (Annotations). Zielsetzung: Automatisierte Dokumentation aus den Kommentaren
 * im PHP-Code erstellen.
 * 
 * Beispiele für die Verwendung inkl. Rendering des Templates
 * 
 * Im Controller mit __Rendering per Fluid:__
 * ```
 * $path = \nn\t3::Environment()->extPath('myext') . 'Classes/Utilities/';
 * $doc = \Nng\Nnhelpers\Helpers\DocumentationHelper::parseFolder( $path );
 * $this->view->assign('doc', $doc);
 * ``` 
 * Generieren der Typo3 / __Sphinx ReST-Doku__ über ein eigenen Fluid-Template:
 * ```
 * $path = \nn\t3::Environment()->extPath('myext') . 'Classes/Utilities/';
 * $doc = \Nng\Nnhelpers\Helpers\DocumentationHelper::parseFolder( $path );
 * 
 * foreach ($doc as $className=>$infos) {
 *   $rendering = \nn\t3::Template()->render(
 *     'EXT:myext/Resources/Private/Backend/Templates/Documentation/ClassTemplate.html', [
 *       'infos' => $infos
 *     ]
 *   );
 *   
 *   $filename = $infos['fileName'] . '.rst';
 *   $file = \nn\t3::File()->absPath('EXT:myext/Documentation/Utilities/Classes/' . $filename);
 *   $result = file_put_contents( $file, $rendering );
 * }
 * ```
 */
class DocumentationHelper {
	
	static $sourceCodeCache = [];

	/**
	 * Klassen-Name als String inkl. vollem Namespace aus einer PHP-Datei holen.
	 * Gibt z.B. `Nng\Classes\MyClass` zurück.
	 * 
	 * ```
	 * \Nng\Nnhelpers\Helpers\DocumentationHelper::getClassNameFromFile( 'Classes/MyClass.php' );
	 * ```
	 * @return string
	 */
	public static function getClassNameFromFile( $file ) 
	{
		$file = \nn\t3::File()->absPath( $file );

		$fileStr = php_strip_whitespace($file);

		$tokens = @token_get_all($fileStr);
		$namespace = $class = '';

		for ($i = 0; $i<count($tokens); $i++) {
			if ($tokens[$i][0] === T_NAMESPACE) {
				for ($j=$i+1;$j<count($tokens); $j++) {

					if ($tokens[$j][0] === T_STRING || (PHP_VERSION_ID >= 80000 && ($tokens[$j][0] == T_NAME_QUALIFIED || $tokens[$j][0] == T_NAME_FULLY_QUALIFIED))) {
						$namespace .= '\\'.$tokens[$j][1];
					} else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
						break;
					}
				}
			}

			if ($tokens[$i][0] === T_CLASS) {
				for ($j=$i+1;$j<count($tokens);$j++) {
					if ($tokens[$j] === '{') {
						$class = $tokens[$i+2][1] ?? null;
					}
				}
			}

			if ($class) break;
		}

		$className = ltrim( $namespace . '\\' . $class, '\\');
		return $className;
	}

	/**
	 * Einen Ordner (rekursiv) nach Klassen mit Annotations parsen.
	 * Gibt ein Array mit Informationen zu jeder Klasse und seinen Methoden zurück.
	 * 
	 * Die Annotations (Kommentare) über den Klassen-Methoden können in Markdown formatiert werden, sie werden automatisch in HTML mit passenden `<pre>` und `<code>` Tags umgewandelt.
	 * ```
	 * \Nng\Nnhelpers\Helpers\DocumentationHelper::parseFolder( 'Path/To/Classes/' );
	 * \Nng\Nnhelpers\Helpers\DocumentationHelper::parseFolder( 'EXT:myext/Classes/ViewHelpers/' );
	 * \Nng\Nnhelpers\Helpers\DocumentationHelper::parseFolder( 'Path/Somewhere/', ['recursive'=>false, 'suffix'=>'php', 'parseMethods'=>false] );
	 * ```
	 * @return array
	 */
	public static function parseFolder( $path = '', $options = [] ) 
	{
		$options = array_merge([
			'recursive' 	=> true,
			'suffix'		=> 'php',
			'parseMethods'	=> true,
		], $options);

		$classList = [];

		$folders = ["{$path}*.{$options['suffix']}"];
		if ($options['recursive']) {
			$folders[] = "{$path}*/*.{$options['suffix']}";
		}

		$fileList = [];
		foreach ($folders as $folder) {
			$files = glob($folder);			
			$fileList = array_merge($fileList, $files);
		}

		// Durch alle php-Dateien im Verzeichnis Classes/Utilities/ gehen
		foreach ($fileList as $path) {
			$classInfo = self::parseFile( $path, $options['parseMethods'] );
			$className = $classInfo['className'];
			$classList[$className] = $classInfo;
		}

		ksort($classList);
		return $classList;
	}

	/**
	 * Alle Infos zu einer einzelnen PHP-Datei holen.
	 * 
	 * Parsed den Kommentar (Annotation) über der Klassen-Definition und optional auch alle Methoden der Klasse.
	 * Gibt ein Array zurück, bei der auch die Argumente / Parameter jeder Methode aufgeführt werden.
	 * 
	 * Markdown kann in den Annotations verwendet werden, das Markdown wird automatisch in HTML-Code umgewandelt.
	 * ```
	 * \Nng\Nnhelpers\Helpers\DocumentationHelper::parseFile( 'Path/Classes/MyClass.php' );
	 * ```
	 * @return array
	 */
	public static function parseFile( $path = '', $returnMethods = true ) 
	{
		$className = self::getClassNameFromFile( $path );
		$data = self::parseClass( $className, $returnMethods );
		$data = array_merge($data, [
			'path' 		=> $path,
			'fileName'	=> pathinfo( $path, PATHINFO_FILENAME ),
		]);
		return $data;
	}
	
	/**
	 * Infos zu einer bestimmten Klasse holen.
	 * 
	 * Ähnelt `parseFile()` - allerdings muss hier der eigentliche Klassen-Name übergeben werden.
	 * Wenn man nur den Pfad zur PHP-Datei kennt, nutzt man `parseFile()`.
	 * ```
	 * \Nng\Nnhelpers\Helpers\DocumentationHelper::parseClass( \Nng\Classes\MyClass::class );
	 * ```
	 * @return array
	 */
	public static function parseClass( $className = '', $returnMethods = true ) 
	{
		$reflector = new \ReflectionClass( $className );
		$docComment = $reflector->getDocComment();

		$classComment = MarkdownHelper::parseComment( $docComment );

		$classInfo = [
			'className'		=> $className,
			'comment' 		=> $classComment,
			'rawComment'	=> $docComment,
			'methods'		=> [],
		];
		
		if (!$returnMethods) {
			return $classInfo;
		}

		// Durch alle Methoden der Klasse gehen
		foreach ($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

			if ($method->class == $reflector->getName()) {

				if (strpos($method->name, '__') === false) {
					$comment = MarkdownHelper::parseComment($method->getDocComment());

					$params = $method->getParameters();
					$paramInfo = [];
					$paramString = [];
					foreach ($params as $param) {
						
						$defaultValue = $param->isOptional() ? $param->getDefaultValue() : ''; 
						if (is_string($defaultValue)) $defaultValue = "'{$defaultValue}'";
						if ($defaultValue === false) $defaultValue = 'false';
						if ($defaultValue === true) $defaultValue = 'true';
						if (is_null($defaultValue)) $defaultValue = 'NULL';
						if ($defaultValue == 'Array' || is_array($defaultValue)) $defaultValue = '[]';

						$paramInfo[$param->getName()] = $defaultValue;
						$paramString[] = "\${$param->getName()}" . ($param->isOptional() ? " = {$defaultValue}" : '');
					}

					$classInfo['methods'][$method->name] = [
						'comment' 		=> $comment,
						'paramInfo'		=> $paramInfo,
						'paramString'	=> join(', ', $paramString),
						'sourceCode'	=> self::getSourceCode($method->class, $method->name),
					];
				}
			}
		}						
		
		ksort($classInfo['methods']);
		return $classInfo;
	}

	/**
	 * Quelltext einer Methode holen.
	 * 
	 * Gibt den "rohen" PHP-Code der Methode einer Klasse zurück.
	 * ```
	 * \Nng\Nnhelpers\Helpers\DocumentationHelper::parseClass( \Nng\Classes\MyClass::class, 'myMethodName' );
	 * ``` 
	 * @return string 	
	 */
	public static function getSourceCode($class, $method)
	{
		$func = new \ReflectionMethod($class, $method);
	
		$f = $func->getFileName();
		$start_line = $func->getStartLine() - 1;
		$end_line = $func->getEndLine();
		
		$cache = self::$sourceCodeCache[$f] ?? false;
		if (!$cache) {
			$source = file($f);
			$source = implode('', array_slice($source, 0, count($source)));
			$source = preg_split("/".PHP_EOL."/", $source);
			self::$sourceCodeCache[$f] = $source;
		}
		
		$source = self::$sourceCodeCache[$f];
		$body = "\n";
		for ($i=$start_line; $i<$end_line; $i++) {
			$body.="{$source[$i]}\n";
		}
		$body = str_replace('    ', "\t", $body);
		$body = str_replace("\n\t", "\n", $body);
		return $body;   
	}

}