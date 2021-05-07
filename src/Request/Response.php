<?php


namespace IsaEken\PackagistMirror\Request;


class Response
{
    /**
     * @var string $body
     */
    protected string $body = "";

    /**
     * @var string $header
     */
    protected string $header = "";

    /**
     * @var array $info
     */
    protected array $info = [];
    protected $header_cache;

    /**
     * Response constructor.
     *
     * @param string $body
     * @param array $info
     */
    public function __construct(string $body, array $info)
    {
        $this->header = substr($body, 0, $info["header_size"]);
        $this->body = substr($body, $info["header_size"]);
        $this->info = $info;
    }

    /**
     * @return string
     */
    public function getHeaderString(): string
    {
        return $this->header;
    }

    /**
     * @param string|null $label
     * @return mixed
     */
    public function getHeader(string|null $label = null): mixed
    {
        if (! $this->header_cache) {
            $header_string = rtrim($this->header);
            $header_string = str_replace(["\r\n", "\r"], "\n", $header_string);
            $header_array = explode("\n", $header_string);
            array_shift($header_array);

            $result = [];
            foreach ($header_array as $header) {
                $position = strpos($header, ":");
                $key = substr($header, 0, $position);
                $value = substr($header, $position + 1);
                $result[trim($key)] = trim($value);
            }

            $this->header_cache = $result;
        }

        if ($label !== null) {
            return $this->header_cache[$label] ?? null;
        }

        return $this->header_cache;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return (string) $this->info["url"];
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return (int) $this->info["http_code"];
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return (string) $this->info["content_type"];
    }

    /**
     * @return int
     */
    public function getContentLength(): int
    {
        return (int) $this->info["download_content_length"];
    }

    /**
     * @param string|null $label
     * @return mixed
     */
    public function getInfo(string|null $label = null): mixed
    {
        if ($label && isset($this->info[$label])) {
            return $this->info[$label];
        }

        return $this->info;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return (string) $this->body;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getBody();
    }
}
