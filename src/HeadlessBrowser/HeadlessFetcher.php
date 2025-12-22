<?php

namespace GiveTwice\ProductInfoFetcher\HeadlessBrowser;

use GiveTwice\ProductInfoFetcher\HeadlessBrowser\Exceptions\BrowserException;
use GiveTwice\ProductInfoFetcher\HeadlessBrowser\Exceptions\NodeNotFoundException;
use InvalidArgumentException;
use Symfony\Component\Process\Process;

class HeadlessFetcher
{
    private const PROCESS_TIMEOUT_BUFFER = 10;

    private string $nodeBinary = '/usr/bin/node';

    private ?string $chromePath = null;

    private int $timeout = 30;

    private ?string $userAgent = null;

    /** @var array<string, string> */
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

    /**
     * @param  array<string, string>  $headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function fetch(string $url): HeadlessFetchResult
    {
        $this->validateUrl($url);
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

        $process->setTimeout($this->timeout + self::PROCESS_TIMEOUT_BUFFER);
        $process->run();

        $output = json_decode($process->getOutput(), true);

        if ($output === null || ! is_array($output)) {
            throw new BrowserException(
                'Invalid JSON response from headless browser: '.$process->getOutput()
            );
        }

        if (! $process->isSuccessful() || ! ($output['success'] ?? false)) {
            $errorMessage = $output['error'] ?? $process->getErrorOutput();

            if (isset($output['html'], $output['statusCode'])) {
                throw BrowserException::blocked(
                    $errorMessage,
                    (int) $output['statusCode'],
                    $output['html'],
                    $output['headers'] ?? [],
                    $output['finalUrl'] ?? null
                );
            }

            throw new BrowserException($errorMessage);
        }

        return new HeadlessFetchResult(
            html: $output['html'],
            statusCode: $output['statusCode'],
            finalUrl: $output['finalUrl'],
        );
    }

    private function validateUrl(string $url): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL provided: {$url}");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only HTTP(S) URLs are allowed');
        }
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
