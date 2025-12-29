<?php

namespace Centamiv\Vektor\Core;

final class Config
{
    // Vector Dimensions
    public const DIMENSION = 1536;

    // File Paths
    public const DATA_DIR = __DIR__ . '/../../data';

    // *** A. vector.bin ***
    public const VECTOR_FILE = self::DATA_DIR . '/vector.bin';
    public const VECTOR_ROW_SIZE = 6181; // 1 (Flag) + 36 (ExtID) + 6144 (Vector)
    public const VECTOR_FLAG_SIZE = 1;
    public const VECTOR_ID_SIZE = 36;
    public const VECTOR_DATA_SIZE = 6144; // 1536 * 4 bytes

    // *** B. graph.bin ***
    public const GRAPH_FILE = self::DATA_DIR . '/graph.bin';
    public const GRAPH_HEADER_SIZE = 8; // EntryID (4) + TotalNodes (4)
    public const GRAPH_NODE_SIZE = 324; // 1 (MaxLvl) + 128 (L0) + 64 (L1) + 64 (L2) + 64 (L3)

    // Graph Config
    public const M = 16;
    public const M0 = 32;
    public const L = 4; // Max levels 0-3 (So max_level is 3)

    // *** C. meta.bin ***
    public const META_FILE = self::DATA_DIR . '/meta.bin';
    public const META_ROW_SIZE = 48; // 36 (Key) + 4 (Val) + 4 (Left) + 4 (Right)

    // Lock File
    public const LOCK_FILE = self::DATA_DIR . '/db.lock';
}
