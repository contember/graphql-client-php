<?php declare(strict_types = 1);

namespace Nextras\Dbal\Bridges\NetteTracy;

use Contember\GraphQL\GQLQueryExecutor;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\IBarPanel;


class DbQueriesPanel implements IBarPanel
{
	/** @var array */
	private $queries = [];


	public static function install(GQLQueryExecutor $queryExecutor): void
	{
		Debugger::getBar()->addPanel(new DbQueriesPanel($queryExecutor));
	}


	public function __construct(GQLQueryExecutor $queryExecutor)
	{
		$queryExecutor->onQuery[] = [$this, 'logQuery'];
	}


	public function logQuery(GQLQueryExecutor $executor, \stdClass $query): void
	{
		$queries = isset($query->result->extensions->dbQueries) ? $query->result->extensions->dbQueries : [];
		$this->queries = array_merge($this->queries, $queries);
	}


	public function getTab(): ?string
	{
		$count = count($this->queries);

		ob_start();
		require __DIR__ . '/DbQueriesPanel.tab.phtml';
		return (string) ob_get_clean();
	}


	public function getPanel(): ?string
	{
		$count = count($this->queries);
		$queries = $this->queries;
		$queries = array_map(function ($row) {
			$row->sql = self::highlight($row->sql);
			return $row;
		}, $queries);

		ob_start();
		require __DIR__ . '/DbQueriesPanel.panel.phtml';
		return (string) ob_get_clean();
	}


	/**
	 * Based on https://github.com/nextras/dbal/blob/master/src/Utils/SqlHighlighter.php
	 */
	public static function highlight(string $sql): string
	{
		static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|SHOW|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE|START\s+TRANSACTION|COMMIT|ROLLBACK|(?:RELEASE\s+|ROLLBACK\s+TO\s+)?SAVEPOINT';
		static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE|REGEXP|TRUE|FALSE';

		$sql = " $sql ";
		$sql = htmlspecialchars($sql, ENT_IGNORE, 'UTF-8');
		$sql = preg_replace_callback("#(/\\*.+?\\*/)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is", function ($matches) {
			if (!empty($matches[1])) { // comment
				return '<em style="color:gray">' . $matches[1] . '</em>';
			} elseif (!empty($matches[2])) { // most important keywords
				return "\n<strong style=\"color:#2D44AD\">" . $matches[2] . '</strong>';
			} elseif (!empty($matches[3])) { // other keywords
				return '<strong>' . $matches[3] . '</strong>';
			}
		}, $sql);
		assert($sql !== null);
		return trim($sql);
	}


	/**
	 * @param mixed $value
	 */
	public static function dump($value): string
	{
		if (is_string($value) && preg_match('~^[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}-[0-9a-f]{12}\z~', $value)) {
			static $uuidCounter = [];
			if (isset($uuidCounter[$value])) {
				$i = $uuidCounter[$value];
			} else {
				$i = count($uuidCounter) + 1;
				$uuidCounter[$value] = $i;
			}

			return '<pre class="tracy-dump"><span style="color: #969696">' . $value . '</span> (uuid #' . $i . ')</pre>';
		}
		return Dumper::toHtml($value);
	}
}
