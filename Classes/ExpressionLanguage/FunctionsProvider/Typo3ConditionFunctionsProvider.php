<?php

namespace Nng\Nnhelpers\ExpressionLanguage\FunctionsProvider;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Class TypoScriptConditionProvider
 * @internal
 */
class Typo3ConditionFunctionsProvider implements ExpressionFunctionProviderInterface
{
	/**
	 * @return ExpressionFunction[]
	 */
	public function getFunctions(): array
	{
		return [
			$this->getNnt3_pidInRootlineFunction()
		];
	}

	/**
	 * Prüft, ob eine der angegebenen Seiten innerhalb der Rootline sind.
	 * ```
	 * [nnt3_pidInRootline([12,13])]
	 * 	...
	 * [GLOBAL]
	 * 
	 * [nnt3_pidInRootline(12)]
	 * 	...
	 * [GLOBAL]
	 * ```
	 * return ExpressionFunction
	 */
	protected function getNnt3_pidInRootlineFunction(): ExpressionFunction {
		return new ExpressionFunction('nnt3_pidInRootline', function () {
			// Not implemented, we only use the evaluator
		}, function ($existingVariables, $pidList = []) {
			if (!is_array($pidList)) $pidList = [$pidList];
			$rootLine = \nn\t3::Page()->getRootline();
			$rootLineUids = array_column( $rootLine, 'uid' );
			return count(array_intersect( $pidList, $rootLineUids )) > 0;
		});
	}

}
