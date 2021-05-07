<?php


namespace IsaEken\PackagistMirror\Request;


use RuntimeException;

class CurlException extends RuntimeException
{
    /**
     * @var Request $request
     */
    private Request $request;

    /**
     * @param Request $request
     * @return CurlException
     */
    public function setRequest(Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return mixed
     */
    public function getResponse(): mixed
    {
        return $this->request->getResponse();
    }
}
