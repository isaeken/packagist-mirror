<?php


namespace IsaEken\PackagistMirror\Controllers;


class Controller
{
    /**
     * @var string $name
     */
    protected static string $name = "";

    /**
     * @var string $description
     */
    protected static string $description = "";

    /**
     * @var array $arguments
     */
    protected static array $arguments = [];

    /**
     * @var array $options
     */
    protected static array $options = [];

    /**
     * @var array $_arguments
     */
    public static array $_arguments = [];

    /**
     * @var array $_options
     */
    public static array $_options = [];

    /**
     * Controller constructor.
     *
     * @param array $_arguments
     * @param array $_options
     */
    public function __construct(array $_arguments = [], array $_options = [])
    {
        static::$_arguments = $_arguments;
        static::$_options = $_options;
    }

    /**
     * @param string $key
     * @return bool
     */
    protected static function hasArgument(string $key): bool
    {
        return array_key_exists($key, static::$_arguments);
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected static function getArgument(string $key): mixed
    {
        if (static::hasArgument($key)) {
            return static::$_arguments[$key];
        }

        if (isset(static::$arguments) && array_key_exists($key, static::$arguments)) {
            return static::$arguments[$key];
        }

        return null;
    }

    /**
     * @param string $option
     * @return bool
     */
    protected static function hasOption(string $option): bool
    {
        return static::$_options[$option];
    }

    /**
     * @return int
     */
    protected static function run(): int
    {
        return 0;
    }
}
