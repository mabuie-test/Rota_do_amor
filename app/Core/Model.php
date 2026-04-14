<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->prepareAndExecute($sql, $params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    protected function fetchAllRows(string $sql, array $params = []): array
    {
        $stmt = $this->prepareAndExecute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Backward-compatible alias used by services.
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->fetchAllRows($sql, $params);
    }

    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->prepareAndExecute($sql, $params);
        return $stmt->rowCount() >= 0;
    }

    private function prepareAndExecute(string $sql, array $params): \PDOStatement
    {
        [$rewrittenSql, $usedPlaceholders, $aliasSource] = $this->rewriteDuplicateNamedPlaceholders($sql);
        $boundParams = $this->buildBoundParams($params, $usedPlaceholders, $aliasSource, $sql);

        $stmt = $this->db->prepare($rewrittenSql);
        $stmt->execute($boundParams);

        return $stmt;
    }

    /**
     * @return array{0:string,1:array<int,string>,2:array<string,string>}
     */
    private function rewriteDuplicateNamedPlaceholders(string $sql): array
    {
        $len = strlen($sql);
        $out = '';
        $used = [];
        $aliasSource = [];
        $counts = [];

        $state = 'normal';
        $i = 0;
        while ($i < $len) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($state === 'normal') {
                if ($ch === "'" || $ch === '"' || $ch === '`') {
                    $state = $ch;
                    $out .= $ch;
                    $i++;
                    continue;
                }

                if ($ch === '-' && $next === '-') {
                    $state = 'line_comment';
                    $out .= $ch . $next;
                    $i += 2;
                    continue;
                }

                if ($ch === '/' && $next === '*') {
                    $state = 'block_comment';
                    $out .= $ch . $next;
                    $i += 2;
                    continue;
                }

                if ($ch === ':' && $next !== ':' && preg_match('/[A-Za-z_]/', $next) === 1) {
                    $j = $i + 2;
                    while ($j < $len && preg_match('/[A-Za-z0-9_]/', $sql[$j]) === 1) {
                        $j++;
                    }

                    $base = substr($sql, $i + 1, $j - $i - 1);
                    $counts[$base] = ($counts[$base] ?? 0) + 1;
                    $alias = $counts[$base] === 1 ? $base : $base . '__dup' . $counts[$base];

                    $out .= ':' . $alias;
                    $used[] = $alias;
                    $aliasSource[$alias] = $base;
                    $i = $j;
                    continue;
                }

                $out .= $ch;
                $i++;
                continue;
            }

            if ($state === 'line_comment') {
                $out .= $ch;
                $i++;
                if ($ch === "\n") {
                    $state = 'normal';
                }
                continue;
            }

            if ($state === 'block_comment') {
                $out .= $ch;
                $i++;
                if ($ch === '*' && $next === '/') {
                    $out .= '/';
                    $i++;
                    $state = 'normal';
                }
                continue;
            }

            // String/backtick contexts with escape handling.
            $out .= $ch;
            $i++;
            if ($ch === '\\' && $i < $len) {
                $out .= $sql[$i];
                $i++;
                continue;
            }
            if ($ch === $state) {
                $state = 'normal';
            }
        }

        $duplicates = array_keys(array_filter($counts, static fn(int $count): bool => $count > 1));
        if ($duplicates !== []) {
            error_log('[pdo.placeholder_rewrite] duplicates=' . implode(',', $duplicates) . ' sql_hash=' . sha1($sql));
        }

        return [$out, $used, $aliasSource];
    }

    /**
     * @param array<int,string> $usedPlaceholders
     * @param array<string,string> $aliasSource
     * @return array<string,mixed>
     */
    private function buildBoundParams(array $params, array $usedPlaceholders, array $aliasSource, string $originalSql): array
    {
        if ($usedPlaceholders === []) {
            return $params;
        }

        $normalized = [];
        foreach ($params as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $name = ltrim($key, ':');
            $normalized[$name] = $value;
        }

        $bound = [];
        $missing = [];
        foreach ($usedPlaceholders as $alias) {
            $source = $aliasSource[$alias] ?? $alias;
            if (array_key_exists($alias, $normalized)) {
                $bound[':' . $alias] = $normalized[$alias];
                continue;
            }
            if (array_key_exists($source, $normalized)) {
                $bound[':' . $alias] = $normalized[$source];
                continue;
            }

            $missing[] = ':' . $source;
        }

        if ($missing !== []) {
            $snippet = preg_replace('/\s+/', ' ', trim($originalSql));
            if ($snippet === null) {
                $snippet = trim($originalSql);
            }

            throw new RuntimeException(sprintf(
                'Parâmetros SQL em falta (%s). SQL=%s',
                implode(', ', array_values(array_unique($missing))),
                mb_substr($snippet, 0, 320)
            ));
        }

        $usedBaseNames = array_values(array_unique(array_values($aliasSource)));
        $extra = array_diff(array_keys($normalized), $usedBaseNames);
        if ($extra !== []) {
            error_log('[pdo.placeholder_extra_params] extras=' . implode(',', $extra) . ' sql_hash=' . sha1($originalSql));
        }

        return $bound;
    }
}
