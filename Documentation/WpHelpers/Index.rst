.. include:: ../Includes.txt

.. _wphelpers:

==================
Helpers for WordPress
==================

We have a vision.
--------------

What if there was a similar collection of "helpers" for other content management systems? If the methods were even
(almost) congruent? If a developer could simply "jump" between Joomla, WordPress, Drupal, NodeJS and TYPO3 
without having to learn other concepts and core APIs over and over again.

This is the idea behind ``nnhelpers`` - with the long term goal to make even a large part of the code reusable between different
CMS. Sure: This is a dream and it comes with hurdles. But just knowing: There are
``\nn\t3::debug()`` or ``\nn\t3::Db()->findAll()`` or ``\nn\t3::Environment()->getBaseUrl()`` - and this command is
framework-spanning the same would already be a big help. No matter if you really want to implement ``nnhelpers`` then - or simply
just use it as a ``cheat sheet`` to see how the function is implemented in detail within the respective system.

We set a starting point in 2022 and started to bring ``wphelpers`` to life: A mirror of ``nnhelpers``
for WordPress! 

`nng/wphelpers on packagist <https://packagist.org/packages/nng/wphelpers>`__ 
| `WpHelpers in GIT on bitbucket.org <https://bitbucket.org/99grad/wphelpers>`__

Using TYPO3Fluid as rendering engine in WordPress
--------------

What was one of our first steps and methods of ``wphelpers``? Getting a decent template engine up and running.
WordPress relies on PHP-templating - from the point of view of a Fluid- or Twig-accustomed developer this feels like a 
anachronistic disaster.

With ``wphelpers`` you can now use this nice line within your WordPress plugin:

.. code-block:: php

    \nn\wp::Template()->render('EXT:my_wp_plugin/path/to/template.php', ['demo'=>123]);

... and use it to render a fluid template! Thanks to the community who made `Fluid available as a standalone version <https://github.com/TYPO3/Fluid>`__. 
And since Fluid is still one of the best template engines out there - why not "upgrade" WordPress with it?
Now all templates of the TYPO3 extensions are reusable in WordPress!

And the performance? WordPress always argues that nothing is more performant than a PHP template. But if you know Fluid in depth, 
you knows that all templates are "translated" into pure PHP code and cached (same with Smarty etc.). So there will be
hardly any difference in performance.


Let's do it!
--------------

If there are other teams out there, who move in the parallel universes between TYPO3, WordPress etc. and
find this idea interesting: What do you think about it? Feel like getting in on the action? Do you want to translate a method from ``nnhelpers`` into another system?
into another system? Let's start the revolution ;)

We are looking forward to your feedback!