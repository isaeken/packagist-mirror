<?php


namespace IsaEken\PackagistMirror\Request;


use ArrayIterator;
use Countable;
use CurlMultiHandle;
use IteratorAggregate;
use RuntimeException;

class MultiCurl implements Countable, IteratorAggregate
{
    /**
     * @var CurlMultiHandle $curl
     */
    protected CurlMultiHandle $curl;

    /**
     * @var int $timeout
     */
    protected int $timeout = -1;

    /**
     * @var array $pool
     */
    protected array $pool = [];

    /**
     * MultiCurl constructor.
     */
    public function __construct(...$requests)
    {
        $this->curl = curl_multi_init();
        foreach ($requests as $request) {
            $this->attach($request);
        }
    }

    public function __destruct()
    {
        $this->detachAll();
        curl_multi_close($this->curl);
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function attach(Request $request): static
    {
        $handle = $request->getHandle();
        $this->pool[(int) $handle] = $request;
        curl_multi_add_handle($this->curl, $handle);
        return $this;
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function detach(Request $request): static
    {
        $handle = $request->getHandle();
        unset($this->pool[(int) $handle]);
        curl_multi_remove_handle($this->curl, $handle);
        return $this;
    }

    /**
     * @return void
     */
    public function start(): void
    {
        do {
            switch (curl_multi_select($this->curl, $this->timeout)) {
                case -1:
                    usleep(10);
                    do {
                        $stat = curl_multi_exec($this->curl, $running);
                    } while ($stat === CURLM_CALL_MULTI_PERFORM);
                    continue 2;
                default:
                    break 2;
            }
        } while ($running);
    }

    /**
     * @return array
     */
    public function getFinishedResponses(): array
    {
        $requests = [];

        switch (curl_multi_select($this->curl, $this->timeout)) {
            case 0:
                throw new RuntimeException("timeout");

            case -1:
            default:
                do {
                    $stat = curl_multi_exec($this->curl, $running);
                } while ($stat === CURLM_CALL_MULTI_PERFORM);

                do {
                    if ($raised = curl_multi_info_read($this->curl, $remains)) {
                        $info = curl_getinfo($raised["handle"]);
                        $body = curl_multi_getcontent($raised["handle"]);

                        $response = new Response($body, $info);
                        $request = $this->pool[(int) $raised["handle"]];

                        $request->setResponse($response);
                        $this->detach($request);

                        if (CURLE_OK !== $raised["result"]) {
                            $error = new CurlException(curl_error($raised["handle"], $raised["result"]));
                            $request->setError($error);
                        }

                        $requests[] = $request;
                    }
                } while ($remains);
        }

        return $requests;
    }

    /**
     * @return void
     */
    public function waitResponse(): void
    {
        do {
            switch (curl_multi_select($this->curl, $this->timeout)) {
                case 0:
                    throw new RuntimeException("timeout");

                case -1:
                default:
                    do {
                        $stat = curl_multi_exec($this->curl, $running);
                    }
                    while ($stat === \CURLM_CALL_MULTI_PERFORM);

                    do {
                        if ($raised = curl_multi_info_read($this->curl, $remains)) {
                            $info = curl_getinfo($raised["handle"]);
                            $body = curl_multi_getcontent($raised["handle"]);

                            $response = new Response($body, $info);
                            $request = $this->pool[(int) $raised["handle"]];
                            $request->setResponse($response);
                        }
                    } while ($remains);
            }
        } while ($running);
    }

    /**
     * @return void
     */
    public function send(): void
    {
        $this->start();
        $this->waitResponse();
    }

    /**
     * @return $this
     */
    public function detachAll(): static
    {
        foreach ($this->pool as $request) {
            curl_multi_remove_handle($this->curl, $request->getHandle());
        }

        $this->pool = [];
        return $this;
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->pool);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->pool);
    }
}
