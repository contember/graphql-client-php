<?php declare(strict_types = 1);

namespace Contember\GraphQL\Bridges\NetteCache;

use Contember\GraphQL\QueryCache;
use Contember\GraphQL\QueryResult;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;

class NetteQueryCache implements QueryCache
{
	/** @var Cache */
	private $cache;


	public function __construct(IStorage $cacheStorage)
	{
		$this->cache = new Cache(MemoryStorageWrapper::wrap($cacheStorage), 'Contember.QueryResultCache');
	}


	public function load(string $query, array $variables): ?QueryResult
	{
		$key = self::createCacheKey($query, $variables);
		$cacheEntry = $this->cache->load($key);
		if (!$cacheEntry) {
			return null;
		}
		['headers' => $headers, 'data' => $data, 'createdAt' => $createdAt] = $cacheEntry;
		return new QueryResult(new \DateTimeImmutable($createdAt), $data, $headers, QueryResult::STATUS_CACHED);
	}


	public function save(string $query, array $variables, QueryResult $queryResult): void
	{
		$key = self::createCacheKey($query, $variables);
		$this->cache->save($key, [
			'headers' => $queryResult->getHeaders(),
			'data' => $queryResult->getData(),
			'createdAt' => $queryResult->getCreatedAt()->format('c'),
		], $this->getDependencies($query, $variables, $queryResult));
	}


	protected function getDependencies(string $query, array $variables, QueryResult $queryResult): array
	{
		return [];
	}


	public static function createCacheKey(string $query, array $variables): string
	{
		return md5(serialize([$query, $variables]));
	}
}
