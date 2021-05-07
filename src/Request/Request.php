<?php


namespace IsaEken\PackagistMirror\Request;


use CurlHandle;
use InvalidArgumentException;

class Request
{
    /**
     * @var CurlHandle $curl
     */
    private CurlHandle $curl;

    /**
     * @var array $options
     */
    protected array $options = [
        "returntransfer" => true,
        "header" => true,
    ];

    /**
     * @var Response $response
     */
    protected Response $response;

    /**
     * @var CurlException $error
     */
    protected CurlException $error;

    /**
     * Request constructor.
     *
     * @param string|null $url
     * @param array $options
     */
    public function __construct(string|null $url = null, array $options = [])
    {
        if ($url !== null) {
            $this->curl = curl_init($url);
            $options["url"] = $url;
        }
        else {
            $this->curl = curl_init();
        }

        $this->options += $options;
        $this->setOptions($this->options);
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

    public function __clone(): void
    {
        $this->curl = curl_copy_handle($this->curl);
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): static
    {
        $this->options = $options + $this->options;
        curl_setopt_array($this->curl, $this->__toCurlSetopt($options));
        return $this;
    }

    /**
     * @param string $label
     * @param $value
     * @return $this
     */
    public function setOption(string $label, $value): static
    {
        $this->options[$label] = $value;
        curl_setopt($this->curl, $this->__toCurlOption($label), $value);
        return $this;
    }

    /**
     * @param string $label
     * @return mixed
     */
    public function getOption(string $label): mixed
    {
        return $this->options[$label];
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return CurlHandle
     */
    public function getHandle(): CurlHandle
    {
        return $this->curl;
    }

    /**
     * @return Response
     */
    public function send(): Response
    {
        $body = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);
        $this->response = new Response($body, $info);
        $error_no = curl_errno($this->curl);

        if (0 !== $error_no) {
            $error = new CurlException(curl_error($this->curl), $error_no);
            $error->setRequest($this);
            $this->error = $error;
            throw $error;
        }

        return $this->response;
    }

    /**
     * @param Response $response
     * @return $this
     */
    public function setResponse(Response $response): static
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @param CurlException $exception
     * @return Request
     */
    public function setError(CurlException $exception): static
    {
        $this->error = $exception;
        return $this;
    }

    /**
     * @return CurlException
     */
    public function getError(): CurlException
    {
        return $this->error;
    }

    /**
     * @param array $option_list
     * @return array
     */
    protected function __toCurlSetopt(array $option_list): array
    {
        $fixed_option_list = [];
        foreach ($option_list as $option => $value) {
            $label = $this->__toCurlOption($option);
            $fixed_option_list[$label] = $value;
        }

        return $fixed_option_list;
    }

    /**
     * @param string|int $label
     * @return mixed
     */
    protected function __toCurlOption(string|int $label): mixed
    {
        if (is_int($label)) {
            return $label;
        }

        if (is_string($label)) {
            $const = "CURLOPT_" . strtoupper($label);

            if (defined($const)) {
                $curlopt = constant($const);
            }
            else {
                throw new InvalidArgumentException("$const does not exists in CURLOPT_* constants.");
            }

            return $curlopt;
        }

        throw new InvalidArgumentException("label is invalid");
    }
}
