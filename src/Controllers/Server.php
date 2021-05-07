<?php


namespace IsaEken\PackagistMirror\Controllers;


use IsaEken\PackagistMirror\Application;
use IsaEken\PackagistMirror\Router;

class Server extends Controller
{
    /**
     * @var string $name
     */
    public static string $name = "Server";

    /**
     * @var string $description
     */
    public static string $description = "Start development server.";

    /**
     * @var array|string[] $arguments
     */
    public static array $arguments = [
        "host" => "127.0.0.1", "port" => 8000,
    ];

    /**
     * @return int
     */
    public static function run(): int
    {
        $host = static::getArgument("host");
        $port = static::getArgument("port");
        $schema = "http";

        print "Started development server on $schema://$host:$port/\r\n";
        chdir(Application::$app->config->directories["public"]);
        passthru(PHP_BINARY . " -S $host:$port 2>&1");

        return 0;
    }
}
