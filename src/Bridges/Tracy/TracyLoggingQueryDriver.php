<?php declare(strict_types = 1);

namespace Contember\GraphQL\Bridges\Tracy;

use Contember\GraphQL\Drivers\GQLQueryDriver;
use GuzzleHttp\Promise\PromiseInterface;
use Tracy\Debugger;
use Tracy\Dumper;

class TracyLoggingQueryDriver implements GQLQueryDriver
{
	/** @var GQLQueryDriver */
	private $innerDriver;


	public function __construct(GQLQueryDriver $innerDriver)
	{
		$this->innerDriver = $innerDriver;
	}


	public function execute(string $query, array $variables, array $headers = []): PromiseInterface
	{
		$start = microtime(true);
		return $this->innerDriver->execute($query, $variables, $headers)
			->then(function (QueryResult $result) use ($query, $variables, $start) {
				$duration = microtime(true) - $start;
				Debugger::barDump([
					'query' => $query,
					'variables' => $variables,
					'result' => $result->getData(),
					'status' => $result->getStatus(),
					'duration (ms)' => round($duration * 1000),
				], null, [Dumper::DEPTH => 9, Dumper::TRUNCATE => 10000]);
				return $result;
			});
	}
}
