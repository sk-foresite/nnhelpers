.. include:: ../Includes.txt

.. _wphelpers:

==================
Helpers für WordPress
==================

Wir haben eine Vision.
--------------

Was wäre, wenn es eine ähnliche Sammlung an "Helfern" für andere Content-Management-Systeme gäbe? Wenn die Methoden sogar
(fast) deckungsgleich wären? Wenn man als Entwickler zwischen Joomla, WordPress, Drupal, NodeJS und TYPO3 einfach 
"springen" könnte, ohne sich immer wieder in andere Konzepte und Core-APIs einzuarbeiten.

Das ist die Idee hinter ``nnhelpers`` – mit dem langfristigen Ziel, sogar einen Großteil des Codes zwischen unterschiedlichen
CMS wiederverwendbar zu machen. Klar: Das ist ein Traum und er ist mit Hürden verbunden. Aber allein zu wissen: Es gibt
``\nn\t3::debug()`` oder ``\nn\t3::Db()->findAll()`` oder ``\nn\t3::Environment()->getBaseUrl()`` – und dieser Befehl ist
Framework-übergreifend gleich wäre schon eine große Hilfe. Egal, ob man ``nnhelpers`` dann wirklich nutzen will – oder einfach
nur als "Spickzettel" verwendet, um zu sehen, wie die Funktion im Detail innerhalb des jeweiligen Systems implementiert wird.

Wir haben in 2022 einen Startpunkt gesetzt und angefangen, ``wphelpers`` ins Leben zu rufen: Eine Spiegelung von ``nnhelpers``
für WordPress! 

`nng/wphelpers auf packagist <https://packagist.org/packages/nng/wphelpers>`__ 
| `WpHelpers im GIT auf bitbucket.org <https://bitbucket.org/99grad/wphelpers>`__

TYPO3Fluid als Rendering Engine in WordPress nutzen
--------------

Was war einer unserer ersten Schritte und Methoden der ``wphelpers``? Eine vernünftige Template-Engine an den Start bringen.
WordPress setzt ja bekanntlich auf PHP-Templating – aus Sicht eines Fluid- oder Twig-verwöhnten Entwicklers eher eine 
anachronistische Vollkatastrophe.

Mit ``wphelpers`` kann man jetzt innerhalb seines WordPress-Plugins jetzt diese schöne Zeile nutzen:

.. code-block:: php

    \nn\wp::Template()->render('EXT:my_wp_plugin/path/to/template.php', ['demo'=>123]);

... und damit ein Fluid-Template rendern! Dank der Community, die `Fluid als Standalone Version <https://github.com/TYPO3/Fluid>`__ 
bereit gestellt hat. Und da Fluid immer noch eine der besten Template-Engines ist – warum nicht WordPress damit "upgraden".
Damit sind schon mal alle Templates der TYPO3-Extensions in WordPress wiederverwendbar.

Und die Performance? WordPress argumentiert immer, dass nichts performanter als ein PHP-Template ist. Aber wer Fluid kennt, 
der weiß, dass alle Templates (wie auch bei Smarty etc.) in reinen PHP-Code "übersetzt" und gecached werden. Es wird also
faktisch kaum einen Unterschied in der Performance geben.


Let's do it!
--------------

Wenn es dort draußen noch andere Teams gibt, die sich in den Paralleluniversen zwischen TYPO3, WordPress etc. bewegen und
diese Idee interessant finden: Was haltet ihr davon? Lust, mit einzusteigen? Lust eine Methode auf ``nnhelpers`` in
ein anderes System zu übersetzen? Let's start the revolution ;)

Wir freuen uns auf Euer Feedback!