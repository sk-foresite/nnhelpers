<?php

namespace Nng\Nnhelpers\Utilities;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;

use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
use Pelago\Emogrifier\HtmlProcessor\HtmlPruner;

/**
 * Helferlein für den Mailversand
 */
class Mail implements SingletonInterface {
   
	/**
	 * 	Eine E-Mail senden.
	 *	```
	 *	$html = \nn\t3::Template()->render('MailTemplate', ['varKey'=>'varValue'], 'tx_extname_plugin');
	 *
	 *	\nn\t3::Mail()->send([
	 *		'html'			=> $html,
	 *		'plaintext'		=> Optional: Text-Version
	 *		'fromEmail'		=> Absender-Email
	 *		'fromName'		=> Absender-Name
	 *		'toEmail'		=> Empfänger-Email
	 *		'subject'		=> Betreff
	 *		'attachments'	=> [...],
	 *		'emogrify'		=> CSS-Stile in Inline-Styles umwandeln (default: `true`)
	 *		'absPrefix'		=> Relative Pfade in absolute umwandeln (default: `true`)
	 *	]);
	 *	```
	 * 	Bilder einbetten mit 	`<img data-embed="1" src="..." />`
	 * 	Dateianhänge mit 		`<a data-embed="1" href="..." />`
	 * 	@return void
	 */
    public function send ( $paramOverrides = [] ) {

		\nn\t3::autoload();

		// Defaults mergen mit Parametern
		$params = [
			'emogrify'	=> true,
			'absPrefix'	=> true,
		];
		ArrayUtility::mergeRecursiveWithOverrule( $params, $paramOverrides);
		
		$mail = GeneralUtility::makeInstance(MailMessage::class);

		$html = $params['html'] ?? '';
		$pathSite = \nn\t3::Environment()->getPathSite();

		$recipients = \nn\t3::Arrays($params['toEmail'] ?? '')->trimExplode();
		$fromEmail = array_shift(\nn\t3::Arrays($params['fromEmail'] ?? '')->trimExplode());
		
		$mail->from( new \Symfony\Component\Mime\Address($fromEmail, $params['fromName'] ?? '') );
		$mail->to(...$recipients);		
		$mail->subject($params['subject'] ?? '');

		// Inline-Media im HTML-Code finden (<img data-embed="1" src="..." />)
		$dom = new \DOMDocument();
		@$dom->loadHTML($html);

		// XPATH Cheat-Sheet: https://devhints.io/xpath
		$xpath = new \DOMXPath($dom);
		$nodes = $xpath->query('//*[@data-embed]');
		$attachedFiles = [];

		foreach ($nodes as $node) {
			if ($img = $node->getAttribute('src')) {
				$node->removeAttribute('data-embed');	
				if ($useSwiftMailer) {
					$cid = $mail->embed(\Swift_Image::fromPath($pathSite . $img));
					$node->setAttribute('src', $cid);
				} else {
					$cid = 'img-' . md5($pathSite . $img);
					$node->setAttribute('src', 'cid:'.$cid);
					$mail->embedFromPath( $pathSite . $img, $cid );
				}
			}
			if ($file = $node->getAttribute('href')) {
				if (!isset($attachedFiles[$pathSite . $file])) {
					$absPath = \nn\t3::File()->absPath( $file );
					if ($useSwiftMailer) {
						$attachment = \Swift_Attachment::fromPath($absPath);
						$mail->attach($attachment);
						$node->parentNode->removeChild($node);
					} else {
						$mail->attachFromPath( $absPath );
					}
					$attachedFiles[$absPath] = true;
				}
			}
		}
	
		// Zusätzlich Anhänge?
		$attachments = \nn\t3::Arrays($params['attachments'] ?? '')->trimExplode();
		foreach ($attachments as $path) {
			if (!isset($attachedFiles[$pathSite . $path])) {
				$absPath = \nn\t3::File()->absPath( $path );
				if ($useSwiftMailer) {
					$attachment = \Swift_Attachment::fromPath( $absPath );
					$mail->attach($attachment);
				} else {
					$mail->attachFromPath( $absPath );
				}
				$attachedFiles[ $absPath ] = true;
			}
		}

		$html = $dom->saveHTML();

		// Relative Pfade im Quelltext mit absoluten Pfaden ersetzen
		if ($params['absPrefix'] ?? false) {
			$html = \nn\t3::Dom()->absPrefix( $html );
		}

		// CSS in Inline-Styles umwandeln
		if ($params['emogrify'] ?? false) {
			$html = CssInliner::fromHtml($html)->inlineCss()->render();
		}

		$plaintext = $params['plaintext'] ?? strip_tags($html);

		if ($html) {
			if ($useSwiftMailer) {
				$mail->setBody($html, 'text/html');
				$mail->addPart($plaintext, 'text/plain');
			} else {
				$mail->html($html);
				$mail->text($plaintext);
			}
		} else {
			if ($useSwiftMailer) {
				$mail->setBody($plaintext, 'text/plain');
			} else {
				$mail->text($plaintext);
			}
		}
	
		if ($useSwiftMailer) {
			$mail->setReturnPath( $params['returnPath_email'] ?? '' );
		} else {
			$returnPath = $params['returnPath_email'] ?? \TYPO3\CMS\Core\Utility\MailUtility::getSystemFromAddress();
			if (strpos($returnPath, 'no-reply') === false) {
				$mail->setReturnPath( $returnPath );
			}
		}

		$sent = $mail->send();

		if (!$sent) {
/*			
			$fp = fopen('mail_error.log', 'a');
			$to = join(',', $recipients);
			fputs($fp, date('d.m.Y H:i:s')." {$to}\n\n\n");
			fclose($fp);
			$helpMail = $this->params['errorMail_email'];
			$mail->setReturnPath( $helpMail );
			$mail->setTo( $helpMail );
			$mail->setSubject('Mailversand: FEHLER!');
			$mail->send();
//*/
		}

		return $sent;
	}

}