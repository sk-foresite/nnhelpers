<?php

namespace Nng\Nnhelpers\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * ModifyResponse MiddleWare.
 * 
 * Request handling in MiddleWare / TYPO3 docs:
 * https://bit.ly/3GBcveH
 * 
 */
class ModifyResponse implements MiddlewareInterface 
{
	/**
	 *	@param ServerRequestInterface $request
	 *	@param RequestHandlerInterface $handler
	 *	@return ResponseInterface
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface 
	{
		$response = $handler->handle($request);
		\nn\t3::Cookies()->addCookiesToResponse( $request, $response );
		return $response;
	}
}