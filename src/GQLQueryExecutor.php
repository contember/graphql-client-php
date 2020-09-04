<?php declare(strict_types = 1);

namespace Contember\GraphQL;

use Contember\GraphQL\Drivers\GQLQueryDriver;
use Contember\GraphQL\Loaders\GQLQueryLoader;
use GuzzleHttp\Promise\PromiseInterface;


class GQLQueryExecutor
{
	/** @var callable[] */
	public $onQuery = [];

	/** @var GQLQueryLoader */
	private $queryLoader;

	/** @var GQLQueryDriver */
	private $driver;


	public function __construct(GQLQueryDriver $driver, GQLQueryLoader $queryLoader)
	{
		$this->queryLoader = $queryLoader;
		$this->driver = $driver;
	}


	public function queryFileAsync(string $queryFile, array $variables = []): PromiseInterface
	{
		$query = $this->queryLoader->load($queryFile);
		return $this->queryAsync($query, $variables);
	}


	public function queryFile(string $queryFile, array $variables): \stdClass
	{
		return $this->queryFileAsync($queryFile, $variables)->wait();
	}


	public function queryAsync(string $query, array $variables = []): PromiseInterface
	{
		return $this->driver->execute($query, $variables, [])
			->then(function (QueryResult $queryResult) use ($query, $variables) {
				$result = $queryResult->getData();
				foreach ($this->onQuery as $callback) {
					$callback($this, (object) [
						'query' => $query,
						'variables' => $variables,
						'result' => $result,
					]);
				}
				return $result;
			});
	}


	public function query(string $query, array $variables = []): \stdClass
	{
		return $this->queryAsync($query, $variables)->wait();
	}
}
