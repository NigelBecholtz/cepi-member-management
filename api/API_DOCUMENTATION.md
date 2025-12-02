# CEPI Member Check API Documentation

## Endpoint

**URL:** `/api/check-member.php`

**Methods:** `GET`, `POST`, `OPTIONS`

## Description

Check if an email address exists in the database and retrieve the member's MMCEPI status and organisation information.

## Authentication

**API Key Required:** All requests must include a valid API key.

### API Key Methods

You can provide your API key using one of the following methods:

#### 1. Authorization Header (Recommended)
```http
Authorization: Bearer YOUR_API_KEY_HERE
```

#### 2. X-API-Key Header
```http
X-API-Key: YOUR_API_KEY_HERE
```

#### 3. Query Parameter (GET requests only)
```
GET /api/check-member.php?email=user@example.com&api_key=YOUR_API_KEY_HERE
```

#### 4. Request Body (POST requests only)
```json
POST /api/check-member.php
{
  "email": "user@example.com",
  "api_key": "YOUR_API_KEY_HERE"
}
```

**Note:** API keys are managed through the admin dashboard. Contact your administrator to obtain an API key.

## Request

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `email` | string | Yes | Email address to check |
| `api_key` | string | Yes | Your API key (see Authentication section) |

### GET Request Example

```
GET /api/check-member.php?email=user@example.com
Authorization: Bearer YOUR_API_KEY_HERE
```

Or with query parameter:

```
GET /api/check-member.php?email=user@example.com&api_key=YOUR_API_KEY_HERE
```

### POST Request Example

```json
POST /api/check-member.php
Authorization: Bearer YOUR_API_KEY_HERE
Content-Type: application/json

{
  "email": "user@example.com"
}
```

Or with API key in body:

```json
POST /api/check-member.php
Content-Type: application/json

{
  "email": "user@example.com",
  "api_key": "YOUR_API_KEY_HERE"
}
```

## Response

### Success Response (Email Found)

**HTTP Status:** `200 OK`

```json
{
  "found": true,
  "mm_cepi": true,
  "organisation_id": 1,
  "organisation_name": "Example Organisation"
}
```

### Success Response (Email Not Found)

**HTTP Status:** `200 OK`

```json
{
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

### Error Response (Missing API Key)

**HTTP Status:** `401 Unauthorized`

```json
{
  "error": "API key required",
  "message": "Please provide a valid API key. See documentation for authentication methods.",
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

### Error Response (Invalid API Key)

**HTTP Status:** `401 Unauthorized`

```json
{
  "error": "Invalid API key",
  "message": "The provided API key is invalid or expired",
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

### Error Response (Invalid Email)

**HTTP Status:** `400 Bad Request`

```json
{
  "error": "Invalid email address",
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

### Error Response (Server Error)

**HTTP Status:** `500 Internal Server Error`

```json
{
  "error": "Database error",
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

### Error Response (Rate Limit Exceeded)

**HTTP Status:** `429 Too Many Requests`

```json
{
  "error": "Rate limit exceeded",
  "message": "Too many requests. Please try again later.",
  "retry_after": 45,
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

## Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `found` | boolean | Whether the email was found in the database |
| `mm_cepi` | boolean | MMCEPI status (only relevant if `found` is `true`) |
| `organisation_id` | integer\|null | ID of the organisation the member belongs to |
| `organisation_name` | string\|null | Name of the organisation the member belongs to |
| `error` | string | Error message (only present on errors) |

## Examples

### JavaScript (Browser - Fetch API)

```javascript
const apiKey = 'YOUR_API_KEY_HERE';
const email = 'user@example.com';

fetch(`https://mmcepi.optimasit.com/api/check-member.php?email=${encodeURIComponent(email)}`, {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${apiKey}`,
    'Accept': 'application/json'
  }
})
.then(response => response.json())
.then(data => {
  if (data.found) {
    console.log('Member found:', data.organisation_name);
    console.log('MMCEPI status:', data.mm_cepi);
  } else {
    console.log('Member not found');
  }
})
.catch(error => {
  console.error('API Error:', error);
});
```

### JavaScript (Node.js - Axios)

```javascript
const axios = require('axios');

const apiKey = 'YOUR_API_KEY_HERE';
const email = 'user@example.com';

axios.get('https://mmcepi.optimasit.com/api/check-member.php', {
  params: { email: email },
  headers: {
    'Authorization': `Bearer ${apiKey}`,
    'Accept': 'application/json'
  }
})
.then(response => {
  const data = response.data;
  if (data.found) {
    console.log('Member found:', data.organisation_name);
  } else {
    console.log('Member not found');
  }
})
.catch(error => {
  console.error('API Error:', error.response?.data || error.message);
});
```

### PHP

```php
<?php

