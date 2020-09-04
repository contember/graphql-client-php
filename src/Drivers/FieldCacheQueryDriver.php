<?php declare(strict_types = 1);

namespace Contember\GraphQL\Drivers;

use Contember\GraphQL\QueryCache;
use Contember\GraphQL\QueryResult;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;

class FieldCacheQueryDriver implements GQLQueryDriver
{
	/** @var GQLQueryDriver */
	private $innerDriver;

	/** @var array */
	private $parsedQueryCache = [];

	/** @var QueryCache */
	private $queryCache;


	public function __construct(GQLQueryDriver $innerDriver, QueryCache $queryCache)
	{
		$this->innerDriver = $innerDriver;
		$this->queryCache = $queryCache;
	}


	public function execute(string $query, array $variables, array $headers = []): PromiseInterface
	{
		$queryHash = md5($query);
		if (!isset($this->parsedQueryCache[$queryHash])) {
			$document = Parser::parse($query);
			$document = $this->inlineFragments($document);
			$document = $this->cleanupUnusedFragments($document);
			$this->parsedQueryCache[$queryHash] = $document;
		} else {
			$document = $this->parsedQueryCache[$queryHash];
		}

		$aliasMap = [];
		$isEmpty = true;
		$document = Visitor::visit($document, [
			'enter' => function ($node) use ($variables, &$aliasMap, &$isEmpty) {
				if ($node instanceof OperationDefinitionNode) {
					if ($node->operation !== 'query') {
						$isEmpty = false;
						return Visitor::stop();
					}
				}
				if ($node instanceof FieldNode) {
					$alias = $node->alias ? $node->alias->value : $node->name->value;
					$usedVariables = $this->collectVariables($node);

					$variableValues = array_filter($variables, function (string $key) use ($usedVariables) {
						return in_array($key, $usedVariables, true);
					}, ARRAY_FILTER_USE_KEY);

					$queryString = Printer::doPrint($node);
					$aliasMap[$alias] = [$queryString, $variableValues];
					if ($this->queryCache->load($queryString, $variableValues) !== null) {
						return Visitor::removeNode();
					}

					$isEmpty = false;

					return Visitor::skipNode();
				}
				if ($node instanceof FragmentDefinitionNode) {
					return Visitor::skipNode();
				}

				return null;
			}
		]);

		$document = $this->cleanupUnusedFragments($document);
		$document = $this->cleanupUnusedVariables($document);

		assert($document instanceof DocumentNode);
		if ($isEmpty) {
			$value = new QueryResult(new \DateTimeImmutable(), (object) ['data' => (object) []], [], QueryResult::STATUS_OK);
			$result = new FulfilledPromise($value);
		} else {
			$query1 = Printer::doPrint($document);

			$result = $this->innerDriver->execute($query1, $variables, $headers);
		}

		return $result->then(function (QueryResult $result) use ($aliasMap) {
			$data = $result->getData();
			// errored
			if (!isset($data->data)) {
				return $result;
			}
			foreach ($data->data as $alias => $value) {
				if (isset($aliasMap[$alias])) {
					[$queryStr, $variables] = $aliasMap[$alias];
					$queryResult = new QueryResult($result->getCreatedAt(), (object) ['value' => $value], $result->getHeaders(), QueryResult::STATUS_OK);
					$this->queryCache->save($queryStr, $variables, $queryResult);
				}
			}
			$status = $result->getStatus();
			$createdAt = $result->getCreatedAt();
			foreach ($aliasMap as $alias => [$queryStr, $variables]) {
				if (!property_exists($data->data, $alias)) {
					$queryEntry = $this->queryCache->load($queryStr, $variables);
					assert($queryEntry !== null);
					$data->data->{$alias} = $queryEntry->getData()->value;
					$status = $queryEntry->getStatus();
					$createdAt = min($createdAt, $queryEntry->getCreatedAt());
				}
			}
			return new QueryResult($createdAt, $data, $result->getHeaders(), $status);
		});
	}


	private function inlineFragments(DocumentNode $document): DocumentNode
	{
		return Visitor::visit($document, [
			'enter' => function ($node) use ($document) {
				if ($node instanceof FragmentDefinitionNode) {
					if ($node->typeCondition->name->value === 'Query') {
						return Visitor::removeNode();
					}
					return Visitor::skipNode();
				}
				if (!$node instanceof OperationDefinitionNode) {
					return null;
				}
				if ($node->operation !== 'query') {
					return Visitor::stop();
				}
				$inlineFragments = function (SelectionSetNode $node) use ($document, &$inlineFragments): array {
					$nodes = [];
					foreach ($node->selections as $selectionNode) {
						if ($selectionNode instanceof FieldNode) {
							$nodes[] = $selectionNode;
						} elseif ($selectionNode instanceof FragmentSpreadNode) {
							$fragment = $this->findFragment($document, $selectionNode->name->value);

							$nodes = array_merge($nodes, $inlineFragments($fragment->selectionSet));
						} else {
							throw new \LogicException();
						}
					}
					return $nodes;
				};
				$dolly = clone $node;
				$dolly->selectionSet = new SelectionSetNode(['selections' => $inlineFragments($node->selectionSet)]);
				return $dolly;
			}
		]);
	}


	private function findFragment(DocumentNode $document, string $fragmentName): FragmentDefinitionNode
	{
		foreach ($document->definitions as $node) {
			if (!$node instanceof FragmentDefinitionNode) {
				continue;
			}
			if ($node->name->value === $fragmentName) {
				return $node;
			}
		}
		throw new \RuntimeException("Fragment $fragmentName not found");
	}


	private function collectVariables(Node $node): array
	{
		$variables = [];
		Visitor::visit($node, [
			'enter' => function ($node) use (&$variables) {
				if ($node instanceof VariableDefinitionNode) {
					return Visitor::skipNode();
				}
				if ($node instanceof VariableNode) {
					$variables[] = $node->name->value;
				}
				return null;
			}
		]);
		return $variables;
	}


	private function cleanupUnusedFragments(DocumentNode $node): DocumentNode
	{
		do {
			$removed = false;
			$usedFragments = [];
			Visitor::visit($node, [
				'enter' => function ($node) use (&$usedFragments) {
					if ($node instanceof FragmentSpreadNode) {
						$usedFragments[] = $node->name->value;
					}
				}
			]);
			$result = Visitor::visit($node, [
				'enter' => function ($node) use ($usedFragments, &$removed) {
					if ($node instanceof FragmentDefinitionNode && !in_array($node->name->value, $usedFragments, true)) {
						$removed = true;
						return Visitor::removeNode();
					}
				}
			]);
			assert($result instanceof DocumentNode);
			$node = $result;
		} while ($removed);
		return $node;
	}


	private function cleanupUnusedVariables(DocumentNode $node): DocumentNode
	{
		$variables = $this->collectVariables($node);
		return Visitor::visit($node, [
			'enter' => function ($node) use ($variables) {
				if ($node instanceof VariableDefinitionNode && !in_array($node->variable->name->value, $variables, true)) {
					return Visitor::removeNode();
				}
			}
		]);
	}
}
