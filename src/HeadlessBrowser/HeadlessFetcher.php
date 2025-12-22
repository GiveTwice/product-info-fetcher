<?php

namespace GiveTwice\ProductInfoFetcher\HeadlessBrowser;

use GiveTwice\ProductInfoFetcher\HeadlessBrowser\Exceptions\BrowserException;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\Exceptions\NodeNotFoundException;
use Symfony\Component\Process\Process;

class HeadlessFetcher
{
    private string $nodeBinary = '/usr/bin/node';

    private ?string $chromePath = null;

    private int $timeout = 30;

    private ?string $userAgent = null;

    private array $headers = [];

    public function setNodeBinary(string $path): self
    {
        $this->nodeBinary = $path;

        return $this;
    }

    public function setChromePath(string $path): self
    {
        $this->chromePath = $path;

        return $this;
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function fetch(string $url): HeadlessFetchResult
    {
        $this->ensureNodeExists();

        $command = [
            'url' => $url,
            'timeout' => $this->timeout * 1000,
            'userAgent' => $this->userAgent,
            'headers' => $this->headers,
            'chromePath' => $this->chromePath,
        ];

        $process = new Process([
            $this->nodeBinary,
            $this->getScriptPath(),
            json_encode($command),
        ]);

        $process->setTimeout($this->timeout + 10);
        $process->run();

        $output = json_decode($process->getOutput(), true);

        if (! $process->isSuccessful() || ! ($output['success'] ?? false)) {
            throw new BrowserException($output['error'] ?? $process->getErrorOutput());
        }

        return new HeadlessFetchResult(
            html: $output['html'],
            statusCode: $output['statusCode'],
            finalUrl: $output['finalUrl'],
        );
    }

    private function ensureNodeExists(): void
    {
        $process = new Process([$this->nodeBinary, '--version']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new NodeNotFoundException(
                "Node.js not found at '{$this->nodeBinary}'. ".
                'Install Node.js or configure path with setNodeBinary().'
            );
        }
    }

    private function getScriptPath(): string
    {
        return dirname(__DIR__, 2).'/bin/fetch-html.cjs';
    }
}
