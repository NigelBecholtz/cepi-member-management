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

### Error Response (Missing/Invalid API Key)

**HTTP Status:** `401 Unauthorized`

```json
{
  "error": "Valid API key required",
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

### Error Response (Missing Email)

**HTTP Status:** `400 Bad Request`

```json
{
  "error": "Email address is required.",
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

### Error Response (Invalid Content Type)

**HTTP Status:** `400 Bad Request`

```json
{
  "error": "Invalid content type. Use application/json for POST requests.",
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

### Error Response (Invalid JSON)

**HTTP Status:** `400 Bad Request`

```json
{
  "error": "Invalid JSON format.",
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

### Error Response (Method Not Allowed)

**HTTP Status:** `405 Method Not Allowed`

```json
{
  "error": "Method not allowed. Use GET or POST.",
  "found": false,
  "mm_cepi": false,
  "organisation_id": null,
  "organisation_name": null
}
```

### Error Response (Database Error)

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

**Note:** The examples below are simplified. See the "Examples" section below for complete code examples with API key authentication.

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

**Note:** Rate limiting is currently not implemented. This feature may be added in a future update to prevent API abuse.

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

## CORS (Cross-Origin Resource Sharing)

The API supports CORS for cross-origin requests:

- **Allowed Origins:** Currently allows all origins (`*`) in production. Development origins (localhost) are whitelisted.
- **Allowed Methods:** `GET`, `POST`, `OPTIONS`
- **Allowed Headers:** `Content-Type`, `Authorization`, `X-API-Key`

**Note:** In production, you should configure specific allowed origins in the API endpoint for better security.

## Notes

- Email addresses are case-insensitive (automatically converted to lowercase)
- Only active members are checked (`is_active = TRUE`)
- The API uses email hashing for privacy and security
- All API calls are logged in the activity logs with API key information
- API keys are hashed and cannot be recovered once created
- API key usage is tracked (last_used_at timestamp is updated on each request)

