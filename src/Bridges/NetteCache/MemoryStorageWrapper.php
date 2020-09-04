<?php declare(strict_types = 1);

namespace Contember\GraphQL\Bridges\NetteCache;

use Nette\Caching\IStorage;
use Nette\Caching\Storages\MemoryStorage;

class MemoryStorageWrapper implements IStorage
{
	/** @var IStorage */
	protected $mainStorage;

	/** @var MemoryStorage */
	protected $memoryStorage;


	private function __construct(IStorage $mainStorage)
	{
		$this->mainStorage = $mainStorage;
		$this->memoryStorage = new MemoryStorage();
	}


	public static function wrap(IStorage $storage): IStorage
	{
		return $storage instanceof MemoryStorage ? $storage : new self($storage);
	}


	public function read(string $key)
	{
		if (($value = $this->memoryStorage->read($key)) !== null) {
			return $value;
		}
		if (($value = $this->mainStorage->read($key)) !== null) {
			$this->memoryStorage->write($key, $value, []);

			return $value;
		}

		return null;
	}


	public function lock(string $key): void
	{
		$this->mainStorage->lock($key);
	}


	/**
	 * @param mixed $data
	 */
	public function write(string $key, $data, array $dependencies): void
	{
		$this->memoryStorage->write($key, $data, $dependencies);
		$this->mainStorage->write($key, $data, $dependencies);
	}


	public function remove(string $key): void
	{
		$this->memoryStorage->remove($key);
		$this->mainStorage->remove($key);
	}


	public function clean(array $conditions): void
	{
		$this->memoryStorage->clean($conditions);
		$this->mainStorage->clean($conditions);
	}

}
