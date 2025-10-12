# StandingTech SMS/WhatsApp Integration

## Overview
This Laravel application integrates with StandingTech's SMS and WhatsApp gateway service for sending messages, OTP codes, and notifications.

## Configuration

Add the following environment variables to your `.env` file:

```env
# StandingTech SMS/WhatsApp Gateway Configuration
STANDINGTECH_API_URL=https://gateway.standingtech.com/api/v4/sms/send
STANDINGTECH_API_TOKEN=534|ya4bex01Ef8UykzsFG9NKfeYzZaORJ5tVe8Miq6x751bd377
STANDINGTECH_SENDER_ID=SterenApp
STANDINGTECH_TIMEOUT=30
STANDINGTECH_ENABLED=true
```

### Configuration Options

- **STANDINGTECH_API_URL**: The API endpoint URL (default: https://gateway.standingtech.com/api/v4/sms/send)
- **STANDINGTECH_API_TOKEN**: Your API token provided by StandingTech
- **STANDINGTECH_SENDER_ID**: Default sender ID for messages (default: SterenApp)
- **STANDINGTECH_TIMEOUT**: API request timeout in seconds (default: 30)
- **STANDINGTECH_ENABLED**: Enable/disable the service (default: true)

## API Endpoints

### 1. Send SMS
**POST** `/api/messaging/sms/send`

Send an SMS message to a single recipient.

**Request Body:**
```json
{
  "recipient": "9647502171212",
  "message": "Your message here",
  "sender_id": "SterenApp",
  "lang": "en"
}
```

**Response:**
```json
{
  "success": true,
  "message": "SMS sent successfully",
  "data": {
    "success": true,
    "data": { ... },
    "status_code": 200
  }
}
```

### 2. Send WhatsApp
**POST** `/api/messaging/whatsapp/send`

Send a WhatsApp message to a single recipient.

**Request Body:**
```json
{
  "recipient": "9647502171212",
  "message": "Your message here",
  "sender_id": "SterenApp",
  "lang": "en"
}
```

### 3. Send Notification
**POST** `/api/messaging/notification/send`

Send a notification via SMS or WhatsApp.

**Request Body:**
```json
{
  "recipient": "9647502171212",
  "message": "Your notification message",
  "type": "sms",
  "sender_id": "SterenApp",
  "lang": "en"
}
```

**Parameters:**
- `type`: Either "sms" or "whatsapp"

### 4. Send OTP (SMS)
**POST** `/api/messaging/otp/send`

Send an OTP code via SMS.

**Request Body:**
```json
{
  "recipient": "9647502171212",
  "otp": "123456",
  "lang": "en"
}
```

### 5. Send OTP (WhatsApp)
**POST** `/api/messaging/otp/whatsapp`

Send an OTP code via WhatsApp.

**Request Body:**
```json
{
  "recipient": "9647502171212",
  "otp": "123456",
  "lang": "en"
}
```

### 6. Send Verification Code
**POST** `/api/messaging/verification/send`

Send a verification code via SMS or WhatsApp.

**Request Body:**
```json
{
  "recipient": "9647502171212",
  "code": "ABC123",
  "type": "sms",
  "lang": "en"
}
```

### 7. Send Bulk Messages (Auth Required)
**POST** `/api/messaging/bulk/send`

Send messages to multiple recipients.

**Headers:**
```
Authorization: Bearer {your_token}
```

**Request Body:**
```json
{
  "recipients": ["9647502171212", "9647502171213", "9647502171214"],
  "message": "Your message here",
  "type": "sms",
  "sender_id": "SterenApp",
  "lang": "en"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Bulk messages processed: 3 sent, 0 failed",
  "data": {
    "results": [
      {
        "recipient": "9647502171212",
        "success": true,
        "response": { ... }
      }
    ],
    "summary": {
      "total": 3,
      "success": 3,
      "failed": 0
    }
  }
}
```

### 8. Get Service Status (Auth Required)
**GET** `/api/messaging/status`

Get the current status of the messaging service.

**Headers:**
```
Authorization: Bearer {your_token}
```

**Response:**
```json
{
  "success": true,
  "message": "Service status retrieved successfully",
  "data": {
    "configured": true,
    "api_url": "https://gateway.standingtech.com/api/v4/sms/send",
    "sender_id": "SterenApp"
  }
}
```

## Usage Examples

### Using the Service in Your Code

```php
use App\Services\StandingTechService;

class YourController extends Controller
{
    protected StandingTechService $messagingService;

    public function __construct(StandingTechService $messagingService)
    {
        $this->messagingService = $messagingService;
    }

    public function sendWelcomeSMS($phoneNumber)
    {
        try {
            $result = $this->messagingService->sendSMS(
                $phoneNumber,
                'Welcome to Steren App!',
                'SterenApp',
                'en'
            );
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendOTP($phoneNumber, $otpCode)
    {
        try {
            $result = $this->messagingService->sendOTP(
                $phoneNumber,
                $otpCode,
                'en'
            );
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

### cURL Examples

**Send SMS:**
```bash
curl --location 'http://localhost:8000/api/messaging/sms/send' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "recipient": "9647502171212",
  "message": "Hello from Steren App!",
  "lang": "en"
}'
```

**Send WhatsApp:**
```bash
curl --location 'http://localhost:8000/api/messaging/whatsapp/send' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "recipient": "9647502171212",
  "message": "Hello via WhatsApp!",
  "lang": "en"
}'
```

**Send OTP:**
```bash
curl --location 'http://localhost:8000/api/messaging/otp/send' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "recipient": "9647502171212",
  "otp": "123456",
  "lang": "en"
}'
```

## Validation Rules

### Phone Number
- Required
- Must be 10-15 digits
- No spaces, dashes, or other characters
- Should include country code (e.g., 9647502171212 for Iraq)

### Message
- Required
- Maximum 1000 characters

### Sender ID
- Optional
- Maximum 11 characters

### Language
- Optional
- Valid values: "en", "ar"
- Default: "en"

### Type
- Required for notification/bulk endpoints
- Valid values: "sms", "whatsapp"

### OTP Code
- Required for OTP endpoints
- Must be 4-6 digits

## Error Handling

The service throws `StandingTechException` for API errors:

```php
try {
    $result = $this->messagingService->sendSMS(...);
} catch (\App\Exceptions\StandingTechException $e) {
    // Handle StandingTech API errors
    Log::error('StandingTech Error: ' . $e->getMessage());
    return response()->json([
        'error' => $e->getMessage(),
        'status_code' => $e->getStatusCode()
    ], $e->getStatusCode());
} catch (\Exception $e) {
    // Handle general errors
    Log::error('General Error: ' . $e->getMessage());
    return response()->json([
        'error' => 'An unexpected error occurred'
    ], 500);
}
```

## Message Templates

The service includes built-in message templates for OTP and verification codes:

**English OTP:**
```
Your verification code is: 123456. Valid for 10 minutes.
```

**Arabic OTP:**
```
رمز التحقق الخاص بك هو: 123456. صالح لمدة 10 دقائق.
```

**English Verification:**
```
Your Steren verification code: ABC123
```

**Arabic Verification:**
```
رمز التحقق من Steren: ABC123
```

## Contact Information

For detailed API documentation or support:
- Email: akam@standingtech.com
- WhatsApp: +964-750-2171212
- Postman Documentation: Available upon request

## Security Notes

1. **Never commit your API token to version control**
2. Store the token securely in your `.env` file
3. Use environment variables for all sensitive configuration
4. Implement rate limiting for public endpoints
5. Monitor API usage and costs
6. Validate all phone numbers before sending
7. Log all messaging activities for auditing

## Testing

Run the messaging service tests:

```bash
php artisan test --filter MessagingController
```

Test the service configuration:

```bash
php artisan tinker
>>> app(\App\Services\StandingTechService::class)->getStatus();
```

## Troubleshooting

### Service Not Configured
- Check that `STANDINGTECH_API_TOKEN` is set in `.env`
- Verify the API URL is correct
- Ensure `STANDINGTECH_ENABLED=true`

### API Errors
- Verify the phone number format (digits only, with country code)
- Check that your API token is valid and not expired
- Ensure you have sufficient credits/balance
- Review the Laravel logs in `storage/logs/laravel.log`

### Timeout Errors
- Increase `STANDINGTECH_TIMEOUT` in `.env`
- Check your network connection
- Verify the API endpoint is accessible

## License

This integration is proprietary to your application. The StandingTech API service is provided by Standing Technology.

