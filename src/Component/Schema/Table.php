<?php

namespace Cinch\Component\Schema;

use Exception;
use InvalidArgumentException;

class Table
{
    /** @var ColumnDefinition[] */
    private array $columns = [];
    private array $checks = [];

    public function __construct(
        private readonly Session $session,
        private readonly string $name,
        private readonly string $options)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function varchar(string $name, int $length): ColumnDefinition
    {
        if ($length < 1)
            throw new InvalidArgumentException("varchar length must be greater than zero, found $length");

        return $this->getColumn($name) ??
            $this->addColumn(new ColumnDefinition($this, $name, new Type\Varchar($length)));
    }

    public function tinytext(string $name): ColumnDefinition
    {
        return $this->getColumn($name) ??
            $this->addColumn(new ColumnDefinition($this, $name, new Type\Text('tinytext')));
    }

    public function mediumtext(string $name): ColumnDefinition
    {
        return $this->getColumn($name) ??
            $this->addColumn(new ColumnDefinition($this, $name, new Type\Text('mediumtext')));
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->getColumn($name) ??
            $this->addColumn(new ColumnDefinition($this, $name, new Type\Text('text')));
    }

    public function longtext(string $name): ColumnDefinition
    {
        return $this->getColumn($name) ??
            $this->addColumn(new ColumnDefinition($this, $name, new Type\Text('longtext')));
    }

    public function bool(string $name, bool $forceInt = false): ColumnDefinition
    {
        return $this->getColumn($name) ??
            $this->addColumn(new ColumnDefinition($this, $name, new Type\Boolean($forceInt)));
    }

    public function tinyint(string $name, bool $isUnsigned = true): ColumnDefinition
    {
        return $this->addInteger($name, 'tinyint', $isUnsigned);
    }

    public function smallint(string $name, bool $isUnsigned = true): ColumnDefinition
    {
        return $this->addInteger($name, 'smallint', $isUnsigned);
    }

    public function mediumint(string $name, bool $isUnsigned = true): ColumnDefinition
    {
        return $this->addInteger($name, 'mediumint', $isUnsigned);
    }

    public function int(string $name, bool $isUnsigned = true): ColumnDefinition
    {
        return $this->addInteger($name, 'int', $isUnsigned);
    }

    public function bigint(string $name, bool $isUnsigned = true): ColumnDefinition
    {
        return $this->addInteger($name, 'bigint', $isUnsigned);
    }

    /**
     * @param Check|string $value when a string, this is a raw sql expression: check ($expression)
     * @param string $name only used when $value is a string
     * @return self
     */
    public function check(Check|string $value, string $name = ''): static
    {
        $this->checks[] = is_string($value) ? new Check\Expression($name, $value) : $value;
        return $this;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function execute(): void
    {
        $statements = [
            ...array_map(fn ($c) => $c->compile($this->session), $this->columns),
            ...array_map(fn ($c) => $c->compile($this->session), $this->checks)
        ];

        $sql = sprintf("create table %s\n(\n    %s\n);\n",
            $this->session->quoteIdentifier($this->name),
            implode(",\n    ", $statements)
        );

        echo $sql;
    }

    private function addInteger(string $name, string $type, bool $isUnsigned): ColumnDefinition
    {
        return $this->getColumn($name) ??
            $this->addColumn(new ColumnDefinition($this, $name, new Type\Integer($type, $isUnsigned)));
    }

    protected function addColumn(ColumnDefinition $c): ColumnDefinition
    {
        return $this->columns[$c->getName()] = $c;
    }

    private function getColumn(string $name): ColumnDefinition|null
    {
        return $this->columns[$name] ?? null;
    }

    public function checkBetween(string $column, int|float|string $min, int|float|string $max, string $name = ''): static
    {
        return $this->check(new Check\Between($name, $column, $min, $max, not: false));
    }

    public function checkNotBetween(string $column, int|float|string $min, int|float|string $max, string $name = ''): static
    {
        return $this->check(new Check\Between($name, $column, $min, $max, not: true));
    }

    public function checkBetweenLength(string $column, int $min, int $max, string $name = ''): static
    {
        return $this->check(new Check\BetweenLength($name, $column, $min, $max, not: false));
    }

    public function checkNotBetweenLength(string $column, int $min, int $max, string $name = ''): static
    {
        return $this->check(new Check\BetweenLength($name, $column, $min, $max, not: true));
    }

    public function checkNotEmpty(string $column, string $name = ''): static
    {
        return $this->check(new Check\Expression($name, "coalesce($column, '') <> ''"));
    }

    public function checkPattern(string $column, string $pattern, bool $isCaseSensitive = true, string $name = ''): static
    {
        return $this->check(new Check\Pattern($name, $column, $pattern, $isCaseSensitive, not: false));
    }

    public function checkNotPattern(string $column, string $pattern, bool $isCaseSensitive = true, string $name = ''): static
    {
        return $this->check(new Check\Pattern($name, $column, $pattern, $isCaseSensitive, not: true));
    }

    public function checkIn(string $column, array $values, string $name = ''): static
    {
        return $this->check(new Check\In($name, $column, $values, not: false));
    }

    public function checkNotIn(string $column, array $values, string $name = ''): static
    {
        return $this->check(new Check\In($name, $column, $values, not: true));
    }

    public function checkGreaterThan(string $column, string|int|float $value, string $name = ''): static
    {
        return $this->check(new Check\Comparison($name, $column, Check\Operator::GT, $value));
    }

    public function checkGreaterThanEquals(string $column, string|int|float $value, string $name = ''): static
    {
        return $this->check(new Check\Comparison($name, $column, Check\Operator::GE, $value));
    }

    public function checkLessThan(string $column, string|int|float $value, string $name = ''): static
    {
        return $this->check(new Check\Comparison($name, $column, Check\Operator::LT, $value));
    }

    public function checkLessThanEquals(string $column, string|int|float $value, string $name = ''): static
    {
        return $this->check(new Check\Comparison($name, $column, Check\Operator::LE, $value));
    }

    public function checkEquals(string $column, string|int|float $value, string $name = ''): static
    {
        return $this->check(new Check\Comparison($name, $column, Check\Operator::EQ, $value));
    }

    public function checkNotEquals(string $column, string|int|float $value, string $name = ''): static
    {
        return $this->check(new Check\Comparison($name, $column, Check\Operator::NE, $value));
    }
}
