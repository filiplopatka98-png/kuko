<?php
declare(strict_types=1);
namespace Kuko;

interface HttpClient
{
    public function postForm(string $url, array $params): array;
}

final class CurlHttpClient implements HttpClient
{
    public function postForm(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            curl_close($ch);
            return ['success' => false, 'error-codes' => ['curl-failed']];
        }
        curl_close($ch);
        $data = json_decode((string) $body, true);
        return is_array($data) ? $data : ['success' => false, 'error-codes' => ['bad-response']];
    }
}
