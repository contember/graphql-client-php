<?php declare(strict_types = 1);

namespace Contember\GraphQL\Utils;

class VariableReplacer
{
	public static function replace(string $subject, array $parameters): string
	{
		$result = preg_replace_callback('~:(\{(?<name1>\w+)\}|(?<name2>[^\W]+))~', function (array $match) use ($parameters) {
			$varName = $match['name1'] ?: $match['name2'];
			return $parameters[$varName] ?? '';
		}, $subject);
		assert(is_string($result));
		return $result;
	}
}
