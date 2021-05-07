<?php


namespace IsaEken\PackagistMirror\Controllers;


use IsaEken\PackagistMirror\Application;
use IsaEken\PackagistMirror\Downloader;
use IsaEken\PackagistMirror\ExpiredFileManager;
use IsaEken\PackagistMirror\Request\MultiCurl;
use IsaEken\PackagistMirror\Request\Request;
use IsaEken\PackagistMirror\Router;
use RuntimeException;
use SplQueue;

class Crawler extends Controller
{
    /**
     * @var string $name
     */
    public static string $name = "Crawler";

    /**
     * @var string $description
     */
    public static string $description = "Update mirror.";

    /**
     * @return int
     */
    public static function run(): int
    {
        $config = Application::$app->config;

        if (file_exists($config->files["lock"])) {
            throw new RuntimeException("Lock file \"" . $config->files["lock"] . "\" exists.");
        }

        touch($config->files["lock"]);
        register_shutdown_function(function () use ($config) {
            unlink($config->files["lock"]);
        });

        $expiredFileManager = new ExpiredFileManager($config->databases["expired"], $config->service["expire_minutes"]);

        $downloader = new Downloader;
        $downloader->config = $config;
        $downloader->queue = new SplQueue;
        $downloader->expiredFileManager = $expiredFileManager;
        $downloader->multiCurl = new MultiCurl;
        $downloader->multiCurl->setTimeout(-1);

        for ($connection = 0; $connection < $config->service["max_connections"]; ++$connection) {
            $request = new Request;
            $request->setOption("encoding", "gzip");
            $request->setOption("userAgent", "https://github.com/hirak/packagist-crawler");
            $downloader->queue->enqueue($request);
        }

        $downloader->expiredFileManager->clear();

        $config->retry = true;
        do {
            $config->retry = false;
            $providers = $downloader->providers();
            $packages = $downloader->packages();
        } while ($config->retry);

        return Flusher::run();
    }
}
