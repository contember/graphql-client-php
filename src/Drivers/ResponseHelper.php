<?php declare(strict_types = 1);

namespace Contember\GraphQL\Drivers;

use Contember\GraphQL\ContemberFetchException;
use Contember\GraphQL\QueryResult;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Message\ResponseInterface;

class ResponseHelper
{
	public static function processResponse(ResponseInterface $response): QueryResult
	{
		if ($response->getStatusCode() !== 200) {
			throw new ContemberFetchException($response, 'Invalid response code: ' . $response->getStatusCode());
		}
		$data = $response->getBody()->getContents();

		$result = Json::decode($data);
		self::throwExceptionFromErrorInResponse($response, $result);

		return new QueryResult(new \DateTimeImmutable(), $result->data, $response->getHeaders(), QueryResult::STATUS_OK);
	}


	public static function processException(?ResponseInterface $response, \Throwable $e): void
	{
		$content = $response ? $response->getBody()->getContents() : null;
		try {
			$data = $content ? Json::decode($content) : null;
			self::throwExceptionFromErrorInResponse($response, $data, $e);
		} catch (JsonException $e) {
		}
		throw new ContemberFetchException($response, $content ?: $e->getMessage(), 0, $e);
	}


	public static function throwExceptionFromErrorInResponse(?ResponseInterface $response, ?\stdClass $data, ?\Throwable $previous = null): void
	{
		if (!$data || !isset($data->errors)) {
			return;
		}
		$message = implode("\n", array_map(function (\stdClass $error) {
			return $error->message;
		}, $data->errors));
		throw new ContemberFetchException($response, $message, 0, $previous);
	}
}
