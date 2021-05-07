<?php


namespace IsaEken\PackagistMirror;


class Router
{
    /**
     * @var array $arguments
     */
    public array $arguments = [];

    /**
     * @var array $options
     */
    public array $options = [];

    /**
     * @var array|string[] $routes
     */
    public static array $routes = [
        "default" => "\\IsaEken\\PackagistMirror\\Controllers\\Help",
        "help" => "\\IsaEken\\PackagistMirror\\Controllers\\Help",
        "crawl" => "\\IsaEken\\PackagistMirror\\Controllers\\Crawler",
        "check" => "\\IsaEken\\PackagistMirror\\Controllers\\Checker",
        "publish" => "\\IsaEken\\PackagistMirror\\Controllers\\Publisher",
        "flush" => "\\IsaEken\\PackagistMirror\\Controllers\\Flusher",
        "serve" => "\\IsaEken\\PackagistMirror\\Controllers\\Server",
    ];

    /**
     * Router constructor.
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--")) {
                $this->options[] = $arg;
            }
            else {
                $key = $arg;
                $value = null;

                if (str_contains($arg, "=")) {
                    $explode = explode("=", $arg);
                    $key = $explode[0];
                    $value = $explode[1];
                }

                $this->arguments[$key] = $value;
            }
        }

    }

    /**
     * @return int
     */
    public function route(): int
    {
        foreach (static::$routes as $route => $controller) {
            if ($route === "default") {
                continue;
            }

            if (array_key_exists($route, $this->arguments)) {
                return (new static::$routes[$route]($this->arguments, $this->options))::run();
            }
        }

        return (new static::$routes["default"]($this->arguments, $this->options))::run();
    }
}
