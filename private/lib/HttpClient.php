<?php
declare(strict_types=1);
namespace Kuko;

interface HttpClient
{
    public function postForm(string $url, array $params): array;
}
