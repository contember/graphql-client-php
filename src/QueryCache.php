<?php declare(strict_types = 1);

namespace Contember\GraphQL;

interface QueryCache
{
	public function load(string $query, array $variables): ?QueryResult;

	public function save(string $query, array $variables, QueryResult $queryResult): void;
}
