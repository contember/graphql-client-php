<?php declare(strict_types = 1);

namespace Contember\GraphQL\Drivers;

use Contember\GraphQL\QueryCache;
use Contember\GraphQL\QueryResult;
use GuzzleHttp\Promise\PromiseInterface;

class CacheSaveQueryDriver implements GQLQueryDriver
{
	/** @var GQLQueryDriver */
	private $innerDriver;

	/** @var QueryCache */
	private $queryCache;


	public function __construct(GQLQueryDriver $innerDriver, QueryCache $queryCache)
	{
		$this->innerDriver = $innerDriver;
		$this->queryCache = $queryCache;
	}


	public function execute(string $query, array $variables, array $headers = []): PromiseInterface
	{
		return $this->innerDriver->execute($query, $variables, $headers)
			->then(function (QueryResult $result) use ($query, $variables) {
				if ($result->getStatus() === QueryResult::STATUS_OK) {
					$this->queryCache->save($query, $variables, $result);
				}
				return $result;
			});
	}
}
