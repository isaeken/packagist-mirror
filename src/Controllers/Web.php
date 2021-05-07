<?php


namespace IsaEken\PackagistMirror\Controllers;


use IsaEken\PackagistMirror\Router;

class Web extends Controller
{
    /**
     * @var string $name
     */
    public static string $name = "Web";

    /**
     * @var string $description
     */
    public static string $description = "Web Home Controller.";

    /**
     * @return int
     */
    public static function run(): int
    {
        print ":)";
        return 0;
    }
}
