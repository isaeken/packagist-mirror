<?php


namespace IsaEken\PackagistMirror\Controllers;


use IsaEken\PackagistMirror\Application;
use IsaEken\PackagistMirror\Router;

class Flusher extends Controller
{
    /**
     * @var string $name
     */
    public static string $name = "Flusher";

    /**
     * @var string $description
     */
    public static string $description = "Flush mirror.";

    /**
     * @return int
     */
    public static function run(): int
    {
        $config = Application::$app->config;
        $cache_directory = $config->directories["cache"];

        rename($cache_directory . "/packages.json.new", $cache_directory . "/packages.json");
        file_put_contents($cache_directory . "/packages.json.gz", gzencode(file_get_contents($cache_directory . "/packages.json")));

        return 0;
    }
}
