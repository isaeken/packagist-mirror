<?php


namespace IsaEken\PackagistMirror;


use IsaEken\PackagistMirror\Request\MultiCurl;
use IsaEken\PackagistMirror\Request\Request;
use ProgressBar\Manager;
use RuntimeException;
use SplQueue;

class Downloader
{
    /**
     * @var array $providers
     */
    private static array $providers;

    /**
     * @var Config $config
     */
    public Config $config;

    /**
     * @var ExpiredFileManager $expiredFileManager
     */
    public ExpiredFileManager $expiredFileManager;

    /**
     * @var SplQueue $queue
     */
    public SplQueue $queue;

    /**
     * @var MultiCurl $multiCurl
     */
    public MultiCurl $multiCurl;

    /**
     * Download providers.
     *
     * @return array
     */
    public function providers(): array
    {
        if (isset(static::$providers)) {
            return static::$providers;
        }

        $cache_directory = $this->config->directories["cache"];
        $packages_cache = $cache_directory . "/packages.json";

        $request = new Request($this->config->urls["packagist"] . "/packages.json");
        $request->setOption("encoding", "gzip");
        $response = $request->send();

        if (200 === $response->getStatusCode()) {
            $packages = json_decode($response->getBody());
            foreach (explode(" ", "notify notify-batch search") as $k) {
                if (str_starts_with($packages->$k, "/")) {
                    $packages->$k = "https://packagist.org". $packages->$k;
                }
            }

            file_put_contents($packages_cache . ".new", json_encode($packages));
        }
        else {
            // no changes
            copy($packages_cache, $packages_cache . ".new");
            $packages = json_decode(file_get_contents($packages_cache));
        }

        if (empty($packages->{"provider-includes"})) {
            throw new RuntimeException("packages.json schema changed?");
        }

        $providers = [];

        $numberOfProviders = count((array) $packages->{"provider-includes"});
        $progressBar = new Manager(0, $numberOfProviders);
        $progressBar->setFormat("Downloading Providers: %current%/%max% [%bar%] %percent%%");

        foreach ($packages->{"provider-includes"} as $tpl => $version) {
            $file_url = str_replace("%hash%", $version->sha256, $tpl);
            $cache_name = $cache_directory . "/" . $file_url;
            $providers[] = $cache_name;

            if (! file_exists($cache_name)) {
                $request->setOption("url", $this->config->urls["packagist"] . "/" . $file_url);
                $response = $request->send();

                if (200 === $response->getStatusCode()) {
                    $old_cache = $cache_directory . "/" . str_replace("%hash%.json", "*", $tpl);

                    if ($glob = glob($old_cache)) {
                        foreach ($glob as $old) {
                            $this->expiredFileManager->add($old, time());
                        }
                    }

                    if (! file_exists(dirname($cache_name))) {
                        mkdir(dirname($cache_name), 0777, true);
                    }

                    file_put_contents($cache_name, $response->getBody());

                    if ($this->config->service["generate_gz"]) {
                        file_put_contents($cache_name . ".gz", gzencode($response->getBody()));
                    }
                }
                else {
                    $this->config->retry = true;
                }
            }

            $progressBar->advance();
        }

        static::$providers = $providers;
        return $providers;
    }

    /**
     * @return array
     */
    public function packages(): array
    {
        $cache_directory = $this->config->directories["cache"];
        $provider_count = count($this->providers());
        $urls = [];
        $index = 1;

        foreach ($this->providers() as $provider_json) {
            $list = json_decode(file_get_contents($provider_json));
            if (! $list || empty($list->providers)) {
                continue;
            }

            $progress_bar = new Manager(0, count((array) $list->providers));

            print "   - Provider {$index}/{$provider_count}:\r\n";
            $progress_bar->setFormat("      - Package: %current%/%max% [%bar%] %percent%%");

            $sum = 0;
            foreach ($list->providers as $package_name => $provider) {
                $progress_bar->advance();
                ++$sum;
                $url = $this->config->urls["packagist"] . "/p/" . $package_name . "\$" . $provider->sha256 . ".json";
                $cache_file = $cache_directory . "/" . str_replace($this->config->urls["packagist"] . "/", "", $url);
                if (file_exists($cache_file)) {
                    continue;
                }

                /** @var Request $request */
                $request = $this->queue->dequeue();
                $request->packageName = $package_name;
                $request->sha256 = $provider->sha256;
                $request->setOption("url", $url);

                $this->multiCurl->attach($request);
                $this->multiCurl->start();

                if (count($this->queue)) {
                    continue;
                }

                do {
                    $requests = $this->multiCurl->getFinishedResponses();
                } while (0 === count($requests));

                foreach ($requests as $request) {
                    $response = $request->getResponse();
                    $this->queue->enqueue($request);

                    if (200 !== $response->getStatusCode() || $request->sha256 !== hash("sha256", $response)) {
                        error_log($response->getStatusCode() . "\t" . $response->getUrl());
                        $this->config->retry = true;
                        continue;
                    }

                    $cache_file = $cache_directory . "/" . str_replace($this->config->urls["packagist"] . "/", "", $response->getUrl());
                    $cache_file_2 = $cache_directory . "/p/" . $request->packageName . ".json";

                    $urls[] = $this->config->service["url"] . "/p/" . $request->packageName . ".json";

                    if ($glob = glob("{$cache_directory}/p/$request->packageName\$*")) {
                        foreach ($glob as $old) {
                            $this->expiredFileManager->add($old, time());
                        }
                    }

                    if (! file_exists(dirname($cache_file))) {
                        mkdir(dirname($cache_file), 0777, true);
                    }

                    file_put_contents($cache_file, $response->getBody());
                    file_put_contents($cache_file_2, $response->getBody());

                    if ($this->config->service["generate_gz"]) {
                        $gz = gzencode($response->getBody());
                        file_put_contents($cache_file . ".gz", $gz);
                        file_put_contents($cache_file_2 . ".gz", $gz);
                    }
                }
            }

            ++$i;
        }

        if (0 === count($this->multiCurl)) {
            return [];
        }

        $this->multiCurl->waitResponse();

        $progress_bar = new Manager(0, count($this->multiCurl));
        $progress_bar->setFormat("   - Remained packages: %current%/%max% [%bar%] %percent%%");

        foreach ($this->multiCurl as $request) {
            $response = $request->getResponse();

            if (200 !== $response->getStatusCode() || $request->sha256 !== hash("sha256", $response)) {
                error_log($response->getStatusCode() . "\t" . $response->getUrl());
                $this->config->retry = true;
                continue;
            }

            $cache_file = $cache_directory . "/" . str_replace($this->config->urls["packagist"] . "/", "", $response->getUrl());
            $cache_file_2 = $cache_directory . "/p/" . $request->packageName . ".json";
            $urls[] = $this->config->service["url"] . "/p/" . $request->packageName . ".json";

            if ($glob = glob("{$cache_directory}/p/$request->packageName\$*")) {
                foreach ($glob as $old) {
                    $this->expiredFileManager->add($old, time());
                }
            }

            if (! file_exists(dirname($cache_file))) {
                mkdir(dirname($cache_file), 0777, true);
            }

            file_put_contents($cache_file, $response->getBody());

            if ($this->config->service["generate_gz"]) {
                $gz = gzencode($response->getBody());
                file_put_contents($cache_file . ".gz", $gz);
                file_put_contents($cache_file_2 . ".gz", $gz);
            }

            $progress_bar->advance();
        }

        return $urls;
    }
}
