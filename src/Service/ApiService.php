<?php

namespace App\Service;

use App\Exception\BillingUnavailableException;

class ApiService
{
    private string $route;
    private array $options;

    public function __construct(string $route, string $method, $postParams = null, $getParams = null, $headers = null)
    {
        $this->route = $_ENV['BILLING_URL'] . $route;
        $this->options = [
            CURLOPT_RETURNTRANSFER => true,
        ];
        if ($method === 'POST') {
            $this->options[CURLOPT_POST] = true;
            $this->options[CURLOPT_HTTPHEADER] = [
                'Content-Type: application/json',
            ];
            if ($postParams !== null) {
                $this->options[CURLOPT_POSTFIELDS] = json_encode($postParams, JSON_THROW_ON_ERROR);
            }
        } else {
            if ($getParams !== null) {
                $this->route .= '?' . http_build_query($getParams);
            }
        }
        if ($headers !== null) {
            $this->options[CURLOPT_HTTPHEADER] = [];
            array_push($this->options[CURLOPT_HTTPHEADER], ...$headers);
        }
    }

    public function exec()
    {
        $query = curl_init($this->route);
        curl_setopt_array($query, $this->options);
        $response = curl_exec($query);

        if ($response === false) {
            throw new BillingUnavailableException('Ошибка на стороне сервиса авторизации');
        }
        curl_close($query);
        return $response;
    }


}