<?php declare(strict_types = 1);

namespace Contember\GraphQL;

class QueryResult
{
	public const STATUS_OK = 'ok';
	public const STATUS_STALE = 'stale';
	public const STATUS_NOT_MODIFIED = 'not_modified';
	public const STATUS_CACHED = 'cached';

	/** @var \stdClass */
	private $data;

	/** @var array */
	private $headers;

	/** @var \DateTimeImmutable */
	private $createdAt;

	/** @var string */
	private $status;


	public function __construct(\DateTimeImmutable $createdAt, \stdClass $data, array $headers, string $status)
	{
		$this->data = $data;
		$this->headers = array_change_key_case($headers, CASE_LOWER);
		$this->createdAt = $createdAt;
		$this->status = $status;
	}


	public function getData(): \stdClass
	{
		return $this->data;
	}


	public function getHeaders(): array
	{
		return $this->headers;
	}


	public function getStatus(): string
	{
		return $this->status;
	}


	public function getCreatedAt(): \DateTimeImmutable
	{
		return $this->createdAt;
	}


	public function withStatus(string $status): self
	{
		return new self($this->createdAt, $this->data, $this->headers, $status);
	}
}
