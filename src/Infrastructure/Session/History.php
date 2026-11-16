<?php

declare(strict_types=1);

namespace PhpPgAdmin\Infrastructure\Session;

final class History
{
    public static function clear(string $serverId, string $selectedDatabase): void
    {
        if (
            isset($_SESSION['history']) &&
            is_array($_SESSION['history']) &&
            isset($_SESSION['history'][$serverId]) &&
            is_array($_SESSION['history'][$serverId]) &&
            isset($_SESSION['history'][$serverId][$selectedDatabase]) &&
            is_array($_SESSION['history'][$serverId][$selectedDatabase])
        ) {
            unset($_SESSION['history'][$serverId][$selectedDatabase]);
        }
    }

    public static function deleteEntry(string $serverId, string $selectedDatabase, string $queryId): void
    {
        if (
            isset($_SESSION['history']) &&
            is_array($_SESSION['history']) &&
            isset($_SESSION['history'][$serverId]) &&
            is_array($_SESSION['history'][$serverId]) &&
            isset($_SESSION['history'][$serverId][$selectedDatabase]) &&
            is_array($_SESSION['history'][$serverId][$selectedDatabase]) &&
            isset($_SESSION['history'][$serverId][$selectedDatabase][$queryId])
        ) {
            unset($_SESSION['history'][$serverId][$selectedDatabase][$queryId]);
        }
    }

    /**
     * @return array<string, array{'query': string, 'paginate': bool}>
     */
    public static function getHistory(string $serverId, string $database): array
    {
        $history = [];

        if (!isset($_SESSION['history']) || !is_array($_SESSION['history'])) {
            return $history;
        }

        if (!isset($_SESSION['history'][$serverId]) || !is_array($_SESSION['history'][$serverId])) {
            return $history;
        }

        if (
            !isset($_SESSION['history'][$serverId][$database]) ||
            !is_array($_SESSION['history'][$serverId][$database])
        ) {
            return $history;
        }

        foreach ($_SESSION['history'][$serverId][$database] as $queryId => $entry) {
            if (!is_string($queryId) || !is_array($entry)) {
                continue;
            }

            if (!isset($entry['query']) || !is_string($entry['query'])) {
                continue;
            }

            $paginate = false;

            if (isset($entry['paginate']) && is_string($entry['paginate'])) {
                $paginate = $entry['paginate'] === 't';
            }

            $history[$queryId] = [
                'paginate' => $paginate,
                'query' => $entry['query'],
            ];
        }

        return $history;
    }

    /**
     * @return array{'query': string, 'paginate': bool}|null
     */
    public static function getHistoryEntry(string $serverId, string $database, string $queryId): ?array
    {
        $history = self::getHistory($serverId, $database);

        return $history[$queryId] ?? null;
    }
}
