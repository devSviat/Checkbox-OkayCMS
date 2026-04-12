<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

use Okay\Core\BackendTranslations;
use Okay\Core\EntityFactory;
use Okay\Core\ServiceLocator;
use Okay\Core\Settings;
use Okay\Modules\Sviat\Checkbox\Init\Init;

/** Базовий клас для HTTP-запитів до Checkbox API */
class CheckboxApiHelper
{
    protected string $apiBaseUrl;
    protected ?string $cashierLogin;
    protected ?string $cashierPassword;
    public ?string $accessToken = null;
    protected string $licenseKey = '';
    protected string $clientName = 'OkayCMS Checkbox Integration';
    protected int $requestTimeout = 5;

    protected Settings $settings;
    protected BackendTranslations $translations;
    protected EntityFactory $entityFactory;
    public array $errors = [];

    public function __construct()
    {
        $serviceLocator = ServiceLocator::getInstance();
        $this->settings = $serviceLocator->getService(Settings::class);
        $this->entityFactory = $serviceLocator->getService(EntityFactory::class);
        $this->translations = $serviceLocator->getService(BackendTranslations::class);

        $this->apiBaseUrl = "https://api.checkbox.in.ua/api/v1/";

        $cashierLogin = $this->settings->{Init::CASHIER_LOGIN};
        $cashierPassword = $this->settings->{Init::CASHIER_PASSWORD};
        $licenseKey = $this->settings->{Init::CASHIER_LICENSE_KEY};

        $this->cashierLogin = is_string($cashierLogin) ? $cashierLogin : null;
        $this->cashierPassword = is_string($cashierPassword) ? $cashierPassword : null;
        $this->licenseKey = is_string($licenseKey) ? $licenseKey : '';
    }

    /** @return array|false */
    public function getAccessToken()
    {
        if (empty($this->cashierLogin) || empty($this->cashierPassword) || empty($this->licenseKey)) {
            return [
                'message' => $this->translations->getTranslation('sviat__checkbox__errors_empty_params')
            ];
        }
        $url = 'cashier/signin';
        $params = [
            'login' => $this->cashierLogin,
            'password' => $this->cashierPassword
        ];
        $requestParams = [
            'method' => 'POST'
        ];
        $response = $this->makeApiRequest($url, $params, $requestParams);
        $this->accessToken = $response['access_token'] ?? null;
        return $response;
    }

    protected function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Виконує HTTP-запит до API. Повертає розкодований JSON, рядок (для PDF/HTML) або false при помилці.
     * При HTTP 4xx/5xx або бізнес-помилці API заповнює $this->errors.
     *
     * @return array|string|false
     */
    protected function makeApiRequest(string $url, array $params = [], array $requestParams = [])
    {
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }

        $method = $requestParams['method'] ?? 'GET';
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        } elseif (!empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
        }
        $url = $this->apiBaseUrl . $url;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->clientName);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->requestTimeout);
        if (strpos($this->apiBaseUrl, 'https://') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = [
            "Content-Type: application/json",
            "Accept: application/json;charset=UTF-8",
        ];
        if (!empty($this->accessToken)) {
            $headers[] = "Authorization: Bearer " . $this->accessToken;
            if (!empty($this->licenseKey)) {
                $headers[] = "X-License-Key: " . $this->licenseKey;
            }
            if (!empty($this->clientName)) {
                $headers[] = "X-Client-Name: " . $this->clientName;
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $data = curl_exec($ch);
        $curlInfo = curl_getinfo($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($data === false || !empty($curlError)) {
            $this->errors['message'] = $curlError ?: 'CURL Error';
            return false;
        }

        return $this->processApiResponse($data, $url, $params, $curlInfo, $headers);
    }

    /**
     * Декодує відповідь API. PDF/HTML повертає як рядок.
     * HTTP 4xx/5xx і бізнес-помилки API записує в $this->errors.
     *
     * @param string|false $data
     * @return array|string|false
     */
    protected function processApiResponse($data, string $url, array $params = [], array $curlInfo = [], array $headers = [])
    {
        if ($data === false || $data === '') {
            return false;
        }

        $contentType = is_string($curlInfo['content_type'] ?? '') ? $curlInfo['content_type'] : '';
        if ($contentType === 'text/html; charset=utf-8'
            || $contentType === 'text/plain; charset=utf-8'
            || strpos($contentType, 'application/pdf') !== false) {
            return $data;
        }

        $response = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors['message'] = 'JSON decode error: ' . json_last_error_msg();
            return false;
        }

        $httpCode = is_numeric($curlInfo['http_code'] ?? null) ? (int)$curlInfo['http_code'] : 200;
        if ($httpCode >= 400) {
            $this->errors['message'] = $response['message'] ?? 'HTTP Error ' . $httpCode;
            $this->errors['requestData'] = [
                'url' => $url,
                'headers' => $headers,
                'curlInfo' => $curlInfo,
                'data' => $params
            ];
            $this->errors['response'] = $response;
            $this->errors['http_code'] = $httpCode;
        } elseif (isset($response['message']) && !isset($response['id']) && !isset($response['access_token'])) {
            // Бізнес-помилка API: є message, але немає маркерів успіху (id / access_token)
            $this->errors['message'] = $response['message'];
            $this->errors['requestData'] = [
                'url' => $url,
                'headers' => $headers,
                'curlInfo' => $curlInfo,
                'data' => $params
            ];
            $this->errors['response'] = $response;
        }

        return $response;
    }
}
