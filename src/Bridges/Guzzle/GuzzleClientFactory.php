<?php declare(strict_types = 1);

namespace Contember\GraphQL\Bridges\Guzzle;

use GuzzleHttp\Client;

class GuzzleClientFactory
{
	public function create(string $apiUrl, string $token): Client
	{
		return new Client([
			'base_uri' => $apiUrl,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'bearer ' . $token,
			],
		]);
	}

}
