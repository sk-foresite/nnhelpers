<?php

namespace Nng\Nnhelpers\ViewHelpers\Format;

use Nng\Nnhelpers\ViewHelpers\AbstractViewHelper;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Highlighted code-Abschnitte per PrismJS.
 * 
 * Der Code kann über `download` zum direkten Download verfügbar gemacht werden.
 * 
 * Die Datei wird dabei dynamisch per JS generiert und gestreamt – es enstehen keine zusätzlichen Dateien auf dem Server.
 * nnhelpers nutzt diese Funktion, um die Boilerplates als Download anzubieten.
 * 
 * Mehr Infos unter: https://prismjs.com/
 *
 * Build: https://bit.ly/3BLqmx0
 * 
 * ```
 * <nnt3:format.code lang="css" download="rte.css">
 *   ... Markup ...
 * </nnt3:format.code>
 * 
 * <nnt3:format.code lang="none">
 *   ... Markup ...
 * </nnt3:format.code>
 * ```
 * @return string  
 */
class CodeViewHelper extends AbstractViewHelper {

	protected $escapeOutput = false;

	public function initializeArguments() {
		$this->registerArgument('str', 'string', 'Code', false);
		$this->registerArgument('lang', 'string', 'Sprache', false);
		$this->registerArgument('download', 'string', 'Download Dateiname', false);
	}

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {

		$str = $arguments['str'] ?: $renderChildrenClosure();
		$lang = $arguments['lang'] ?: 'css';

		// Prism Plugin erlaubt simplen Download
		if ($download = $arguments['download']) {
			$download = 'data-src="' . $download . '" data-nndownload data-download-link-label="Download"';
		}

		$lang = $lang !== 'none' ? "language-{$lang}" : '';

		$str = trim(htmlspecialchars($str, ENT_QUOTES, 'UTF-8'));

        return '<pre ' . $download . '><code class="' . $lang . '">'.$str.'</code></pre>';
	}
}