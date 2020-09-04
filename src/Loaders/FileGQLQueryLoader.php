<?php declare(strict_types = 1);

namespace Contember\GraphQL\Loaders;

use Contember\GraphQL\Utils\VariableReplacer;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;

class FileGQLQueryLoader implements GQLQueryLoader
{
	/** @var string */
	private $fragmentsPathMapping;


	public function __construct(string $fragmentsPathMapping)
	{
		$this->fragmentsPathMapping = $fragmentsPathMapping;
	}


	public function load(string $filename): string
	{
		$context = new GQLLoadingContext();
		$this->loadInternal($filename, $context);

		return $context->getSource();
	}


	public function loadInternal(string $filename, GQLLoadingContext $context): void
	{
		if ($context->hasFile($filename)) {
			return;
		}
		$context->addFile($filename);
		$relativeDir = dirname($filename);
		$content = file_get_contents($filename);
		assert($content !== false);
		$context->appendSource($content);

		preg_match_all('/^\s*# ?import ("([^"]+)"|\'([^\']+)\')\s*$/m', $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$file = $match[2] ?: $match[3];
			$this->loadInternal($relativeDir . DIRECTORY_SEPARATOR . $file, $context);
		}

		$document = Parser::parse($content);
		Visitor::visit($document, [
			'enter' => function ($node) use ($context) {
				if ($node instanceof FragmentSpreadNode) {
					if (!$context->hasFragment($node->name->value)) {
						$this->autoloadFragment($node->name->value, $context);
					}
				} else {
					if ($node instanceof FragmentDefinitionNode) {
						$context->addFragment($node->name->value);
					}
				}
			}
		]);
	}


	public function autoloadFragment(string $fragmentName, GQLLoadingContext $context): void
	{
		$filename = VariableReplacer::replace($this->fragmentsPathMapping, ['name' => $fragmentName]);
		if (file_exists($filename)) {
			$this->loadInternal($filename, $context);
		}
	}
}
