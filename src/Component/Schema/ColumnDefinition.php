<?php

namespace Cinch\Component\Schema;

use BadMethodCallException;
use Exception;

/**
 * @method ColumnDefinition check(Check|string $value, string $name = '')
 * @method ColumnDefinition checkBetween(int|float|string $min, int|float|string $max, string $name = '')
 * @method ColumnDefinition checkNotBetween(int|float|string $min, int|float|string $max, string $name = '')
 * @method ColumnDefinition checkBetweenLength(int $min, int $max, string $name = '')
 * @method ColumnDefinition checkNotBetweenLength(int $min, int $max, string $name = '')
 * @method ColumnDefinition checkIn(array $values, string $name = '')
 * @method ColumnDefinition checkNotIn(array $values, string $name = '')
 * @method ColumnDefinition checkNotEmpty(string $name = '')
 * @method ColumnDefinition checkPattern(string $pattern, bool $isCaseSensitive = true, string $name = '')
 * @method ColumnDefinition checkNotPattern(string $pattern, bool $isCaseSensitive = true, string $name = '')
 * @method ColumnDefinition checkGreaterThan(string|int|float $value, string $name = '')
 * @method ColumnDefinition checkGreaterThanEquals(string|int|float $value, string $name = '')
 * @method ColumnDefinition checkLessThan(string|int|float $value, string $name = '')
 * @method ColumnDefinition checkLessThanEquals(string|int|float $value, string $name = '')
 * @method ColumnDefinition checkEquals(string|int|float $value, string $name = '')
 * @method ColumnDefinition checkNotEquals(string|int|float $value, string $name = '')
 * @see Table
 */
class ColumnDefinition
{
    /** NULL or NOT NULL constraint */
    private bool $null = true;
    private DefaultValue|null $defaultValue = null;
    private string $collationName = '';
    /** either 'primary key', 'unique' or '' */
    private string $keyType = '';
    private GeneratedColumn|null $generated = null;

    public function __construct(
        private readonly Table $table,
        private readonly string $name,
        private readonly Type $type)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function notNull(): self
    {
        $this->null = false;
        return $this;
    }

    public function default(string|int|float|bool|null $value, bool $isExpression = false): self
    {
        $this->defaultValue = new DefaultValue($value, $isExpression);
        return $this;
    }

    public function collate(string $collationName): self
    {
        $this->collationName = $collationName;
        return $this;
    }

    public function generated(string $expression, bool $isVirtual = false): self
    {
        $this->generated = new GeneratedColumn($expression, $isVirtual);
        return $this;
    }

    /* all check calls are proxied to Table. All but check() prepend column name */
    public function __call(string $method, array $arguments): self
    {
        if (str_starts_with($method, 'check') && method_exists($this->table, $method)) {
            if ($method != 'check')
                array_unshift($arguments, $this->name);
            $this->table->$method(...$arguments);
            return $this;
        }

        throw new BadMethodCallException(sprintf('unknown method %s::%s', self::class, $method));
    }

    public function primary(): self
    {
        $this->keyType = 'primary key';
        return $this;
    }

    public function unique(): self
    {
        $this->keyType = 'unique';
        return $this;
    }

    /**
     * @throws Exception
     */
    public function compile(Session $session): string
    {
        $platformName = $session->getPlatformName();
        $definition = [$session->quoteIdentifier($this->name)];

        /* avoid data_type when generated column and SQL Server */
        if (!$this->generated || $platformName != 'sqlsrv')
            $definition[] = $this->type->compile($this, $platformName);

        if ($this->generated)
            $definition[] = $this->generated->compile($platformName);

        if ($this->collationName) {
            $name = $platformName == 'sqlsrv' ? $this->collationName : $session->quoteIdentifier($this->collationName);
            $definition[] = "collate $name";
        }

        $definition[] = $this->null ? 'null' : 'not null';

        if ($this->keyType)
            $definition[] = $this->keyType;

        if ($this->defaultValue) {
            $forceIntBool = $this->type instanceof Type\Boolean && $this->type->shouldForceInt();
            $definition[] = $this->defaultValue->compile($session, $forceIntBool);
        }

        return implode(' ', $definition);
    }
}
