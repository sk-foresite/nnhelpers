<?php
namespace Nng\Nnhelpers\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Einen Text übersetzen, inkl. optionaler Übersetzung über Deep-L.
 * 
 * Siehe auch Doku zu `TranslationHelper` für die Einbindung über PHP oder einen Controller. 
 * ```
 * // Übersetzung über locallang.xlf
 * {mytext->nnt3:translate(id:'LLL:EXT:nnaddress/Resources/Private/Language/locallang_db.xlf:my-ll-id')}
 * {mytext->nnt3:translate(id:'my-ll-id', extensionName:'nnaddress')}
 * ```
 * ```
 * // Übersetzung per Deep-L
 * {nnt3:translate(id:'my-ll-id', text:'Der Text', extensionName:'nnaddress', enableApi:1, translate:1, targetLang:'EN', maxTranslations:2)}
 * {mytext->nnt3:translate(id:'my-ll-id', extensionName:'nnaddress', enableApi:1, translate:1, targetLang:'EN', maxTranslations:2)}
 * {mytext->nnt3:translate(id:'my-ll-id', enableApi:1, translate:1, targetLang:'EN', cacheFolder:'EXT:nnsite/path/to/somewhere/')}
 * {mytext->nnt3:translate(id:'my-ll-id', enableApi:1, translate:1, targetLang:'EN', cacheFolder:'typo3conf/l10n/demo/')}
 * ```
 * ```
 * // Einen Block im Fluid-Template übersetzen
 * <nnt3:translate id="text-id-or-cObj-uid" enableApi="1" translate="1" targetLang="EN">
 *   <p>Ich werde automatisch übersetzt, inkl. aller <b>HTML</b>-Tags!</p>
 * </nnt3:translate>
 * ```
 */
class TranslateViewHelper extends AbstractViewHelper {

	use CompileWithRenderStatic;

	protected $escapeOutput = false;

	public function initializeArguments() {
		$this->registerArgument('id', 'string', 'Key für die Übersetzung', false);
		$this->registerArgument('text', 'string', 'Text, der übersetzt werden soll', false);
		$this->registerArgument('extensionName', 'string', 'Extension, die Übersetzung enthält', false);
		$this->registerArgument('enableApi', 'boolean', 'Deep-L-Api nutzen', false, false);
		$this->registerArgument('translate', 'boolean', 'Automatische Deep-L-Übersetzung aktivieren', false, false);
		$this->registerArgument('targetLang', 'string', 'Zielsprache für Deep-L Übersetzung', false, 'EN');
		$this->registerArgument('srcLang', 'string', 'Ausgangssprache für Deep-L Übersetzung', false, 'DE');
		$this->registerArgument('cacheFolder', 'string', 'Cache-Ordner, in dem Übersetzungen gespeichert werden', false);
		$this->registerArgument('maxTranslations', 'intval', 'Maximale Zahl an Übersetzungen (zwecks Debugging)', false, 0);
	}

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {
		$args = ['id', 'srcLang', 'text', 'extensionName', 'enableApi', 'targetLang', 'cacheFolder', 'maxTranslations', 'translate'];
		
		foreach ($args as $arg) {
			${$arg} = $arguments[$arg];
		}

		if (!$translate) {
			return \nn\t3::LL()->get( $id, $extensionName );
		}

		if (!$text) {
			$text = $renderChildrenClosure();
		}

		$cachedNumTranslations = $GLOBALS['_nnhelpers_numTranslations'] ?? 0;

		$translationHelper = \nn\t3::injectClass( \Nng\Nnhelpers\Helpers\TranslationHelper::class );

		if (!$id) {
			\nn\t3::Exception('ID [' . $id . '] für Übersetzung nicht gültig!');
		}

		if (strtolower($srcLang) == strtolower($targetLang)) {
			return $text;
		}

		if ($cacheFolder) {

			// Angegebenen Cache-Folder verwenden
		
		} else if ($extensionName) {

			// Cache-Folder aus Extension nehmen
			if (!\nn\t3::Environment()->extLoaded($extensionName)) {
				\nn\t3::Exception('Extension ' . $extensionName . ' nicht geladen!');
			}
			$cacheFolder = 'EXT:' . $extensionName . '/Resources/Private/Language/';

		} else {
			// TranslationHelper verwendet default (typo3conf/l10n/nnhelpers/)
		}

		if ($cacheFolder) {
			$translationHelper->setL18nFolderpath( $cacheFolder );
		}

		$translationHelper->setTargetLanguage( $targetLang );

		$translationsLeft = $maxTranslations > 0 ? $maxTranslations - $cachedNumTranslations : 1;
		$allowTranslation = $enableApi && $translationsLeft > 0;

		$translationHelper->setEnableApi( $allowTranslation );

		$cachedNumTranslations++;
		$GLOBALS['_nnhelpers_numTranslations'] = $cachedNumTranslations;

		if ($maxTranslations) {
			$translationHelper->setMaxTranslations( $maxTranslations );
		}

		$text = $translationHelper->translate( $id, $text );

		return $text;
	}
    
}
