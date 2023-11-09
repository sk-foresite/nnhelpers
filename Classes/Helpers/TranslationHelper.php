<?php
namespace Nng\Nnhelpers\Helpers;

/**
 * __Übersetzungsmanagement per Deep-L.__
 * 
 * Damit diese Funktion nutzbar ist, muss im Extension-Manager von `nnhelpers` ein Deep-L Api-Key hinterlegt werden.
 * Der Key ist ist kostenfrei und erlaubt die Übersetzung von 500.000 Zeichen pro Monat.
 * 
 * ```
 * // Übersetzer aktivieren
 * $translationHelper = \nn\t3::injectClass( \Nng\Nnhelpers\Helpers\TranslationHelper::class );
 * 
 * // Übersetzung per Deep-L erlauben
 * $translationHelper->setEnableApi( true );

 * // Zielsprache festlegen
 * $translationHelper->setTargetLanguage( 'EN' );
 * 
 * // Max. Anzahl der Übersetzungen erlauben (zwecks Debugging)
 * $translationHelper->setMaxTranslations( 2 );
 * 
 * // Pfad, in dem die l18n-Dateien gespeichert / gecached werden sollen
 * $translationHelper->setL18nFolderpath( 'EXT:nnhelpers/Resources/Private/Language/' );
 * 
 * // Übersetzung starten
 * $text = $translationHelper->translate('my.example.key', 'Das ist der Text, der übersetzt werden soll');
 * ```
 */
class TranslationHelper {
	
	/**
	 * Pfad zum Ordner mit den Übersetzungsdateien (JSON)
	 * @var string
	 */
	protected $l18nFolderpath = 'typo3conf/l10n/nnhelpers/';
	
	/**
	 * Zielsprache der Übersetzung
	 * @var string
	 */
	protected $targetLanguage = 'EN';

	/**
	 * Nicht übersetzte Texte automatisch per Deep-L übersetzen?
	 * @var boolean
	 */
	protected $enableApi = false;

	/**
	 * Cache der Übersetzungen
	 * @var array
	 */
	protected $l18nCache = [];
	
	/**
	 * Maximale Anzahl an Übersetzungen
	 * @var integer
	 */
	protected $maxTranslations = 0;

	/**
	 * Anzahl an Übersetzungen
	 * @var integer
	 */
	protected $numTranslations = 0;


	/**
	 * Gibt zurück, ob die API aktiviert ist.
	 * ```
	 * $translationHelper->getEnableApi(); // default: false
	 * ```
	 * @return  boolean
	 */
	public function getEnableApi() {
		return $this->enableApi;
	}

	/**
	 * Aktiviert / Deaktiviert die Übersetzung per Deep-L.
	 * ```
	 * $translationHelper->setEnableApi( true ); // default: false
	 * ```
	 * @param   boolean  $enableApi
	 * @return  self
	 */
	public function setEnableApi($enableApi) {
		$this->enableApi = $enableApi;
		return $this;
	}

	/**
	 * Gibt den aktuellen Ordner zurück, in dem die Übersetzungs-Dateien gecached werden.
	 * Default ist `typo3conf/l10n/nnhelpers/`
	 * ```
	 * $translationHelper->getL18nFolderpath();
	 * ```
	 * @return  string
	 */
	public function getL18nFolderpath() {
		return $this->l18nFolderpath;
	}

	/**
	 * Setzt den aktuellen Ordner, in dem die Übersetzungs-Dateien gecached werden.
	 * Idee ist es, die übersetzten Texte für Backend-Module nur 1x zu übersetzen und dann in dem Extension-Ordner zu speichern.
	 * Von dort werden sie dann ins GIT deployed.
	 * 
	 * Default ist `typo3conf/l10n/nnhelpers/`
	 * ```
	 * $translationHelper->setL18nFolderpath('EXT:myext/Resources/Private/Language/');
	 * ```
	 * @param   string  $l18nFolderpath  Pfad zum Ordner mit den Übersetzungsdateien (JSON)
	 * @return  self
	 */
	public function setL18nFolderpath($l18nFolderpath) {
		$this->l18nFolderpath = $l18nFolderpath;
		return $this;
	}

	/**
	 * Holt die Zielsprache für die Übersetzung
	 * ```
	 * $translationHelper->getTargetLanguage(); // Default: EN
	 * ```
	 * @return  string
	 */
	public function getTargetLanguage() {
		return $this->targetLanguage;
	}

	/**
	 * Setzt die Zielsprache für die Übersetzung
	 * ```
	 * $translationHelper->setTargetLanguage( 'FR' );
	 * ```
	 * @param   string  $targetLanguage  Zielsprache der Übersetzung
	 * @return  self
	 */
	public function setTargetLanguage($targetLanguage) {
		$this->targetLanguage = $targetLanguage;
		return $this;
	}

	/**
	 * Holt die maximale Anzahl an Übersetzungen, die pro Instanz gemacht werden sollen.
	 * ```
	 * $translationHelper->getMaxTranslations(); // default: 0 = unendlich
	 * ```	 
	 * @return integer
	 */
	public function getMaxTranslations() {
		return $this->maxTranslations;
	}

