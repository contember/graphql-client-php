<?php declare(strict_types = 1);

namespace Contember\GraphQL\Drivers;

use GuzzleHttp\Promise\PromiseInterface;

interface GQLQueryDriver
{
	public function execute(string $query, array $variables, array $headers = []): PromiseInterface;
}
