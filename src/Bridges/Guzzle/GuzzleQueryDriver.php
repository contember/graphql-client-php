<?php declare(strict_types = 1);

namespace Contember\GraphQL\Bridges\Guzzle;

use Contember\GraphQL\Drivers\GQLQueryDriver;
use Contember\GraphQL\Drivers\ResponseHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleQueryDriver implements GQLQueryDriver
{
	/** @var Client */
	private $client;


	public function __construct(Client $client)
	{
		$this->client = $client;
	}


	public function execute(string $query, array $variables, array $headers = []): PromiseInterface
	{
		$responsePromise = $this->client->postAsync('', [
			'body' => json_encode([
				'query' => $query,
				'variables' => $variables,
			]),
			'headers' => $headers,
		]);
		return $responsePromise->then(function (ResponseInterface $response) {
			return ResponseHelper::processResponse($response);
		}, function (\Throwable $e) {
			if (!$e instanceof RequestException) {
				throw $e;
			}
			$response = $e->getResponse();
			ResponseHelper::processException($response, $e);
		});
	}

}
