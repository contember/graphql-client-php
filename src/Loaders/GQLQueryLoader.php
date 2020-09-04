<?php declare(strict_types = 1);

namespace Contember\GraphQL\Loaders;

interface GQLQueryLoader
{
	public function load(string $filename): string;
}
