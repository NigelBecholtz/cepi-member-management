<?php

namespace Cepi\Utils;

use Cepi\Models\ApiKey;

class ApiKeyValidator {
    private $apiKeyModel;

    public function __construct() {
        $this->apiKeyModel = new ApiKey();
    }

    /**
     * Validate an API key
     * Returns key data if valid, false if invalid
     */
    public function validate($apiKey) {
        return $this->apiKeyModel->validate($apiKey);
    }

    /**
     * Get API key from request
     * Checks multiple sources: Authorization header, X-API-Key header, query parameter, POST body
     */
    public function getApiKeyFromRequest() {
        // Check Authorization header (Bearer token)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        // Check X-API-Key header
        $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if (!empty($apiKeyHeader)) {
            return trim($apiKeyHeader);
        }

        // Check query parameter (GET requests)
        if (isset($_GET['api_key'])) {
            return trim($_GET['api_key']);
        }

        // Check POST body
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_key'])) {
            return trim($_POST['api_key']);
        }

        // Check JSON POST body
        if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_SERVER['CONTENT_TYPE']) &&
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input'), true);
            if ($json && isset($json['api_key'])) {
                return trim($json['api_key']);
            }
        }

        return null;
    }

    /**
     * Validate API key from request
     * Returns key data if valid, false if invalid or missing
     */
    public function validateFromRequest() {
        $apiKey = $this->getApiKeyFromRequest();

        if (!$apiKey) {
            return false;
        }

        return $this->validate($apiKey);
    }

    /**
     * Get the ApiKey model instance
     */
    public function getApiKeyModel() {
        return $this->apiKeyModel;
    }
}

