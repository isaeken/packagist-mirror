<?php


namespace IsaEken\PackagistMirror;


class Application
{
    /**
     * @var Application $app
     */
    public static Application $app;

    /**
     * @var Config $config
     */
    public Config $config;

    /**
     * @var Router $router
     */
    public Router $router;

    /**
     * Application constructor.
     */
    public function __construct()
    {
        static::$app = $this;
    }

    /**
     * @return int
     */
    public function run(): int
    {
        if (php_sapi_name() !== "cli") {
            Router::$routes = [
                "default" => "\\IsaEken\\PackagistMirror\\Controllers\\Web",
            ];
        }

        return $this->router->route();
    }
}
