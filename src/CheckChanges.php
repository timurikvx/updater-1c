<?php

namespace Timurikvx\Update1c;

final class CheckChanges
{
    private string $url;

    private string $user;

    private string $password;

    private string $method;

    public function __construct(string $url, string $method, string $user, string $password)
    {
        $this->url = $url;
        $this->user = $user;
        $this->method = $method;
        $this->password = $password;
    }

    public function check(): bool
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.base64_encode($this->user . ':' . $this->password),
        ];
        $options = [
            'http'=>[
                'method'=>$this->method,
                'header'=>implode("\r\n", $headers),
            ]
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($this->url, false, $context);
        $data = json_decode($result, true);
        if(!key_exists('changes', $data)){
            return false;
        }
        return $data['changes'];

    }
}