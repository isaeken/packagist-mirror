<?php


namespace IsaEken\PackagistMirror;


/**
 * Class Config
 * @package IsaEken\PackagistMirror
 * @property bool $retry
 * @property string[] $directories
 * @property string[] $files
 * @property string[] $databases
 * @property string[] $urls
 * @property array $service
 */
class Config
{
    /**
     * @var array $configurations
     */
    private array $configurations = [
        "retry" => true,

        "directories" => [
            "cache" => __DIR__ . "/../cache",
            "public" => __DIR__ . "/../public_html",
        ],

        "files" => [
            "lock" => __DIR__ . "/../cache/.lock",
        ],

        "databases" => [
            "expired" => __DIR__ . "/../cache/.expired.db",
        ],

        "urls" => [
            "packagist" => "https://packagist.org",
        ],

        "service" => [
            "max_connections" => 6,
            "generate_gz" => true,
            "expire_minutes" => 24 * 60,
            "url" => "http://localhost",
        ],
    ];

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->configurations[$name];
    }

    /**
     * @param string $name
     * @param $value
     */
    public function __set(string $name, $value): void
    {
        $this->configurations[$name] = $value;
    }

    /**
     * @param string $name
     * @return string
     */
    public function getDirectory(string $name): string
    {
        return $this->configurations["directories"][$name];
    }

    /**
     * @param string $name
     * @return string
     */
    public function getFile(string $name): string
    {
        return $this->configurations["files"][$name];
    }

    /**
     * @param string $name
     * @return string
     */
    public function getDatabase(string $name): string
    {
        return $this->configurations["databases"][$name];
    }

    /**
     * @param string $name
     * @return string
     */
    public function getUrl(string $name): string
    {
        return $this->configurations["urls"][$name];
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getEnv(string $name): mixed
    {
        return $this->configurations["service"][$name];
    }
}