	/**
	 * Setzt die maximale Anzahl an Übersetzungen, die pro Instanz gemacht werden sollen.
	 * Hilft beim Debuggen (damit das Deep-L Kontingent nicht durch Testings ausgeschöpft wird) und bei TimeOuts, wenn viele Texte übersetzt werden müssen.
	 * ```
	 * $translationHelper->setMaxTranslations( 5 ); // Nach 5 Übersetzungen abbrechen
	 * ```	 
	 * @param   $maxTranslations
	 * @return  self
	 */
	public function setMaxTranslations($maxTranslations) {
		$this->maxTranslations = $maxTranslations;
		return $this;
	}

	/**
	 * Absoluten Pfad zur l18n-Cache-Datei zurückgeben.
	 * Default ist `typo3conf/l10n/nnhelpers/[LANG].autotranslated.json`
	 * ```
	 * $translationHelper->getL18nPath();
	 * ```
	 * @return string
	 */
	public function getL18nPath() {
		$path = rtrim($this->getL18nFolderpath(), '/').'/';
		$file = \nn\t3::File()->absPath( $path . strtolower($this->targetLanguage) . '.autotranslated.json' );
		return $file;
	}

	/**
	 * Komplette Sprach-Datei laden.
	 * ```
	 * $translationHelper->loadL18nData();
	 * ```
	 * @return array
	 */
	public function loadL18nData() {
		if ($cache = $this->l18nCache) return $cache;
		$path = $this->getL18nPath();
		$data = json_decode( \nn\t3::File()->read($path), true ) ?: [];
		return $this->l18nCache = $data;
	}

	/**
	 * Komplette Sprach-Datei speichern
	 * ```
	 * $translationHelper->saveL18nData( $data );
	 * ```
	 * @return boolean
	 */
	public function saveL18nData( $data = [] ) {
		$path = $this->getL18nPath();
		$success = \nn\t3::File()->write($path, json_encode($data));
		if (!$success) {
			\nn\t3::Exception('l18n-Datei konnte nicht geschrieben werden: ' . $path);
		}
		$this->l18nCache = $data;
		return $path;
	}

	/**
	 * Übersetzen eines Textes.
	 * ```
	 * $translationHelper = \nn\t3::injectClass( \Nng\Nnhelpers\Helpers\TranslationHelper::class );
	 * $translationHelper->setEnableApi( true );
	 * $translationHelper->setTargetLanguage( 'EN' );
	 * $text = $translationHelper->translate('my.example.key', 'Das ist der Text, der übersetzt werden soll'); 
	 * ```
	 * @return string
	 */
	public function translate( $key, $text = '' ) {

		$keyHash = $this->createKeyHash( $key );
		$textHash = $this->createTextHash( $text );
		$l18nData = $this->loadL18nData();
		
		$translation = $l18nData[$keyHash] ?? ['_cs'=>false];
		$textChanged = $translation['_cs'] != $textHash;
		
		$autoTranslateEnabled = $this->enableApi && ($this->maxTranslations == 0 || $this->maxTranslations > $this->numTranslations );

		// Text wurde übersetzt und hat sich nicht geändert
		if (!$textChanged) {
			$str = $translation['text'];
			$str = str_replace('.</p>.', '.</p>', $str);
			return $str;
		}

		// Text wurde nicht übersetzt und Deep-L Übersetzung ist deaktiviert
		if (!$autoTranslateEnabled) {
			if ($translation['_cs'] !== false) {
				return "[Translation needs {$this->targetLanguage} update] " . $text;
			}	
			return "[Translate to {$this->targetLanguage}] " . $text;
		}

		$this->numTranslations++;		
		echo "Translating via Deep-L: {$this->numTranslations} / {$this->maxTranslations} [$keyHash] " . json_encode($key) . "\n";

		$result = \nn\t3::LL()->translate( $text, $this->targetLanguage );

		$l18nData[$keyHash] = [
			'_cs' => $textHash,
			'text' => $result,
		];

		$this->saveL18nData( $l18nData );
		return $result;
	}

	/**
	 * Erzeugt einen eindeutigen Hash aus dem Key, der zur Identifizierung eines Textes benötigt wird.
	 * Jeder Text hat in allen Sprachen den gleichen Key.
	 * ```
	 * $translationHelper->createKeyHash( '12345' );
	 * $translationHelper->createKeyHash( ['mein', 'key', 'array'] );
	 * ```
	 * @return string
	 */
	public function createKeyHash( $param = '' ) {
		return md5(json_encode(['_'=>$param]));
	}
	
	/**
	 * Erzeugt einen eindeutigen Hash / Checksum aus dem Text.
	 * Der übergebene Text ist immer die Basis-Sprache. Ändert sich der Text in der Basissprache, gibt die Methode eine andere Checksum zurück.
	 * Dadurch wird erkannt, wann ein Text neu übersetzt werden muss. Reine Änderungen an Whitespaces und Tags werden ignoriert.
	 * ```
	 * $translationHelper->createKeyHash( '12345' );
	 * $translationHelper->createKeyHash( ['mein', 'key', 'array'] );
	 * ```
	 * @return string
	 */
	public function createTextHash( $text = '' ) {
		$text = strtolower(preg_replace('/\s+/', '', strip_tags( $text )));
		return md5($text);
	}


}