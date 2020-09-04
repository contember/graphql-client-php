<?php declare(strict_types = 1);

namespace Contember\GraphQL\Drivers;

use Contember\GraphQL\QueryCache;
use Contember\GraphQL\QueryResult;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Log\LoggerInterface;

class StaleCacheQueryDriver implements GQLQueryDriver
{
	/** @var QueryCache */
	private $queryCache;

	/** @var GQLQueryDriver */
	private $innerDriver;

	/** @var LoggerInterface */
	private $logger;


	public function __construct(GQLQueryDriver $innerDriver, QueryCache $queryCache, LoggerInterface $logger)
	{
		$this->queryCache = $queryCache;
		$this->innerDriver = $innerDriver;
		$this->logger = $logger;
	}


	public function execute(string $query, array $variables, array $headers = []): PromiseInterface
	{
		return $this->innerDriver->execute($query, $variables, $headers)
			->otherwise(function (\Throwable $e) use ($query, $variables) {
				$cached = $this->loadStaleCache($query, $variables);
				if (!$cached) {
					throw $e;
				}
				$this->logger->error(sprintf('ContemberClient: %s', $e->getMessage()), ['exception' => $e]);
				return $cached->withStatus(QueryResult::STATUS_STALE);
			});
	}

	protected function loadStaleCache(string $query, array $variables): ?QueryResult
	{
		return $this->queryCache->load($query, $variables);
	}
}
