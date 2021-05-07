<?php


namespace IsaEken\PackagistMirror\Controllers;


use IsaEken\PackagistMirror\Router;

class Help extends Controller
{
    /**
     * @var string $name
     */
    public static string $name = "Help";

    /**
     * @var string $description
     */
    public static string $description = "List all commands.";

    /**
     * @return int
     */
    public static function run(): int
    {
        print "\r\n";
        print "--    Packagist Mirror Service    --";
        print "\r\n\r\n";
        print "Commands:\r\n";
        foreach (Router::$routes as $route => $controller) {
            if ($route === "default") {
                continue;
            }

            $arguments = "";
            $options = "";
            $description = "";

            if (isset($controller::$arguments)) {
                foreach ($controller::$arguments as $key => $default) {
                    $arguments .= " <$key=$default>";
                }
            }

            if (isset($controller::$options)) {
                foreach ($controller::$options as $option) {
                    $options .= " [$option]";
                }
            }

            if (isset($controller::$description)) {
                $description = $controller::$description;
            }

            print trim("> $route$arguments$options: $description");
            print "\r\n";
        }
        print "\r\n";

        return 0;
    }
}
