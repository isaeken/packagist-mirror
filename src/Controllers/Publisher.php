<?php


namespace IsaEken\PackagistMirror\Controllers;


use IsaEken\PackagistMirror\Application;
use IsaEken\PackagistMirror\Router;
use PDO;

class Publisher extends Controller
{
    /**
     * @var string $name
     */
    public static string $name = "Publisher";

    /**
     * @var string $description
     */
    public static string $description = "Publish mirror.";

    /**
     * @return int
     */
    public static function run(): int
    {
        $config = Application::$app->config;

        $base_path = $config->directories["cache"];
        $target_path = $config->directories["public"];

        print "Publishing...\r\n";

        $packages_json = json_decode(file_get_contents($base_path . "/packages.json"));

        if (file_exists("optimize.db")) {
            unlink("optimize.db");
        }

        $pdo = new PDO("sqlite:optimize.db", null, null, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec("CREATE TABLE IF NOT EXISTS providers (file TEXT, hash TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS packages (provider TEXT, file TEXT, hash TEXT)");
        $pdo->beginTransaction();

        $size = 0;

        foreach ($packages_json->{"provider-includes"} as $provider_path => $provider_info) {
            $provider_json = json_decode(file_get_contents($base_path . "/" . str_replace("%hash%", $provider_info->sha256, $provider_path)));

            foreach ($provider_json->providers as $package_name => $package_info) {
                $package_json = json_decode(file_get_contents($base_path . "/p/$package_name\${$package_info->sha256}.json"), true);

                foreach ($package_json["packages"] as $version_name => $info) {
                    if ($version_name !== $package_name) {
                        print "\"$version_name\" is unneeded but \"$package_name\" is included.\r\n";
                        $size += strlen(json_encode($info));
                        unset($package_json["packages"][$version_name]);
                    }
                }

                if (empty($package_json["packages"])) {
                    print "\"$package_name\" contains no package information. Skipped.\r\n";
                    continue;
                }

                // recreate package.json
                $package_str = json_encode($package_json, JSON_UNESCAPED_SLASHES);
                $package_hash = hash("sha256", $package_str);

                // export as new file
                $path = $target_path . "/p/{$package_name}\${$package_hash}.json";
                $dir = dirname($path);

                if (! file_exists($dir) && ! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($path, $package_str);

                // update hash value in database
                $stmt = $pdo->prepare("INSERT INTO packages (provider, file, hash) VALUES (:provider, :file, :hash)");
                $stmt->bindValue(":provider", $provider_path);
                $stmt->bindValue(":file", $package_name);
                $stmt->bindValue(":hash", $package_hash);
                $stmt->execute();
            }

            $stmt = $pdo->prepare("SELECT file, hash FROM packages WHERE provider = :provider");
            $stmt->bindValue(":provider", $provider_path);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);

            $new_packages = [];
            foreach ($stmt as $row) {
                $new_packages[$row["file"]] = ["sha256" => $row["hash"]];
            }

            $provider_json = ["providers" => $new_packages];
            $provider_string = json_encode($provider_json, JSON_UNESCAPED_SLASHES);
            $provider_hash = hash("sha256", $provider_string);

            $path = $target_path . "/" . str_replace("%hash%", $provider_hash, $provider_path);
            $directory = dirname($path);

            if (! file_exists($directory) && ! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($directory, $provider_string);

            $stmt = $pdo->prepare("INSERT INTO providers (file, hash) VALUES (:file, :hash)");
            $stmt->bindValue(":file", $provider_path);
            $stmt->bindValue(":hash", $provider_hash);
            $stmt->execute();
        }

        $pdo->commit();

        $stmt = $pdo->query("SELECT file, hash FROM providers");
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $new_providers = [];

        foreach ($stmt as $row) {
            $new_providers[$row["file"]] = ["sha256" => $row["hash"]];
        }

        $packages_json->{"provider-includes"} = $new_providers;
        $packages_string = json_encode($packages_json, JSON_UNESCAPED_SLASHES);

        $path = $target_path . "/packages.json";
        file_put_contents($path, $packages_string);

        print $size . " bytes in total were unnecessary and removed.\r\n";
        print "Writing index file...\r\n";

        $autoloader = realpath(__DIR__ . "/../../vendor/autoload.php");
        $index = <<<EOF
<?php

use IsaEken\PackagistMirror\Application;
use IsaEken\PackagistMirror\Config;
use IsaEken\PackagistMirror\Router;

require_once "$autoloader";

\$app = new Application;
\$app->config = new Config;
\$app->router = new Router(explode("/", \$_SERVER["REQUEST_URI"]));
return \$app->run();
EOF;
        file_put_contents($target_path . "/index.php", $index);
        print "Published your mirror.";
        return 0;
    }
}
