<?php declare(strict_types = 1);

namespace Contember\GraphQL;

use Psr\Http\Message\ResponseInterface;
use Throwable;

class ContemberFetchException extends \RuntimeException
{
	/**
	 * @var null|ResponseInterface
	 */
	private $response;


	public function __construct(?ResponseInterface $response, $message = "", $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->response = $response;
	}


	public function getResponse(): ?ResponseInterface
	{
		return $this->response;
	}
}
