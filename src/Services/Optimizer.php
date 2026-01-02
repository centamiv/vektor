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
            $tmpVector = Config::getDataDir() . '/vector.tmp';
            $tmpGraph = Config::getDataDir() . '/graph.tmp';
            $tmpMeta = Config::getDataDir() . '/meta.tmp';

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
            $backupVector = Config::getVectorFile() . '.bak';
            $backupGraph = Config::getGraphFile() . '.bak';
            $backupMeta = Config::getMetaFile() . '.bak';

            // Delete old backups
            if (file_exists($backupVector)) unlink($backupVector);
            if (file_exists($backupGraph)) unlink($backupGraph);
            if (file_exists($backupMeta)) unlink($backupMeta);

            // Rename Current -> Backup
            if (file_exists(Config::getVectorFile())) rename(Config::getVectorFile(), $backupVector);
            if (file_exists(Config::getGraphFile())) rename(Config::getGraphFile(), $backupGraph);
            if (file_exists(Config::getMetaFile())) rename(Config::getMetaFile(), $backupMeta);

            // Rename Tmp -> Current
            rename($tmpVector, Config::getVectorFile());
            rename($tmpGraph, Config::getGraphFile());
            rename($tmpMeta, Config::getMetaFile());
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock()
    {
        $this->lockHandle = fopen(Config::getLockFile(), 'c');
        if (!$this->lockHandle) {
            throw new RuntimeException("Could not open lock file: " . Config::getLockFile());
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