$apiKey = 'YOUR_API_KEY_HERE';
$email = 'user@example.com';

$url = 'https://mmcepi.optimasit.com/api/check-member.php?email=' . urlencode($email);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data['found']) {
        echo "Member found: " . $data['organisation_name'] . "\n";
        echo "MMCEPI status: " . ($data['mm_cepi'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "Member not found\n";
    }
} else {
    echo "API Error: HTTP $httpCode\n";
    $error = json_decode($response, true);
    echo "Message: " . ($error['error'] ?? 'Unknown error') . "\n";
}
?>
```

### Python

```python
import requests

api_key = 'YOUR_API_KEY_HERE'
email = 'user@example.com'

url = 'https://mmcepi.optimasit.com/api/check-member.php'
headers = {
    'Authorization': f'Bearer {api_key}',
    'Accept': 'application/json'
}
params = {'email': email}

try:
    response = requests.get(url, params=params, headers=headers)
    response.raise_for_status()
    
    data = response.json()
    if data['found']:
        print(f"Member found: {data['organisation_name']}")
        print(f"MMCEPI status: {'Yes' if data['mm_cepi'] else 'No'}")
    else:
        print("Member not found")
        
except requests.exceptions.HTTPError as e:
    print(f"API Error: {e}")
    if e.response:
        error_data = e.response.json()
        print(f"Message: {error_data.get('error', 'Unknown error')}")
```

### cURL (Command Line)

```bash
# GET request with API key in Authorization header (recommended)
curl -H "Authorization: Bearer YOUR_API_KEY_HERE" \
     -H "User-Agent: Mozilla/5.0" \
     "https://mmcepi.optimasit.com/api/check-member.php?email=user@example.com"

# GET request with API key in query parameter
curl "https://mmcepi.optimasit.com/api/check-member.php?email=user@example.com&api_key=YOUR_API_KEY_HERE"

# POST request with JSON body
curl -X POST \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_API_KEY_HERE" \
     -d '{"email": "user@example.com"}' \
     "https://mmcepi.optimasit.com/api/check-member.php"
```

**Note:** When using cURL, you may need to add a User-Agent header if your site is behind Cloudflare, as Cloudflare may block requests without proper browser headers.

## Technical Details

### Email Hashing

For privacy and security, email addresses are stored as hashed values in the database. The API handles this automatically:

- **Input**: You provide the plain email address (e.g., `user@example.com`)
- **Processing**: The API hashes the email using HMAC-SHA256 with a secret key
- **Database Lookup**: The hashed value is compared against the `email_lookup_hash` column
- **No decryption needed**: The deterministic hash allows direct database queries without decrypting all records

This means:
- You always send plain email addresses in your API requests
- The API automatically handles the hashing internally
- Email addresses are never stored or transmitted in plain text in the database

## Rate Limiting

The API implements rate limiting to prevent abuse:

- **60 requests per minute** per IP address
- **1000 requests per hour** per IP address
- Rate limits are tracked per IP address
- When rate limit is exceeded, you'll receive a `429 Too Many Requests` response

### Rate Limit Headers

All responses include rate limit information in headers:

- `X-RateLimit-Limit`: Maximum number of requests allowed
- `X-RateLimit-Remaining`: Number of requests remaining in current window
- `X-RateLimit-Reset`: Unix timestamp when the rate limit resets

### Handling Rate Limits

When you receive a `429` response:
- Check the `retry_after` field (in seconds) to know when to retry
- Implement exponential backoff in your client
- Consider caching results to reduce API calls

## Activity Logging

All API calls are logged in the activity logs with the following information:

- **API Key ID:** Links the call to a specific API key
- **Key Name:** Human-readable identifier for the API key used
- **Email checked:** The email address that was looked up
- **Success status:** Whether the email was found
- **IP address:** Client IP address
- **Timestamp:** When the call was made

This allows administrators to:
- Track which API keys are being used
- Monitor API usage patterns
- Debug integration issues
- Audit API access

## Notes

- Email addresses are case-insensitive (automatically converted to lowercase)
- Only active members are checked (`is_active = TRUE`)
- The API uses email hashing for privacy and security
- All API calls are logged in the activity logs with API key information
- Rate limiting is applied per IP address
- API keys are hashed and cannot be recovered once created

