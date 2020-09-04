<?php declare(strict_types = 1);

namespace Contember\GraphQL\Loaders;


class ApcuCachedGQLQueryLoader implements GQLQueryLoader
{
	/** @var GQLQueryLoader */
	private $innerLoader;


	public function __construct(GQLQueryLoader $innerLoader)
	{
		$this->innerLoader = $innerLoader;
	}


	public function load(string $filename): string
	{
		$key = $this->formatKey($filename);
		return (string) apcu_entry($key, function () use ($filename) {
			return $this->innerLoader->load($filename);
		});
	}


	private function formatKey(string $filename): string
	{
		return 'gql_' . md5($filename);
	}
}
