<?php

namespace Nng\Nnhelpers\Helpers;

/**
 * Erweiterung für das Formular des Extension-Managers.
 * 
 */
class ExtConfTemplateHelper {

    /**
     * Mehrzeiliges Textfeld / Textarea im Extension Manager Konfigurator zeigen.
	 * Diese Zeile in `ext_conf_template.txt` der eigenen Extension nutzen:
     * ```
	 * # cat=basic; type=user[Nng\Nnhelpers\Helpers\ExtConfTemplateHelper->textfield]; label=Mein Label
	 * meinFeldName = 
	 * ```
     * @return string
     */
	public function textfield( $conf = [] ) {
        return "<textarea style=\"min-height:100px\" name=\"{$conf['fieldName']}\" class=\"form-control\">{$conf['fieldValue']}</textarea>";
    }

}