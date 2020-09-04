<?php declare(strict_types = 1);

namespace Contember\GraphQL\Drivers;

use Contember\GraphQL\ContemberFetchException;
use Contember\GraphQL\QueryCache;
use Contember\GraphQL\QueryResult;
use GuzzleHttp\Promise\PromiseInterface;

class ContemberCacheQueryDriver implements GQLQueryDriver
{
	const CONTEMBER_REF_HEADER = 'x-contember-ref';

	/** @var QueryCache */
	private $queryCache;

	/** @var GQLQueryDriver */
	private $innerDriver;


	public function __construct(GQLQueryDriver $innerDriver, QueryCache $queryCache)
	{
		$this->queryCache = $queryCache;
		$this->innerDriver = $innerDriver;
	}


	public function execute(string $query, array $variables, array $headers = []): PromiseInterface
	{
		$result = $this->queryCache->load($query, $variables);
		$headers[self::CONTEMBER_REF_HEADER] = $result ? ($result->getHeaders()[self::CONTEMBER_REF_HEADER] ?? 'none') : 'none';
		$innerResponse = $this->innerDriver->execute($query, $variables, $headers);
		if (!$result) {
			return $innerResponse;
		}
		return $innerResponse->otherwise(function (\Throwable $e) use ($result) {
			if (!$e instanceof ContemberFetchException) {
				throw $e;
			}
			$response = $e->getResponse();
			if (!$response || $response->getStatusCode() !== 304) {
				throw $e;
			}
			return $result->withStatus(QueryResult::STATUS_NOT_MODIFIED);
		});
	}
}
