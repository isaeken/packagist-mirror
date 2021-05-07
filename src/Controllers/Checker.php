<?php


namespace IsaEken\PackagistMirror\Controllers;


use IsaEken\PackagistMirror\Application;
use IsaEken\PackagistMirror\Router;
use ProgressBar\Manager;

class Checker extends Controller
{
    /**
     * @var string $name
     */
    public static string $name = "Checker";

    /**
     * @var string $description
     */
    public static string $description = "Check mirror health.";

    /**
     * @return int
     */
    public static function run(): int
    {
        $config = Application::$app->config;
        $cache_directory = $config->directories["cache"];
        $errors = [];

        if (! file_exists($cache_directory . "/packages.json")) {
            print "Errors: \n" . "\"" . $cache_directory . "/packages.json" . "\" not found.";
            return 1;
        }

        $package_json = json_decode(file_get_contents($cache_directory . "/packages.json"));

        $provider_counter = 1;
        $provider_count = count((array) $package_json->{"provider-includes"});

        foreach ($package_json->{"provider-includes"} as $tpl => $provider) {
            $provider_json = str_replace("%hash%", $provider->sha256, $tpl);
            $packages = json_decode(file_get_contents($cache_directory . "/" . $provider_json));

            $progress_bar = new Manager(0, count((array) $packages->providers));
            $progress_bar->setFormat("      - Package: %current%/%max% [%bar%] %percent%%");

            print "   - Check Provider {$provider_counter}/{$provider_count}:\n";

            foreach ($packages->providers as $tpl2 => $sha) {
                if (! file_exists($file = $cache_directory . "/p/$tpl2\$$sha->sha256.json")) {
                    $errors[] = "   - $tpl\t$tpl2 file not exists\n";
                }
                else if ($sha->sha256 !== hash_file("sha256", $file)) {
                    unlink($file);
                    $errors[] = "   - $tpl\t$tpl2\tsha256 not match: {$sha->sha256}\n";
                }

                $progress_bar->advance();
            }

            ++$provider_counter;
        }

        if (count($errors)) {
            print "Errors: \n" . implode("", $errors);
            return 1;
        }

        print "No errors found.";
        return 0;
    }
}
