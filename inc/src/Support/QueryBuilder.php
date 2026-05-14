<?php
declare(strict_types=1);

namespace Vie\Support;

final class QueryBuilder
{
    private array $selects = ['*'];
    private array $wheres  = [];
    private array $params  = [];
    private array $orderBy = [];
    private ?int $limit    = null;
    private ?int $offset   = null;

    public function __construct(private readonly string $table)
    {
    }

    public function select(array $columns): self
    {
        $this->selects = $columns;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $placeholder = is_int($value) || is_float($value) ? '%s' : '%s';
        $this->wheres[] = "{$column} {$operator} {$placeholder}";
        $this->params[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->wheres[] = '1 = 0';
            return $this;
        }
        $placeholders   = implode(',', array_fill(0, count($values), '%s'));
        $this->wheres[] = "{$column} IN ({$placeholders})";
        array_push($this->params, ...$values);
        return $this;
    }

    public function whereLike(string $column, string $pattern): self
    {
        $this->wheres[] = "{$column} LIKE %s";
        $this->params[] = $pattern;
        return $this;
    }

    public function whereOrLike(array $columns, string $pattern): self
    {
        if ($columns === []) {
            return $this;
        }
        $parts = [];
        foreach ($columns as $col) {
            $parts[]        = "{$col} LIKE %s";
            $this->params[] = $pattern;
        }
        $this->wheres[] = '(' . implode(' OR ', $parts) . ')';
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = "{$column} IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = "{$column} IS NOT NULL";
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "{$column} {$dir}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function toSelectSql(): string
    {
        $cols = implode(', ', $this->selects);
        $sql  = "SELECT {$cols} FROM {$this->table}";
        $sql .= $this->buildWhere();
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimit();
        return $sql;
    }

    public function toCountSql(): string
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $sql .= $this->buildWhere();
        return $sql;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function hasParams(): bool
    {
        return $this->params !== [];
    }

    private function buildWhere(): string
    {
        if ($this->wheres === []) {
            return '';
        }
        return ' WHERE ' . implode(' AND ', $this->wheres);
    }

    private function buildOrderBy(): string
    {
        if ($this->orderBy === []) {
            return '';
        }
        return ' ORDER BY ' . implode(', ', $this->orderBy);
    }

    private function buildLimit(): string
    {
        if ($this->limit === null) {
            return '';
        }
        $sql = " LIMIT {$this->limit}";
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
        return $sql;
    }
}
