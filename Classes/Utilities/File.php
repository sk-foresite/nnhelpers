<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\File\BasicFileUtility;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Frontend\Utility\CompressionUtility;

/**
 * Methoden rund um das Dateisystem: 
 * Lesen, Schreiben, Kopieren, Verschieben und Bereinigen von Dateien.
 */
class File implements SingletonInterface {
   

	static $TYPES = [
		'image'		=> ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'webp', 'svg', 'eps', 'raw'],
		'video'		=> ['mp4', 'webm', 'mov', 'avi'],
		'audio'		=> ['mp3', 'aiff'],
		'document'	=> ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'ai', 'indd', 'txt', 'zip', 'tar'],
		'pdf'		=> ['pdf'],
		'enc'		=> ['enc'],
	];

	/**
     * @var string
     */
	const UNSAFE_FILENAME_CHARACTER_EXPRESSION = '\\x00-\\x2C\\/\\x3A-\\x3F\\x5B-\\x60\\x7B-\\xBF';
	
	/**
	 * Holt Pfad zur Datei, relativ zum Typo3-Installtionsverzeichnis (PATH_site).
	 * Kann mit allen Arten von Objekten umgehen.
	 * ```
	 * \nn\t3::File()->getPublicUrl( $falFile );		// \TYPO3\CMS\Core\Resource\FileReference
	 * \nn\t3::File()->getPublicUrl( $fileReference );	// \TYPO3\CMS\Extbase\Domain\Model\FileReference
	 * \nn\t3::File()->getPublicUrl( $folder );			// \TYPO3\CMS\Core\Resource\Folder
	 * \nn\t3::File()->getPublicUrl( $folder, true );	// https://.../fileadmin/bild.jpg
	 * ```
	 * @return string
	 */
	public function getPublicUrl( $obj = null, $absolute = false ) {
		$url = false;
		if (is_string($obj)) {
			$url = $obj;
		} else if (\nn\t3::Obj()->isFalFile( $obj ) || \nn\t3::Obj()->isFile( $obj )) {
			$url = $obj->getPublicUrl();
		} else if (\nn\t3::Obj()->isFileReference($obj)) {
			$url = $obj->getOriginalResource()->getPublicUrl();
		} else if (is_array($obj) && $url = ($obj['publicUrl'] ?? false)) {
			// $url kann genutzt werden!
		} else if (is_a($obj, \TYPO3\CMS\Core\Resource\Folder::class, true)) {
			$url = $obj->getPublicUrl();
		}
		$url = ltrim($url, '/');
		return !$absolute ? $url : $this->absUrl( $url );
	}

	/**
	 * Prüft, ob eine Datei existiert.
	 * Gibt absoluten Pfad zur Datei zurück.
	 * ```
	 * \nn\t3::File()->exists('fileadmin/bild.jpg');
	 * ```
	 * Existiert auch als ViewHelper:
	 * ```
	 * {nnt3:file.exists(file:'pfad/zum/bild.jpg')}
	 * ```
	 * @return string|boolean
	 */
	public function exists ( $src = null ) {

		if (file_exists( $src )) return $src;
		$src = $this->absPath( $src );

		if (file_exists( $src )) return $src;
		return false;
	}
	
	/**
	 * Gibt Dateigröße zu einer Datei in Bytes zurück
	 * Falls Datei nicht exisitert, wird 0 zurückgegeben.
	 * ```
	 * \nn\t3::File()->size('fileadmin/bild.jpg');
	 * ```
	 * @return integer
	 */
	public function size ( $src = null ) {
		$src = $this->exists( $src );
		if (!$src) return 0;
		return filesize($src);
	}
	
	/**
	 * Bereinigt einen Dateinamen
	 * ```
	 * $clean = \nn\t3::File()->cleanFilename('fileadmin/nö:so nicht.jpg');	// 'fileadmin/noe_so_nicht.jpg'
	 * ```
	 * @return string
	 */
	public function cleanFilename ( $filename = '' ) {
		$path = pathinfo( $filename, PATHINFO_DIRNAME ).'/';
		$suffix = strtolower(pathinfo( $filename, PATHINFO_EXTENSION ));
		$filename = pathinfo( $filename, PATHINFO_FILENAME );

		$filename = GeneralUtility::makeInstance(CharsetConverter::class)->utf8_char_mapping($filename);

		$cleanFilename = utf8_decode( $filename );
		$cleanFilename = strtolower(preg_replace('/[' . self::UNSAFE_FILENAME_CHARACTER_EXPRESSION . '\\xC0-\\xFF]/', '_', trim($cleanFilename)));
		$cleanFilename = str_replace(['@'], '_', $cleanFilename);

		if ($secondSuffix = pathinfo( $cleanFilename, PATHINFO_EXTENSION )) {
			$cleanFilename = substr( $cleanFilename, 0, -strlen($secondSuffix) );
			$suffix = "{$secondSuffix}.{$suffix}";
		}

		$cleanFilename = preg_replace('/_+/', '_', $cleanFilename);
		$cleanFilename = substr( $cleanFilename, 0, 64 - strlen($suffix) - 1);
		return $path . rtrim($cleanFilename, '.') . ".{$suffix}";
	}
	
	/**
	 * Erzeugt einen eindeutigen Dateinamen für die Datei, falls
	 * im Zielverzeichnis bereits eine Datei mit identischem Namen
	 * existiert.
	 * ```
	 * $name = \nn\t3::File()->uniqueFilename('fileadmin/01.jpg');	// 'fileadmin/01-1.jpg'
	 * ```
	 * @return string
	 */
	public function uniqueFilename ( $filename = '' ) {

		$filename = $this->cleanFilename( $filename );
		if (!$this->exists($filename)) return $filename;

		$path = pathinfo( $filename, PATHINFO_DIRNAME ).'/';
		$suffix = pathinfo( $filename, PATHINFO_EXTENSION );
		$filename = preg_replace('/-[0-9][0-9]$/', '', pathinfo( $filename, PATHINFO_FILENAME ));

		$i = 0;
		while ($i < 99) {
			$i++;
			$newName = $path . $filename . '-' . sprintf('%02d', $i) . '.' . $suffix;
			if (!$this->exists($newName)) return $newName;
		}
		return $path . $filename . '-' . uniqid() . '.' . $suffix;
	}


	/**
	 * Kopiert eine Datei.
	 * Gibt `false` zurück, falls die Datei nicht kopiert werden konnte.
	 * Gibt (neuen) Dateinamen zurück, falls das Kopieren erfolgreich war.
	 * 
	 * ```
	 * $filename = \nn\t3::File()->copy('fileadmin/bild.jpg', 'fileadmin/bild-kopie.jpg');
	 * ```
	 * @param string $src	Pfad zur Quelldatei 
	 * @param string $dest	Pfad zur Zieldatei 
	 * @param boolean $renameIfFileExists	Datei umbenennen, falls am Zielort bereits Datei mit gleichem Namen existiert
	 * @return string|boolean
	 */
	public function copy ( $src = null, $dest = null, $renameIfFileExists = true ) {
		
		if (!file_exists( $src )) return false;
		if (!$renameIfFileExists && $this->exists($dest)) return false;
		
		$dest = $this->uniqueFilename( $dest );
		$path = pathinfo( $dest, PATHINFO_DIRNAME ).'/';
		// Ordner anlegen, falls noch nicht vorhanden
		\nn\t3::Storage()->getFolder( $path );
		\TYPO3\CMS\Core\Utility\GeneralUtility::upload_copy_move($src, $dest);
		return $this->exists( $dest ) ? $dest : false;
	}
	
	/**
	 * Verschiebt eine Datei
	 * ```
	 * \nn\t3::File()->move('fileadmin/bild.jpg', 'fileadmin/bild-kopie.jpg');
	 * ```
	 * @return boolean
	 */
	public function move ( $src = null, $dest = null ) {
		if (!file_exists( $src )) return false;
		if (file_exists($dest)) return false;
		rename( $src, $dest );
		return file_exists( $dest );
	}
	
	/**
	 * Löscht eine Datei komplett vom Sever.
	 * Löscht auch alle `sys_file` und `sys_file_references`, die auf die Datei verweisen.
	 * Zur Sicherheit können keine PHP oder HTML Dateien gelöscht werden.
	 * 
	 * ```
	 * \nn\t3::File()->unlink('fileadmin/bild.jpg');					// Pfad zum Bild
	 * \nn\t3::File()->unlink('/abs/path/to/file/fileadmin/bild.jpg');	// absoluter Pfad zum Bild
	 * \nn\t3::File()->unlink('1:/my/image.jpg');						// Combined identifier Schreibweise
	 * \nn\t3::File()->unlink( $model->getImage() );					// \TYPO3\CMS\Extbase\Domain\Model\FileReference
	 * \nn\t3::File()->unlink( $falFile );			 					// \TYPO3\CMS\Core\Resource\FileReference
	 * ```
	 * @return boolean
	 */
	public function unlink ( $file = null ) {

		$file = $this->getPublicUrl( $file );
		if (!trim($file)) return false;

		$file = $this->absPath($this->absPath( $file ));
		\nn\t3::Fal()->deleteSysFile( $file );

		if (!$this->exists( $file )) return false;
		if (!$this->isAllowed( $file )) return false;

		@unlink( $file );

		if (file_exists( $file )) return false;
		
		return true;
	}

	/**
	 * Eine Upload-Datei ins Zielverzeichnis verschieben.
	 * 
	 * Kann absoluter Pfad zur tmp-Datei des Uploads sein – oder ein `TYPO3\CMS\Core\Http\UploadedFile`,
	 * das sich im Controller über `$this->request->getUploadedFiles()` holen lässt.
	 * ```
	 * \nn\t3::File()->moveUploadedFile('/tmp/xjauGSaudsha', 'fileadmin/bild-kopie.jpg');
	 * \nn\t3::File()->moveUploadedFile( $fileObj, 'fileadmin/bild-kopie.jpg');
	 * ```
	 * @return string
	 */
	public function moveUploadedFile ( $src = null, $dest = null ) {

		$dest = $this->uniqueFilename($this->absPath( $dest ));

		if (!$this->isAllowed($dest)) {
			\nn\t3::Exception('\nn\t3::File()->moveUploadedFile() :: Filetype not allowed.');
			return false;
		}

		if (!is_string($src) && is_a($src, \TYPO3\CMS\Core\Http\UploadedFile::class)) {

			if ($stream = $src->getStream()) {
				$handle = fopen($dest, 'wb+');
				if ($handle === false) return false;
				$stream->rewind();
				while (!$stream->eof()) {
					$bytes = $stream->read(4096);
					fwrite($handle, $bytes);
				}
				fclose($handle);
			}

		} else {
			$src = $this->absPath( $src );
			move_uploaded_file( $src, $dest );
		}

		if (file_exists($dest)) {
			return $dest;
		}
		return false;
	}

	/**
	 * Absolute URL zu einer Datei generieren.
	 * Gibt den kompletten Pfad zur Datei inkl. `https://.../` zurück.
	 * 
	 * ```
	 * // => https://www.myweb.de/fileadmin/bild.jpg
	 * \nn\t3::File()->absUrl( 'fileadmin/bild.jpg' );
	 * 
	 * // => https://www.myweb.de/fileadmin/bild.jpg
	 * \nn\t3::File()->absUrl( 'https://www.myweb.de/fileadmin/bild.jpg' );
	 * 
	 * // => /var/www/vhost/somewhere/fileadmin/bild.jpg
	 * \nn\t3::File()->absUrl( 'https://www.myweb.de/fileadmin/bild.jpg' );
	 * ```
	 * 
	 * @return string
	 */
	public function absUrl( $file = null ) {
		$baseUrl = \nn\t3::Environment()->getBaseURL();
		$file = $this->stripPathSite( $file );
		$file = str_replace( $baseUrl, '', $file );
		return $baseUrl . ltrim( $file, '/' );
	}

	/**
	 * Absoluter Pfad zu einer Datei auf dem Server.
	 * 
	 * Gibt den kompletten Pfad ab der Server-Root zurück, z.B. ab `/var/www/...`.
	 * Falls der Pfad bereits absolut war, wird er unverändert zurückgegeben.
	 * 
	 * ```
	 * \nn\t3::File()->absPath('fileadmin/bild.jpg'); 					// => /var/www/website/fileadmin/bild.jpg
	 * \nn\t3::File()->absPath('/var/www/website/fileadmin/bild.jpg'); 	// => /var/www/website/fileadmin/bild.jpg
	 * \nn\t3::File()->absPath('EXT:nnhelpers'); 					 	// => /var/www/website/typo3conf/ext/nnhelpers/
	 * ```
	 * 
	 * Außer dem Dateipfad als String können auch alle denkbaren Objekte übergeben werden:
	 * ```
	 * // \TYPO3\CMS\Core\Resource\Folder
	 * \nn\t3::File()->absPath( $folderObject ); 	=> /var/www/website/fileadmin/bild.jpg
	 * 
	 * // \TYPO3\CMS\Core\Resource\File
	 * \nn\t3::File()->absPath( $fileObject ); 		=> /var/www/website/fileadmin/bild.jpg
	 * 
	 * // \TYPO3\CMS\Extbase\Domain\Model\FileReference
	 * \nn\t3::File()->absPath( $fileReference ); 	=> /var/www/website/fileadmin/bild.jpg
	 * ```
	 * 
	 * Existiert auch als ViewHelper:
	 * ```
	 * {nnt3:file.absPath(file:'pfad/zum/bild.jpg')}
	 * ```
	 * @return boolean
	 */
	public function absPath ( $file = null, $resolveSymLinks = false ) {

		if (\nn\t3::Obj()->isFileReference($file)) {
			$file = $file->getOriginalResource()->getOriginalFile();
			return $file->getForLocalProcessing(false);
		}

		if (!is_string($file)) {
			$file = $this->getPublicUrl( $file );
		}

		if (strpos($file, sys_get_temp_dir()) !== false) {
			return $file;
		}
		
		// Prüfen, ob ein Symlink im Spiel ist
		if ($resolveSymLinks && is_link(dirname($file))) {
			$link = readlink(dirname($file));
			$file = realpath(dirname($file) . '/' . $link) . '/' . basename($file);
		}
		
		$file = $this->resolvePathPrefixes( $file );
		$file = $this->normalizePath( $file );

		return GeneralUtility::getFileAbsFileName($file);
	}
	
	/**
	 * EXT: Prefix auflösen zu relativer Pfadangabe
	 * ```
	 * \nn\t3::File()->resolvePathPrefixes('EXT:extname'); 					=> /typo3conf/ext/extname/
	 * \nn\t3::File()->resolvePathPrefixes('EXT:extname/'); 				=> /typo3conf/ext/extname/
	 * \nn\t3::File()->resolvePathPrefixes('EXT:extname/bild.jpg'); 		=> /typo3conf/ext/extname/bild.jpg
	 * \nn\t3::File()->resolvePathPrefixes('1:/uploads/bild.jpg', true); 	=> /var/www/website/fileadmin/uploads/bild.jpg
	 * ```
	 * @return string
	 */
	public function resolvePathPrefixes ( $file = null, $absolute = false ) {

		// `1:/uploads`
		if (preg_match('/^([0-9]*)(:\/)(.*)/i', $file, $matches)) {
			$resourceFactory = GeneralUtility::makeInstance( ResourceFactory::class );
			$storage = $resourceFactory->getStorageObject($matches[1]);
			if (!$storage) return $file;
			$basePath = $storage->getConfiguration()['basePath'];
			$file = $basePath . $matches[3];
		}

		// `EXT:extname` => `EXT:extname/`
		if (strpos($file, 'EXT:') == 0 && !pathinfo($file, PATHINFO_EXTENSION)) {
			$file = rtrim($file, '/') . '/';
		}

		// `EXT:extname/` => `typo3conf/ext/extname/` 
		$absPathName = GeneralUtility::getFileAbsFileName( $file );
		if (!$absPathName) return $file;

		if ($absolute) return $this->absPath($absPathName);
		$pathSite = \nn\t3::Environment()->getPathSite();
		return str_replace( $pathSite, '', $absPathName );
	}
	
	/**
	 * relativen Pfad (vom aktuellen Script aus) zum einer Datei / Verzeichnis zurück.
	 * Wird kein Pfad angegeben, wird das Typo3-Root-Verzeichnis zurückgegeben.
	 * 
	 * ```
	 * \nn\t3::File()->relPath( $file );		=> ../fileadmin/bild.jpg
	 * \nn\t3::File()->relPath();				=> ../
	 * ```
	 *
	 * @return string
	 */
	public function relPath ( $path = '' ) {
		if (!$path) $path = \nn\t3::Environment()->getPathSite();
		$isFolder = $this->isFolder( $path );

		$path = $this->absPath( $path );
		$name = rtrim(PathUtility::getRelativePathTo($path), '/');

		if ($isFolder) $name .= '/';
		return $name;
	}


	/**
	 * Löst ../../-Angaben in Pfad auf.
	 * Funktioniert sowohl mit existierenden Pfaden (per realpath) als auch
	 * nicht-existierenden Pfaden.
	 * ```
	 * \nn\t3::File()->normalizePath( 'fileadmin/test/../bild.jpg' );		=>	fileadmin/bild.jpg
	 * ```
	 * @return string	
	 */
	public function normalizePath( $path ) {
		$hasTrailingSlash = substr($path,-1) == '/';
		$hasStartingSlash = substr($path,0,1) == '/';

		$path = array_reduce(explode('/', $path), function($a, $b) {
			if ($a === 0 || $a === null) $a = '/';
			if ($b === '' || $b === '.') return $a;
			if ($b === '..') return dirname($a);
			return preg_replace('/\/+/', '/', "{$a}/{$b}");
		}, 0);

		if (!$hasStartingSlash) $path = ltrim( $path, '/');
		$isFolder = is_dir( $path ) || $hasTrailingSlash;
		$path = rtrim( $path, '/' );
		if ($isFolder) $path .= '/';
		return $path;
	}


	/**
	 * Einen Ordner im `fileadmin/` erzeugen.
	 * Um einen Ordner außerhalb des `fileadmin` anzulegen, die Methode `\nn\t3::File()->mkdir()` verwenden.
	 * 
	 * ```
	 * \nn\t3::File()->createFolder('tests');
	 * ```
	 * @return boolean
	 */
	public function createFolder ( $path = null ) {
		$resourceFactory = GeneralUtility::makeInstance( ResourceFactory::class );
		$defaultStorage = $resourceFactory->getDefaultStorage();
		$basePath = \nn\t3::Environment()->getPathSite() . $defaultStorage->getConfiguration()['basePath'];
		if (file_exists( $basePath . $path)) return true;
		$defaultStorage->createFolder( $path );
	}
	
	/**
	 * Gibt an, ob der Dateityp verboten ist
	 * ```
	 * \nn\t3::File()->isForbidden('bild.jpg');					=> gibt 'false' zurück
	 * \nn\t3::File()->isForbidden('bild.xyz', ['xyz']);		=> gibt 'false' zurück
	 * \nn\t3::File()->isForbidden('hack.php');					=> gibt 'true' zurück
	 * \nn\t3::File()->isForbidden('.htaccess');				=> gibt 'true' zurück
	 * ```
	 * @param string $filename
	 * @param array $allowed
	 * @return boolean
	 */
	// isForbidden
	public function isForbidden ( $filename = null, $allowed = [] ) 
	{
		if (!$filename) return false;
		if (substr($filename, 0, 1) == '.') return true;

		if (!$allowed) {
			$types = array_values(self::$TYPES);
			$allowed = array_merge(...$types);
		}

		$suffix = $this->suffix($filename);
		return !in_array($suffix, $allowed );
	}
	
	/**
	 * Gibt an, ob der Dateityp erlaubt ist
	 * ```
	 * \nn\t3::File()->isForbidden('bild.jpg');	=> gibt 'true' zurück
	 * \nn\t3::File()->isForbidden('hack.php');	=> gibt 'false' zurück
	 * ```
	 * @return boolean
	 */
	public function isAllowed ( $filename = null ) {
		return !$this->isForbidden( $filename );
	}

	/**
	 * Gibt die Art der Datei anhand des Datei-Suffixes zurück
	 * ```
	 * \nn\t3::File()->type('bild.jpg');	=> gibt 'image' zurück
	 * \nn\t3::File()->type('text.doc');	=> gibt 'document' zurück
	 * ```
	 * @return string
	 */
	// filetype
	public function type ( $filename = null ) {
		if (!$filename) return false;
		$suffix = $this->suffix($filename);
		foreach (self::$TYPES as $k=>$arr) {
			if (in_array($suffix, $arr)) return $k; 
		}
		return 'other';
	}

	/**
	 * Gibt an, ob die Datei ein Video ist
	 * ```
	 * \nn\t3::File()->isVideo('pfad/zum/video.mp4');		=> gibt true zurück
	 * ```
	 * @return boolean
	 */	
	public function isVideo ( $filename = null ) {
		return $this->type( $filename ) == 'video';
	}

	/**
	 * Gibt an, ob es ein Video auf YouTube / Vimeo ist.
	 * Falls ja, wird ein Array mit Angaben zum Einbetten zurückgegeben.	
	 * ```
	 * \nn\t3::File()->isExternalVideo('http://...');
	 * ```
	 * @return array|boolean
	 */	
	public function isExternalVideo ( $url = null ) {
		return \nn\t3::Video()->getExternalType( $url );
	}

	/**
	 * Gibt an, ob die Datei in ein Bild ist
	 * ```
	 * \nn\t3::File()->isImage('bild.jpg');	=> gibt true zurück
	 * \nn\t3::File()->isImage('text.ppt');	=> gibt false zurück
	 * ```
	 * @return boolean
	 */
	public function isImage ( $filename = null ) {
		if (!$filename) return false;
		$suffix = $this->suffix($filename);
		$arr = array_merge(self::$TYPES['image']);
		return in_array($suffix, $arr);
	}

	/**
	 * Gibt an, ob die Datei in ein Bild konvertiert werden kann
	 * ```
	 * \nn\t3::File()->isConvertableToImage('bild.jpg');	=> gibt true zurück
	 * \nn\t3::File()->isConvertableToImage('text.ppt');	=> gibt false zurück
	 * ```
	 * @return boolean
	 */
	public function isConvertableToImage ( $filename = null ) {
		if (!$filename) return false;
		$suffix = $this->suffix($filename);
		$arr = array_merge(self::$TYPES['image'], self::$TYPES['pdf']);
		return in_array($suffix, $arr);
	}

	/**
	 * Gibt den Suffix der Datei zurück
	 * ```
	 * \nn\t3::File()->suffix('bild.jpg');	=> gibt 'jpg' zurück
	 * ```
	 * @return string
	 */
	public function suffix ( $filename = null ) {
		if (!$filename) return false;
		$suffix = strtolower(pathinfo( $filename, PATHINFO_EXTENSION ));
		if ($suffix == 'jpeg') $suffix = 'jpg';
		return $suffix;
	}

	/**
	 * Ersetzt den suffix für einen Dateinamen.
	 * ```
	 * \nn\t3::File()->addSuffix('bild', 'jpg');				//	=> bild.jpg
	 * \nn\t3::File()->addSuffix('bild.png', 'jpg');			//	=> bild.jpg
	 * \nn\t3::File()->addSuffix('pfad/zu/bild.png', 'jpg');	//	=> pfad/zu/bild.jpg
	 * ```
	 * @return string
	 */
	public function addSuffix ( $filename = null, $newSuffix = '' ) {
		$suffix = strtolower(pathinfo( $filename, PATHINFO_EXTENSION ));
		if ($suffix) {
			$filename = substr($filename, 0, -strlen($suffix)-1);
		}
		return $filename . '.' . $newSuffix;
	}
	
	/**
	 * Gibt den Suffix für einen bestimmten Mime-Type / Content-Type zurück.
	 * Sehr reduzierte Variante – nur wenige Typen abgedeckt.
	 * Umfangreiche Version: https://bit.ly/3B9KrNA
	 * ```
	 * \nn\t3::File()->suffixForMimeType('image/jpeg');	=> gibt 'jpg' zurück
	 * ```
	 * @return string
	 */
	public function suffixForMimeType ( $mime = '' ) {
		$mime = array_pop(explode('/', strtolower($mime)));
		$map = [
			'jpeg' 	=> 'jpg',
			'jpg' 	=> 'jpg',
			'gif' 	=> 'gif',
			'png' 	=> 'png',
			'pdf' 	=> 'pdf',
			'tiff' 	=> 'tif',
		];
		foreach ($map as $sword=>$suffix) {
			if (strpos($mime, $sword) !== false) {
				return $suffix;
			}
		}
		return $mime;
	}

	/**
	 * Findet ein passendes sys_file_storage zu einem Datei- oder Ordnerpfad.
	 * Durchsucht dazu alle sys_file_storage-Einträge und vergleicht, 
	 * ob der basePath des Storages zum Pfad der Datei passt.
	 * ```
	 * \nn\t3::File()->getStorage('fileadmin/test/beispiel.txt');
	 * \nn\t3::File()->getStorage( $falFile );
	 * \nn\t3::File()->getStorage( $sysFileReference );
	 * // gibt ResourceStorage mit basePath "fileadmin/" zurück
	 * ```
	 * @return ResourceStorage
	 */
	public function getStorage( $file, $createIfNotExists = false ) {
		if (!is_string($file)) {
			if (\nn\t3::Obj()->isFalFile( $file ) || \nn\t3::Obj()->isFile( $file )) {
				return $file->getStorage();
			} else if (\nn\t3::Obj()->isFileReference($file)) {
				return $file->getOriginalResource()->getStorage();
			}
			return false;
		}

		// ToDo: Prüfen, ob über ResourceFactory lösbar ResourceFactory::getInstance()->retrieveFileOrFolderObject($filenameOrSysFile->getOriginalResource()->getPublicUrl());

		$file = $this->stripPathSite( $file );
		$storageRepository = \nn\t3::Storage();
		if (!trim($file)) return false;
		
		if ($this->isFolder($file)) {
			$file = rtrim($file, '/').'/';
		}

		$file = ltrim($file, '/');
		$dirname = $this->getFolder($file);
		
		$storage = false;
		$curPrecision = 0;
		
		$allStorages = $storageRepository->findAll();
		foreach ($allStorages as $row) {
			$arr = $row->getConfiguration();

			$basePath = $arr['basePath'];

			if ($arr['pathType'] == 'absolute') {
				$basePath = ltrim($basePath, '/');
			}

			$dirnameSubstr = substr($dirname, 0, strlen($basePath));
			
			if ($basePath == $dirnameSubstr && $curPrecision < strlen($arr['basePath'])) {
				$storage = $row;
				$curPrecision = strlen($arr['basePath']);
			}
		}

		$allowCreateConf = \nn\t3::Settings()->getExtConf('nnhelpers')['autoCreateFilemounts'] ?? true;
		
		if ($allowCreateConf && $createIfNotExists && !$storage) {
			$uid = $storageRepository->createLocalStorage( $dirname.' (nnhelpers)', $dirname, 'relative' );
			$storageRepository->clearStorageRowCache();
			$storage = $storageRepository->findByUid( $uid );			
		}
		
		if (!$storage) {
			\nn\t3::Exception("nnhelpers: Storage for file {$file} was not found and autocreate was disabled.");
		}

		return $storage;
	}

	/**
	 * Gibt zurück, ob angegebener Pfad ein Ordner ist
	 * 
	 * Beispiel:
	 * ```
	 * \nn\t3::File()->isFolder('fileadmin'); // => gibt true zurück
	 * ```
	 * @return boolean
	 */
	public function isFolder( $file ) {
		if (substr($file,-1) == '/') return true;
		return is_dir( $this->absPath($file) );
	}
	
	/**
	 * Gibt Pfad zu Datei / Ordner OHNE absoluten Pfad.
	 * Optional kann ein Prefix angegeben werden. 
	 * 
	 * Beispiel:
	 * ```
	 * \nn\t3::File()->stripPathSite('var/www/website/fileadmin/test.jpg'); 		==>	fileadmin/test.jpg
	 * \nn\t3::File()->stripPathSite('var/www/website/fileadmin/test.jpg', true); 	==>	var/www/website/fileadmin/test.jpg
	 * \nn\t3::File()->stripPathSite('fileadmin/test.jpg', true); 					==>	var/www/website/fileadmin/test.jpg
	 * \nn\t3::File()->stripPathSite('fileadmin/test.jpg', '../../'); 				==>	../../fileadmin/test.jpg
	 * ```
	 * @return string
	 */
	public function stripPathSite( $file, $prefix = false ) {
		$pathSite = \nn\t3::Environment()->getPathSite();
		$file = str_replace($pathSite, '', $file);
		if ($prefix === true) {
			$file = $pathSite . $file;
		} else if ($prefix !== false) {
			$file = $prefix . $file;
		}
		return $file;
	}
	
	/**
	 * Gibt Pfad zu Datei / Ordner MIT absoluten Pfad
	 * 
	 * Beispiel: 
	 * ```
	 * \nn\t3::File()->addPathSite('fileadmin/test.jpg');
	 *  // ==> gibt var/www/website/fileadmin/test.jpg zurück
	 * ```
	 * @return string
	 */
	public function addPathSite( $file ) {
		return $this->stripPathSite( $file, true );
	}
	

	/**
	 * Gibt den Ordner zu einer Datei zurück
	 * 
	 * Beispiel:
	 * ```
	 * \nn\t3::File()->getFolder('fileadmin/test/beispiel.txt');
	 * // ==> gibt 'fileadmin/test/' zurück
	 * ```
	 * @return string
	 */
	// getFolderPathForFile
	public function getFolder( $file ) {
		$pathSite = \nn\t3::Environment()->getPathSite();
		$file = str_replace($pathSite, '', $file);
		if (substr($file, -1) == '/') return $file;
		if (is_dir($pathSite.$file)) return $file;
		if (!pathinfo($file, PATHINFO_EXTENSION)) return $file . '/';
		return dirname($file).'/';
	}

	/**
	 * Gibt den relativen Pfad einer Datei zur angegebenen Storage wieder.
	 * 
	 * Beispiel:
	 * ```
	 * \nn\t3::File()->getRelativePathInStorage('fileadmin/media/bild.jpg', $storage); 
	 * // ==> gibt 'media/bild.jpg' zurück	
	 * ```
	 * @return string
	 */
	// getRelativePathForFileInStorage
	public function getRelativePathInStorage( $file, $storage = null ) {

		$file = $this->stripPathSite( $file );
		$resource = GeneralUtility::makeInstance( ResourceFactory::class )->retrieveFileOrFolderObject( $file );
		
		if (!$resource) return false;
		return ltrim($resource->getIdentifier(), '/');

		// ToDo: Prüfen, ob über ResourceFactory lösbar ResourceFactory::getInstance()->retrieveFileOrFolderObject($filenameOrSysFile->getOriginalResource()->getPublicUrl());

		$storage = $storage ?: $this->getStorage( $file );
		if (!$storage) return false;

		$storageConfiguration = $storage->getConfiguration();
		$storageFolder = $storageConfiguration['basePath'];
		$basename = substr( $file, strlen($storageFolder) );
		if (!file_exists(\nn\t3::Environment()->getPathSite() . $storageFolder . $basename)) return false;
		return $basename;
	}
	
	/**
	 * Gibt den Pfad einer Datei anhand eines Dateinamens und der Storage wieder.
	 * Beispiel:
	 * ```
	 * \nn\t3::File()->getPath('media/bild.jpg', $storage);		
	 * // ==> gibt '/var/www/.../fileadmin/media/bild.jpg' zurück	
	 * \nn\t3::File()->getPath('fileadmin/media/bild.jpg');		
	 * // ==> gibt '/var/www/.../fileadmin/media/bild.jpg' zurück	
	 * ```
	 * @return string
	 */
	public function getPath( $file, $storage = null, $absolute = true ) {

		// ToDo: Prüfen, ob über ResourceFactory lösbar ResourceFactory::getInstance()->retrieveFileOrFolderObject($filenameOrSysFile->getOriginalResource()->getPublicUrl());

		if (is_string($file)) {
			$file = ltrim($file, '/');
			$storage = $storage ?: $this->getStorage( $file );
			if (!$storage) return false;

			$storageConfiguration = $storage->getConfiguration();
			$storageFolder = $storageConfiguration['basePath'];
	
		} else {
			$file = $this->getPublicUrl($file);
			$storageFolder = '';
		}
	
		$relPath = $storageFolder . $file;
		$absPath = \nn\t3::Environment()->getPathSite() . $storageFolder . $file;

		if (file_exists($absPath)) return $absolute ? $absPath : $relPath;
		return false;
	}

	/**
	 * Holt den Inhalt einer Datei
	 * ```
	 * \nn\t3::File()->read('fileadmin/text.txt');
	 * ```
	 *	@return string|boolean
	 */
	public function read ( $src = null ) {

		if (!$src || !$this->exists( $src )) return '';
		$absPath = $this->absPath( $src );
		if (!$absPath) return '';
		return file_get_contents( $absPath );
	}

	/**
	 * Einen Ordner und/oder Datei erzeugen.
	 * Legt auch die Ordner an, falls sie nicht existieren.
	 * ```
	 * \nn\t3::File()->write('fileadmin/some/deep/folder/');
	 * \nn\t3::File()->write('1:/some/deep/folder/');
	 * \nn\t3::File()->write('fileadmin/some/deep/folder/file.json', 'TEXT');
	 * ```
	 * @return boolean
	 */
	public function write ( $path = null, $content = null ) {
		$path = \nn\t3::File()->absPath( $path );
		$folder = pathinfo( $path, PATHINFO_DIRNAME );
		
		$exists = \nn\t3::File()->mkdir( $folder );
		if ($exists && $content !== null) {
			return file_put_contents( $path, $content ) !== false;
		}

		return $exists;
	}

	/**
	 * Einen Ordner anlegen
	 * ```
	 * \nn\t3::File()->mkdir( 'fileadmin/mein/ordner/' );
	 * \nn\t3::File()->mkdir( '1:/mein/ordner/' );
	 * ```
	 * @return boolean
	 */
	public function mkdir( $path = '' ) {
		if (\nn\t3::File()->exists($path)) return true;
		$path = \nn\t3::File()->absPath(rtrim($path, '/').'/');
		\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep( $path );
		return \nn\t3::File()->exists( $path );
	}

	/**
	 * Imageinfo + EXIF Data für Datei holen. 
	 * Sucht auch nach JSON-Datei, die evtl. nach processImage() generiert wurde
	 * 
	 * @return array	
	 */
	public function getData ( $file = '' ) {
	
		if (!is_string($file)) {
			$file = $this->getPath( $file );
		}
		if (!file_exists($file)) $file = \nn\t3::Environment()->getPathSite() . $file;
		if (!file_exists($file)) return [];
		
		// Dateiname der JSON-Datei: Identisch mit Bildname, aber suffix .json
		$pathParts = pathinfo($file);
		$jsonFilename = $pathParts['dirname'].'/'.$pathParts['filename'].'.json';

		// Wurde kein JSON für Datei generiert? Dann über Library EXIF-Daten extrahieren
		if (!file_exists($jsonFilename)) {
			return $this->getExifData( $file );		
		}

		// JSON existiert. imageSize trotzdem aktualisieren, weil evtl. processImage() im Einsatz war
		if ($rawData = file_get_contents($jsonFilename)) {
			$jsonData = json_decode($rawData, true);
			return \nn\t3::Arrays( $jsonData )->merge( $this->getImageSize( $file ) );
		}
		
		return [];
	}


	/**
	 * ALLE EXIF Daten für Datei holen.
	 * ```
	 * \nn\t3::File()->getExif( 'yellowstone.jpg' );
	 * ```
	 * @return array
	 */
	public function getExifData( $filename = '' ) {
		return array_merge( 
			$this->getImageSize( $filename ),
			$this->getImageData( $filename ),
			$this->getLocationData( $filename )
		);
	}
	
	/**
	 * EXIF Daten für Datei in JSON speichern.
	 * ```
	 * \nn\t3::File()->extractExifData( 'yellowstone.jpg' );
	 * ```
	 * @return array
	 */
	public function extractExifData( $filename = '' ) {
		$exif = $this->getData( $filename );
		$pathParts = pathinfo($filename);
		$jsonFilename = $pathParts['dirname'].'/'.$pathParts['filename'].'.json';
		file_put_contents( $jsonFilename, json_encode( $exif ) );
		return $exif;	
	}
	
	/**
	 * imagesize für Datei holen.
	 * ```
	 * \nn\t3::File()->getImageSize( 'yellowstone.jpg' );
	 * ```
	 * @return array
	 */
	public function getImageSize( $filename = '' ) {
		if (!file_exists($filename)) return [];
		$imageinfo = getimagesize( $filename );
		return [
			'width'			=> $imageinfo[0],
			'height'		=> $imageinfo[1],
			'mime'			=> $imageinfo['mime'],
		];
	}

	/**
	 * EXIF Bild-Daten für Datei holen.
	 * ```
	 * \nn\t3::File()->getImageData( 'yellowstone.jpg' );
	 * ```
	 * @return array
	 */
	public function getImageData( $filename = '' ) {

		if (!function_exists('exif_read_data')) return [];

		$exif = @\exif_read_data($filename);
		if (!$exif) return [];
		
        $orientation = $exif['Orientation'];
        
        $imageProcessingMap = array(
        	'r2' => '-flop',
        	'r3' => '-flop -flip',
        	'r4' => '-rotate 180 -flop',
        	'r5' => '-flop -rotate 270',
        	'r6' => '-rotate 90',
        	'r7' => '-flop -rotate 90',
        	'r8' => '-rotate 270',
        );

        return [
        	'orient'		=> $orientation,
        	'time'			=> $exif['FileDateTime'],
        	'type'			=> $exif['FileType'],
        	'im' 			=> $imageProcessingMap['r'.$orientation] ?? false,
        ];
	}
				

		
	/**
	 * EXIF GEO-Daten für Datei holen.
	 * Adressdaten werden automatisch ermittelt, falls möglich
	 * ```
	 * \nn\t3::File()->getLocationData( 'yellowstone.jpg' );
	 * ```
	 * @return array
	 */
	public function getLocationData ( $filename = '' ) {

		if (!function_exists('exif_read_data')) return [];

		$rawExif = @\exif_read_data($filename);
		$exif = [];
		
		if ($rawExif) {
			
			$exif['lat'] = \nn\t3::Geo()->toGps($rawExif['GPSLatitude'], $rawExif['GPSLatitudeRef']);
			$exif['lng'] = \nn\t3::Geo()->toGps($rawExif['GPSLongitude'], $rawExif['GPSLongitudeRef']);
			
			$exif = \nn\t3::Arrays( $exif )->merge( \nn\t3::Geo()->getAddress( $exif['lng'], $exif['lat']) );
		}
		
		return $exif;
	}

	/**
	 * Berechnet ein Bild über `maxWidth`, `maxHeight` etc.
	 * Einfache Version von `\nn\t3::File()->processImage()`
	 * Kann verwendet werden, wenn es nur um das Generieren von verkleinerten Bilder geht
	 * ohne Berücksichtigung von Korrekturen der Kamera-Ausrichtung etc.
	 * 
	 * Da die Crop-Einstellungen in FileReference und nicht File gespeichert sind,
	 * funktioniert `cropVariant` nur bei Übergabe einer `FileReference`.
	 * ```
	 * \nn\t3::File()->process( 'fileadmin/imgs/portrait.jpg', ['maxWidth'=>200] );
	 * \nn\t3::File()->process( '1:/bilder/portrait.jpg', ['maxWidth'=>200] );
	 * \nn\t3::File()->process( $sysFile, ['maxWidth'=>200] );
	 * \nn\t3::File()->process( $sysFile, ['maxWidth'=>200, 'absolute'=>true] );
	 * \nn\t3::File()->process( $sysFileReference, ['maxWidth'=>200, 'cropVariant'=>'square'] );
	 * ```
	 * Mit dem Parameter `$returnProcessedImage = true` wird nicht der Dateipfad zum neuen Bild 
	 * sondern das processedImage-Object zurückgegeben.
	 * ```
	 * \nn\t3::File()->process( 'fileadmin/imgs/portrait.jpg', ['maxWidth'=>200], true );
	 * ```
	 * @return string
	 */
	public function process ( $fileObj = '', $processing = [], $returnProcessedImage = false ) {

		$filename = '';
		$cropString = '';
		$imageService = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Service\ImageService::class);

		if ($fileObj instanceof \TYPO3\CMS\Core\Resource\FileReference) {
			$fileObj = \nn\t3::Convert( $fileObj )->toFileReference();
		}
		
		if ($fileObj instanceof \TYPO3\CMS\Core\Resource\File) {

			// sys_file-Object
			$filename = $fileObj->getPublicUrl();

		} else if (is_a($fileObj, \TYPO3\CMS\Extbase\Domain\Model\FileReference::class)) {

			// sys_file_reference-Object
			if (method_exists($fileObj, 'getProperty')) {
				$cropString = $fileObj->getProperty('crop');
			} else if ($originalResource = $fileObj->getOriginalResource()) {
				$cropString = $originalResource->getProperty('crop');
			}
			$image = $fileObj->getOriginalResource();

		} else if (is_string($fileObj) && strpos($fileObj, ':/') !== false) {

			// String mit file_storage-Angabe (1:/uploads/test.jpg)
			$resourceFactory = GeneralUtility::makeInstance( ResourceFactory::class );	
			$file = $resourceFactory->getFileObjectFromCombinedIdentifier( $fileObj );
			$filename = $file->getPublicUrl();
		} else if (is_string($fileObj)) {

			// String (fileadmin/uploads/test.jpg)
			$filename = $fileObj;			
		}

		if ($filename) {
			$image = $imageService->getImage($filename, null, false);
		}

		if ($image) {

            $cropVariantCollection = CropVariantCollection::create((string)$cropString );
            $cropVariant = $processing['cropVariant'] ?: 'default';
			$cropArea = $cropVariantCollection->getCropArea($cropVariant);
			$processing['crop'] = $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image);

			$processedImage = $imageService->applyProcessingInstructions($image, $processing);
			if ($returnProcessedImage) return $processedImage;
			return $imageService->getImageUri($processedImage, $processing['absolute'] ?? false);
		}

		return false;
	}

	/**
	 * Kann direkt nach dem upload_copy_move() aufgerufen werden.
	 * Korrigiert die Ausrichtung des Bildes, die evtl. in EXIF-Daten gespeichert wurde.
	 * Für einfach `maxWidth`-Anweisungen die Methode `\nn\t3::File()->process()` verwenden.
	 * 
	 * Anweisungen für $processing:
	 *
	 * `correctOrientation` =>	Drehung korrigieren (z.B. weil Foto vom Smartphone hochgeladen wurde)
	 *
	 * @return string
	 */
	public function processImage ( $filenameOrSysFile = '', $processing = [] ) {

		if (is_string($filenameOrSysFile)) {
			if ($falFile = \nn\t3::Fal()->getFalFile( $filenameOrSysFile )) {
				$filenameOrSysFile = $falFile;
			}
		}

		// Bereits berechnete Bildgrößen löschen
		\nn\t3::Fal()->clearCache( $filenameOrSysFile );

		if (is_string($filenameOrSysFile)) {
			$filename = $filenameOrSysFile;
		} else if (is_a($filenameOrSysFile, \TYPO3\CMS\Core\Resource\File::class)) {
			$filename = $filenameOrSysFile->getPublicUrl();
		}
		
		if (!trim($filename)) return;
		$pathSite = \nn\t3::Environment()->getPathSite();

		$processing = \nn\t3::Arrays([
			'correctOrientation' 	=> true,
			'maxWidth'				=> 6000,
			'maxHeight'				=> 6000,
		])->merge($processing);

		$processingInstructions = [
			'file'	=> $filename,
			'file.'	=> [],
		];

		if ($maxWidth = $processing['maxWidth']) {
			$processingInstructions['file.']['maxW'] = $maxWidth;
		}
		if ($maxHeight = $processing['maxHeight']) {
			$processingInstructions['file.']['maxH'] = $maxHeight;
		}
		
		// EXIF-Daten vorhanden? Dann als JSON speichern, weil sie nach dem Processing verloren gehen würden.
		if (is_object($filenameOrSysFile)) {
			$uid = $filenameOrSysFile->getUid();
			$exif = \nn\t3::Db()->findByUid('sys_file', $uid)['exif'] ?? [];
		} else if ($exif = $this->getImageData($filename)) {
			$exif = $this->extractExifData( $filename );
		}

		// $exif['im'] enthält z.B. "-rotate 90" als ImageMagick Anweisung
		if ($exif['im'] && $processing['correctOrientation']) {
			$processingInstructions['file.']['params'] = $exif['im'];
		}

		$processedImageFilename = \nn\t3::Tsfe()->cObjGetSingle( 'IMG_RESOURCE', $processingInstructions );

		if ($processedImageFilename) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::upload_copy_move($pathSite . $processedImageFilename, $pathSite . $filename);
		}

		$exif = array_merge($this->getData( $filename ), ['file' => $filename]);

		// Update der Meta-Daten für das Bild
		if (is_object($filenameOrSysFile)) {
			\nn\t3::Fal()->updateMetaData( $filenameOrSysFile );
		}

		return $exif;
	}

	/**
	 * PHP Header für Download senden.
	 * Wenn die Datei physisch existiert, wird die `filesize` automatisch ermittelt.
	 * ```
	 * \nn\t3::File()->sendDownloadHeader( 'download.jpg' );
	 * \nn\t3::File()->sendDownloadHeader( 'pfad/zur/datei/download.jpg' );
	 * \nn\t3::File()->sendDownloadHeader( 'fakedatei.jpg', 1200 );
	 * ```
	 * @return void
	 */
	public function sendDownloadHeader( $filename = '', $filesize = null ) {
		ob_end_clean();
		if (!$filesize && $size = \nn\t3::File()->size($filename)) {
			$filesize = $size;
		}
		$filename = pathinfo( $filename, PATHINFO_BASENAME );
		$type = pathinfo( $filename, PATHINFO_EXTENSION );
		header("Content-Transfer-Encoding: Binary");
		header("Content-Type: application/{$type}");
		//header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		if ($filesize) header("Content-Length: ".$filesize);
	}

	/**
	 * Download einer einzelnen Datei oder eines gezippten Archives.
	 * 
	 * Download als ZIP erfordert die PHP-Extension `gmp`. Falls Extension nicht vorhanden ist,
	 * wird auf `.tar`-Variante ausgewichen. Bei Mac verwendet die Funktion aufgrund von 
	 * Sicherheitswarnungen des Finders grundsätzlich `tar`
	 * 
	 * ```
	 * \nn\t3::File()->download( 'fileadmin/test.pdf' );
	 * \nn\t3::File()->download( $fileReference );
	 * \nn\t3::File()->download( $sysFile );
	 * \nn\t3::File()->download( 'fileadmin/test.pdf', 'download.pdf' );
	 * ```
	 * 
	 * Wird ein Array übergeben, wird ein tar/zip-Download gestartet.
	 * Durch Übergabe eines assoziativen Arrays mit Dateiname als key und Pfad im Archiv als value
	 * Kann die Datei- und Ordnerstruktur im zip-Archiv bestimmt werden.
	 * 
	 * ```
	 * \nn\t3::File()->download( ['fileadmin/test-1.pdf', 'fileadmin/test-2.pdf'], 'archive.zip' );
	 * \nn\t3::File()->download( ['fileadmin/test-1.pdf'=>'eins.pdf', 'fileadmin/test-2.pdf'=>'zwei.pdf'], 'archive.zip' );
	 * \nn\t3::File()->download( ['fileadmin/test-1.pdf'=>'zip-folder-1/eins.pdf', 'fileadmin/test-2.pdf'=>'zip-folder-2/zwei.pdf'], 'archive.zip' );
	 * ```
	 * @param mixed $files			String oder Array der Dateien, die geladen werden sollen
	 * @param mixed $filename		Optional: Dateinamen überschreiben beim Download
	 * @return void
	 */
	public function download ( $files = null, $filename = null ) {

		\nn\t3::autoload();

		ob_end_clean();

		if (!is_array($files)) $files = [$files];

		// FE.compressionLevel in der LocalConfiguration angegeben? Dann hier deaktivieren!
		if ($GLOBALS['TYPO3_CONF_VARS']['FE']['compressionLevel']) {
			header('Content-Encoding: none');
			if (function_exists('apache_setenv')) {
				apache_setenv('no-gzip', '1');
			}
			if (extension_loaded('zlib')) {
				@ini_set('zlib.output_compression', 'off');
				@ini_set('zlib.output_compression_level', '0');
			}
		}

		// Nur eine Datei angegeben, dann einfacher Download
		if (count($files) == 1) {
			$k = key($files);
			if (!is_numeric($k)) {
				// ['pfad/zur/datei.pdf' => 'downloadname.pdf']
				$path = $this->absPath($k);
				$filenameForDownload = $files[$k];
			} else {
				// ['pfad/zur/datei.pdf']
				$path = $this->absPath($files[$k]);
				$filenameForDownload = pathinfo( $path, PATHINFO_BASENAME);
			}
			\nn\t3::File()->sendDownloadHeader( $filenameForDownload );
			readfile( $path );
			die();	
		}

		$archiveFilename = $filename ?: 'download-'.date('Y-m-d');		
		$stream = fopen('php://output', 'w');
		$opt = [];

		// gmp_init ist auf dem Server erforderlich für zip-Stream. Falls nicht vorhanden, auf .tar ausweichen
		if (function_exists('gmp_init')) {
			$zipStream = \Barracuda\ArchiveStream\Archive::instance_by_useragent( $archiveFilename, $opt, $stream );
		} else {
			$zipStream = new \Barracuda\ArchiveStream\TarArchive( $archiveFilename.'.tar', $opt, null, $stream );
		}

		$filesInArchive = [];

		foreach ($files as $k=>$file) {

			$filenameInArchive = basename($file);

			// ['fileadmin/test.pdf' => 'ordername_im_archiv/beispiel.pdf'] wurde übergeben
			if (!is_numeric($k)) {
				$filenameInArchive = $file;
				$file = $k;
			}

			$file = $this->absPath( $file );
			
			if ($filesize = $this->size( $file )) {

				// Gleicher Dateiname bereits im Archiv vorhanden? Dann "-cnt" anhängen
				if ($filesInArchive[$filenameInArchive]) {
					$cnt = $filesInArchive[$filenameInArchive]++;
					$filenameInArchive = pathinfo( $filenameInArchive, PATHINFO_FILENAME ) . '-' . $cnt . '.' . pathinfo( $filenameInArchive, PATHINFO_EXTENSION );
				} else {
					$filesInArchive[$filenameInArchive] = 1;
				}

				$zipStream->init_file_stream_transfer( $filenameInArchive, $filesize );
				$fileStream = fopen($file, 'r');
				while ($buffer = fread($fileStream, 256000)) {
					$zipStream->stream_file_part( $buffer );
				}
				fclose($fileStream);
				$zipStream->complete_file_stream();

			}
		}

		$zipStream->finish();
		die();
	}
}