<?php declare(strict_types = 1);

namespace Contember\GraphQL\Loaders;

class GQLLoadingContext
{
	/** @var string[] */
	private $fragments = [];

	/** @var string[] */
	private $files = [];

	/** @var string */
	private $source = '';


	public function appendSource(string $source): void
	{
		$this->source .= "\n" . $source;
	}


	public function getSource(): string
	{
		return $this->source;
	}


	public function addFragment(string $name): void
	{
		$this->fragments[] = $name;
	}


	public function addFile(string $file): void
	{
		$this->files[] = $file;

	}


	public function hasFragment(string $name): bool
	{
		return in_array($name, $this->fragments, true);
	}


	public function hasFile(string $name): bool
	{
		return in_array($name, $this->files, true);
	}
}
