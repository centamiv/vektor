<?php

namespace Centamiv\Vektor\Services;

use Centamiv\Vektor\Core\Config;
use Centamiv\Vektor\Storage\Binary\VectorFile;
use Centamiv\Vektor\Storage\Binary\GraphFile;
use Centamiv\Vektor\Storage\Binary\MetaFile;
use RuntimeException;

class Optimizer
{
    /** @var resource|null */
    private $lockHandle;

    /**
     * Runs the optimization process.
     * 
     * 1. Vacuums deleted vectors.
     * 2. Rebuilds HNSW Graph (balancing it).
     * 3. Rebuilds Meta Index.
     */
    public function run(): void
    {
        // 1. Acquire Global Lock to block all access
        $this->acquireLock();

        try {
            $tmpVector = Config::DATA_DIR . '/vector.tmp';
            $tmpGraph = Config::DATA_DIR . '/graph.tmp';
            $tmpMeta = Config::DATA_DIR . '/meta.tmp';

            // Clean up old temps just in case
            if (file_exists($tmpVector)) unlink($tmpVector);
            if (file_exists($tmpGraph)) unlink($tmpGraph);
            if (file_exists($tmpMeta)) unlink($tmpMeta);

            // 2. Setup Sources and Targets
            // Source reads from the current active files
            $sourceVectorFile = new VectorFile();

            // Target writes to .tmp files
            $targetVectorFile = new VectorFile($tmpVector);
            $targetGraphFile = new GraphFile($tmpGraph);
            $targetMetaFile = new MetaFile($tmpMeta);

            // 3. Setup Target Indexer (With NO locking)
            // We use a subclass that ignores locking, since we already hold the global lock
            $targetIndexer = new NoLockIndexer($targetVectorFile, $targetGraphFile, $targetMetaFile);

            // 4. Iterate and Re-Index
            // scan() yields only active (non-deleted) vectors
            foreach ($sourceVectorFile->scan() as $record) {
                // Insert into new DB
                // We rely on Indexer to build Graph and Meta from scratch
                // This effectively "balances" the HNSW graph as we insert into a fresh structure
                $targetIndexer->insert($record['id'], $record['vector']);
            }

            // 5. Cleanup Resources
            // Crucial on Windows to release file handles before renaming
            unset($sourceVectorFile);
            unset($targetIndexer);
            unset($targetVectorFile);
            unset($targetGraphFile);
            unset($targetMetaFile);

            gc_collect_cycles();

            // 6. Swap Files
            $backupVector = Config::VECTOR_FILE . '.bak';
            $backupGraph = Config::GRAPH_FILE . '.bak';
            $backupMeta = Config::META_FILE . '.bak';

            // Delete old backups
            if (file_exists($backupVector)) unlink($backupVector);
            if (file_exists($backupGraph)) unlink($backupGraph);
            if (file_exists($backupMeta)) unlink($backupMeta);

            // Rename Current -> Backup
            if (file_exists(Config::VECTOR_FILE)) rename(Config::VECTOR_FILE, $backupVector);
            if (file_exists(Config::GRAPH_FILE)) rename(Config::GRAPH_FILE, $backupGraph);
            if (file_exists(Config::META_FILE)) rename(Config::META_FILE, $backupMeta);

            // Rename Tmp -> Current
            rename($tmpVector, Config::VECTOR_FILE);
            rename($tmpGraph, Config::GRAPH_FILE);
            rename($tmpMeta, Config::META_FILE);
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock()
    {
        $this->lockHandle = fopen(Config::LOCK_FILE, 'c');
        if (!$this->lockHandle) {
            throw new RuntimeException("Could not open lock file: " . Config::LOCK_FILE);
        }
        if (!flock($this->lockHandle, LOCK_EX)) {
            throw new RuntimeException("Could not acquire lock");
        }
    }

    private function releaseLock()
    {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
        }
    }
}

/**
 * Helper class to bypass storage locking since Optimizer holds the master lock.
 */
class NoLockIndexer extends Indexer
{
    protected function acquireLock()
    {
        // No-op
    }

    protected function releaseLock()
    {
        // No-op
    }
}
