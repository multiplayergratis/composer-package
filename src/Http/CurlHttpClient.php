<?php

declare(strict_types=1);

namespace MultiplayerGratis\Http;

use MultiplayerGratis\Exception\TransportException;

final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly int $timeout = 15,
        private readonly int $connectTimeout = 5,
    ) {
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('cURL konnte nicht initialisiert werden.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($handle);
        if ($result === false) {
            $error = curl_error($handle);
            throw new TransportException('HTTP-Anfrage fehlgeschlagen: ' . $error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new Response($status, (string) $result, $responseHeaders);
    }
}
