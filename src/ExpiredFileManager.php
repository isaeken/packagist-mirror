<?php


namespace IsaEken\PackagistMirror;


use PDO;
use ProgressBar\Manager;
use RuntimeException;

class ExpiredFileManager
{
    /**
     * @var PDO $pdo
     */
    private PDO $pdo;

    /**
     * ExpiredFileManager constructor.
     *
     * @param string $database_path
     * @param int $expire
     */
    public function __construct(private string $database_path, private int $expire)
    {
        if (file_exists($database_path) && ! is_writable($database_path)) {
            throw new RuntimeException("$database_path is not writable.");
        }

        $this->pdo = new PDO("sqlite:$this->database_path", null, null, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->pdo->beginTransaction();

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS expired (path TEXT PRIMARY KEY, expiredAt INTEGER)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS expiredAtIndex ON expired (expiredAt)");
    }

    public function __destruct()
    {
        $this->pdo->commit();
        $this->pdo->exec("VACUUM");
    }

    /**
     * Add record into expired.db
     *
     * @param string $fullpath
     * @param int|null $now
     * @return void
     */
    public function add(string $fullpath, int|null $now = null): void
    {
        static $insert, $path, $expiredAt;
        empty($now) or $now = $_SERVER["REQUEST_TIME"];

        if (empty($insert)) {
            $insert = $this->pdo->prepare("INSERT OR IGNORE INTO expired(path, expiredAt) VALUES(:path, :expiredAt)");
            $insert->bindParam(":path", $path, PDO::PARAM_STR);
            $insert->bindParam(":expiredAt", $expiredAt, PDO::PARAM_INT);
        }

        $path = $fullpath;
        $expiredAt = $now;
        $insert->execute();
    }

    /**
     * Delete record from expired.db
     *
     * @param string $fullpath
     * @return void
     */
    public function delete(string $fullpath): void
    {
        static $delete, $path;

        if (empty($delete)) {
            $delete = $this->pdo->prepare("DELETE FROM expired WHERE path = :path");
            $delete->bindParam(":path", $path, PDO::PARAM_STR);
        }

        $path = $fullpath;
        $delete->execute();
    }

    /**
     * Get file list from expired.db
     *
     * @param int|null $until
     * @return array
     */
    public function getExpiredFileList(int|null $until = null): array
    {
        isset($until) or $until = $_SERVER["REQUEST_TIME"] - $this->expire * 60;

        $stmt = $this->pdo->prepare("SELECT path FROM expired WHERE expiredAt <= :expiredAt");
        $stmt->bindValue(":expiredAt", $until, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_COLUMN, 0);

        $list = [];
        foreach ($stmt as $file) {
            $list[] = $file;
        }

        return $list;
    }

    /**
     * @return $this
     */
    public function clear(): static
    {
        $expiredFiles = $this->getExpiredFileList();
        $progressBar = new Manager(0, count($expiredFiles));
        $progressBar->setFormat("   - Clearing Expired Files: %current%/%max% [%bar%] %percent%%");

        foreach ($expiredFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }

            $this->delete($file);
            $progressBar->advance();
        }

        return $this;
    }
}
