<?php namespace Nng\Nnhelpers\Helpers;

/**
 * Ein Wrapper zum Parsen von markdown und Übersetzung in HTML und umgekehrt.
 * 
 */
class MarkdownHelper {
	
	/**
	 * Kommentar-String zu lesbarem HTML-String konvertieren
	 * Kommentare können Markdown verwenden.
	 * Entfernt '*' und '/*' etc.
	 * ```
	 * \Nng\Nnhelpers\Helpers\MarkdownHelper::parseComment( '...' );
	 * ```
	 * @return string
	 */
	public static function parseComment ( $comment = '', $encode = true ) {

		$comment = self::removeAsterisks( $comment );
		if (!$encode) return $comment;

		$comment = htmlspecialchars( $comment );

		return self::toHTML( $comment );
	}


	/**
	 * Einen Text, der markdown enthält in HTML umwandeln.
	 * ```
	 * \Nng\Nnhelpers\Helpers\MarkdownHelper::toHTML( '...' );
	 * ```
	 * @return string 
	 */
	public static function toHTML( $str = '' ) {

		if (!class_exists(\Parsedown::class)) {
			\nn\t3::autoload();
		}

		$parsedown = new \Parsedown();
		$result = $parsedown->text( $str );
		
		$result = str_replace(['&amp;amp;', '&amp;gt;', '&amp;#039;', '&amp;quot;', '&amp;apos;', '&amp;lt;'], ['&amp;', '&gt;', '&apos;', '&quot;', "&apos;", '&lt;'], $result);
		$result = trim($result);

		if (!$result) return '';

		$dom = new \DOMDocument();
		$dom->loadXML( '<t>' . $result . '</t>', LIBXML_NOENT | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING );
		
		if (!$dom) return $result;

		if ($pre = $dom->getElementsByTagName('pre'));
		if (!$pre) return $result;

		foreach ($pre as $el) {
			if ($code = $el->getElementsByTagName('code')) {
				foreach ($code as $codeEl) {
					$codeEl->setAttribute('class', 'language-php');
				}
			}			
		}

		$html = $dom->saveHTML( $dom->getElementsByTagName('t')->nodeValue ?? null );
				
		return trim(str_replace(['<t>', '</t>'], '', $html));
	}


	/** 
	 * Entfernt die Kommentar-Sternchen in einem Text.
	 * ```
	 * \Nng\Nnhelpers\Helpers\MarkdownHelper::removeAsterisks( '...' );
	 * ```
	 * @return string 
	 */
	public static function removeAsterisks( $comment = '' ) {

		// Öffnenden und schließenden Kommentar löschen
		$comment = trim(str_replace(['/**', '/*', '*/'], '', $comment));

		// in Zeilen-Array konvertieren
		$lines = \nn\t3::Arrays($comment)->trimExplode("\n");
		$isCode = false;

		foreach ($lines as $k=>$line) {

			// \nn\t3...; immer als Code formatieren
			//$line = preg_replace("/((.*)(t3:)(.*)(;))/", '`\1`', $line);
			$line = preg_replace("/((.*)(@param)([^\$]*)([\$a-zA-Z]*))(.*)/", '`\1`\6', $line);
			$line = preg_replace("/((.*)(@return)(.*))/", '`\1`', $line);

			// Leerzeichen nach '* ' entfernen
			$line = preg_replace("/(\*)(\s)(.*)/", '\3', $line);
			$line = preg_replace("/`([\s]*)/", '`', $line, 1);
			$line = str_replace('*', '', $line);

			if (!$isCode) {
				$line = trim($line);
			}

			if (strpos($line, '```') !== false) $isCode = !$isCode;

			$lines[$k] = $line;
		}

		$comment = trim(join("\n", $lines));

		return $comment;
	}


}