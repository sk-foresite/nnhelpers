<?php

namespace Nng\Nnhelpers\ViewHelpers\Format;

use Nng\Nnhelpers\ViewHelpers\AbstractViewHelper;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Konvertiert HTML-Tags zu Sphinx-Syntax für die TER Dokumentation.
 * ```
 * {annotation->f:format.raw()->nnt3:format.htmlToSphinx()}
 * ```
 * Aus folgendem Code ...
 * ```
 * <nnt3:format.htmlToSphinx>
 *   <p>Das ist eine Beschreibung dieser Methode</p>
 *   <pre><code>$a = 99;</code></pre>
 * </nnt3:format.htmlToSphinx>
 * ```
 * wird das hier gerendert:
 * ```
 * Das ist eine Beschreibung dieser Methode
 * 
 * .. code-block:: php
 *    
 *    $a = 99;
 * ```
 * @return string
 */
class HtmlToSphinxViewHelper extends AbstractViewHelper {

	protected $escapingInterceptorEnabled = false;

	public function initializeArguments() {
		$this->registerArgument('str', 'string', 'Code', false);
	}

	public static function renderStatic( array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext ) {

		$str = $arguments['str'] ?: $renderChildrenClosure();
		
		$dom = new \DOMDocument();
		$dom->loadHTML( '<t>' . $str . '</t>', LIBXML_NOENT | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING );

		if (!$dom) return '';

		$pre = $dom->getElementsByTagName('pre');

		foreach ($pre as $el) {
			$r = '';
			if ($code = $el->getElementsByTagName('code')) {
				foreach ($code as $codeEl) {
					$val = $codeEl->nodeValue;
					$rows = explode("\n", $val);
					$r .= "\n\n.. code-block:: php\n\n\t" . join("\n\t", $rows) . "\n\n";
				}
			}
			@$el->nodeValue = $r;
		}

		$code = $dom->getElementsByTagName('code');
		foreach ($code as $el) {
			$el->nodeValue = '``' . $el->nodeValue . '``';
		}

		$html = '';
		if (isset($dom->getElementsByTagName('t')->nodeValue)) {
			$html = $dom->saveHTML( $dom->getElementsByTagName('t')->nodeValue );
		}
		$html = str_replace(['</p>'], ["</p>\n"], $html);
		$html = html_entity_decode( strip_tags( $html ));
		$html = str_replace(['&#039;'], ["'"], $html);

		$html = str_replace("\n`", "\n| `", $html);
		return $html;
	}
}