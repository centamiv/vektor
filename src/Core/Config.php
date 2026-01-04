<?php

namespace Centamiv\Vektor\Core;

final class Config
{
    private static ?string $dataDir = null;
    private static ?int $dimensions = null;
    private static ?array $env = null;
    private static ?string $envFile = null;

    public const VECTOR_FLAG_SIZE = 1;
    public const VECTOR_ID_SIZE = 36;

    public const GRAPH_HEADER_SIZE = 8; // EntryID (4) + TotalNodes (4)
    public const GRAPH_NODE_SIZE = 324; // 1 (MaxLvl) + 128 (L0) + 64 (L1) + 64 (L2) + 64 (L3)

    // Graph Config
    public const M = 16;
    public const M0 = 32;
    public const L = 4; // Max levels 0-3 (So max_level is 3)

    public const META_ROW_SIZE = 48; // 36 (Key) + 4 (Val) + 4 (Left) + 4 (Right)

    /**
     * Set the vector dimensions.
     *
     * @param int $dimensions The vector dimensions.
     * @return void
     */
    public static function setDimensions(int $dimensions): void
    {
        self::$dimensions = $dimensions;
    }

    /**
     * Set the path to the data directory.
     *
     * @param string $path The path to the data directory.
     * @return void
     */
    public static function setDataDir(string $path): void
    {
        self::$dataDir = rtrim($path, '/\\');
    }

    /**
     * Get the path to the data directory.
     *
     * @return string The path to the data directory.
     */
    public static function getDataDir(): string
    {
        return self::$dataDir ?? (__DIR__ . '/../../data');
    }

    /**
     * Set the path to the environment file.
     *
     * @param string $path The path to the environment file.
     * @return void
     */
    public static function setEnvFile(string $path): void
    {
        self::$envFile = $path;
        self::$env = null; // Reset cache, will be loaded lazy on next getEnv call
    }

    /**
     * Load environment variables from a file.
     *
     * @return void
     */
    private static function loadEnv(): void
    {
        self::$env = [];
        $path = self::$envFile ?? (__DIR__ . '/../../.env');

        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                self::$env[$key] = trim($value, '"\'');
            }
        }
    }

    /**
     * Get an environment variable value.
     *
     * @param string $key The environment variable name.
     * @param mixed $default The default value if the environment variable is not set.
     * @return mixed The environment variable value or the default value.
     */
    public static function getEnv(string $key, mixed $default = null): mixed
    {
        if (self::$env === null) {
            self::loadEnv();
        }
        return self::$env[$key] ?? $default;
    }

    /**
     * Get the vector dimensions.
     *
     * @return int The vector dimensions.
     */
    public static function getDimensions(): int
    {
        return self::$dimensions ?? (int) self::getEnv('VEKTOR_DIMENSIONS', 1536);
    }

    /**
     * Get the path to the vector file.
     *
     * @return string The path to the vector file.
     */
    public static function getVectorFile(): string
    {
        return self::getDataDir() . '/vector.bin';
    }

    /**
     * Get the size of the vector data.
     *
     * @return int The size of the vector data.
     */
    public static function getVectorDataSize(): int
    {
        return self::getDimensions() * 4; // 4 bytes per float
    }

    /**
     * Get the size of the vector row.
     *
     * @return int The size of the vector row.
     */
    public static function getVectorRowSize(): int
    {
        // 1 (Flag) + 36 (ExtID) + VectorDataSize
        return 1 + 36 + self::getVectorDataSize();
    }

    /**
     * Get the path to the graph file.
     *
     * @return string The path to the graph file.
     */
    public static function getGraphFile(): string
    {
        return self::getDataDir() . '/graph.bin';
    }

    /**
     * Get the path to the meta file.
     *
     * @return string The path to the meta file.
     */
    public static function getMetaFile(): string
    {
        return self::getDataDir() . '/meta.bin';
    }

    /**
     * Get the path to the lock file.
     *
     * @return string The path to the lock file.
     */
    public static function getLockFile(): string
    {
        return self::getDataDir() . '/db.lock';
    }

    /**
     * Get the API token.
     *
     * @return ?string The API token.
     */
    public static function getApiToken(): ?string
    {
        return self::getEnv('VEKTOR_API_TOKEN');
    }
}
