# 🔧 StandingTech API Troubleshooting Guide

## ❌ Error: "Failed to send message"

If you're getting this generic error, follow these steps to identify the root cause:

---

## 🔍 Step 1: Check Laravel Logs

Look at the detailed error in your logs:

```bash
tail -f storage/logs/laravel.log
```

Look for entries like:
```
[2024-01-01 12:00:00] local.ERROR: StandingTech API error
```

This will show you:
- HTTP status code
- Full API response
- Request data sent
- Headers used

---

## 🧪 Step 2: Test Configuration

### **Check if service is configured:**

```bash
php artisan tinker
```

Then run:
```php
config('services.standingtech.api_token')
```

**Expected:** Should return your API token, NOT `null`

If it returns `null`:
```bash
# Add to .env file
STANDINGTECH_API_TOKEN=534|ya4bex01Ef8UykzsFG9NKfeYzZaORJ5tVe8Miq6x751bd377

# Clear cache
php artisan config:clear
php artisan cache:clear

# Restart server
php artisan octane:reload
```

---

## 🌐 Step 3: Test API Connection

Use the test endpoint (requires auth):

```bash
curl --location 'http://localhost:8000/api/messaging/test-connection' \
--header 'Authorization: Bearer YOUR_AUTH_TOKEN' \
--header 'Accept: application/json'
```

This will show:
- ✅ Configuration status
- ✅ API URL
- ✅ Sender ID
- ✅ Test data that would be sent

---

## 📋 Common Issues & Solutions

### **Issue 1: Invalid Phone Number Format**

**Error:** Validation failed or "Invalid recipient"

**Cause:** Phone number format is incorrect

**Solution:**
```json
✅ Correct: "9647502171212" (digits only, with country code)
❌ Wrong: "+964 750 217 1212" (has spaces and +)
❌ Wrong: "0750-217-1212" (missing country code, has dashes)
```

**Test:**
```bash
curl --location 'http://localhost:8000/api/messaging/sms/send' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "recipient": "9647502171212",
  "message": "Test",
  "lang": "en"
}'
```

---

### **Issue 2: Authentication Failed (401)**

**Error:** HTTP 401 Unauthorized

**Cause:** API token is invalid or expired

**Check token in .env:**
```env
STANDINGTECH_API_TOKEN=534|ya4bex01Ef8UykzsFG9NKfeYzZaORJ5tVe8Miq6x751bd377
```

**Verify it's loaded:**
```bash
php artisan tinker
>>> config('services.standingtech.api_token')
```

**Contact StandingTech if token is invalid:**
- Email: akam@standingtech.com
- WhatsApp: +964-750-2171212

---

### **Issue 3: Insufficient Credits (402)**

**Error:** HTTP 402 Payment Required or "Insufficient balance"

**Cause:** Your StandingTech account has run out of credits

**Solution:**
1. Check your account balance with StandingTech
2. Top up your account
3. Contact support: akam@standingtech.com

---

### **Issue 4: Rate Limit Exceeded (429)**

**Error:** HTTP 429 Too Many Requests

**Cause:** Sending too many messages too quickly

**Solution:**
```php
// Add delay between messages
use Illuminate\Support\Facades\RateLimiter;

if (RateLimiter::tooManyAttempts('send-sms:'.$userId, 10)) {
    throw new Exception('Too many attempts');
}

RateLimiter::hit('send-sms:'.$userId, 60); // 10 per minute
```

---

### **Issue 5: Invalid Message Content (400)**

**Error:** HTTP 400 Bad Request

**Possible causes:**
- Message is too long (max 1000 chars)
- Invalid characters in message
- Missing required fields

**Check request:**
```json
{
  "recipient": "9647502171212",  // ✅ Required, 10-15 digits
  "message": "Your message",      // ✅ Required, max 1000 chars
  "sender_id": "SterenApp",      // ❌ Optional, max 11 chars
  "lang": "en"                   // ❌ Optional, "en" or "ar"
}
```

---

### **Issue 6: Service Unavailable (503)**

**Error:** HTTP 503 Service Unavailable

**Cause:** StandingTech API is temporarily down

**Solution:**
1. Wait a few minutes and retry
2. Check with StandingTech support
3. Implement retry logic:

```php
use Illuminate\Support\Facades\Http;

$response = Http::retry(3, 100) // 3 retries, 100ms delay
    ->timeout(30)
    ->post($url, $data);
```

---

### **Issue 7: Timeout Error**

**Error:** "Connection timeout" or no response

**Possible causes:**
- Network issues
- Firewall blocking outbound requests
- API endpoint is slow

**Solutions:**

**Increase timeout:**
```env
STANDINGTECH_TIMEOUT=60  # Increase from 30 to 60 seconds
```

**Check network:**
```bash
# Test if you can reach the API
curl -I https://gateway.standingtech.com/api/v4/sms/send
```

**Check firewall:**
```bash
# Ensure port 443 (HTTPS) is open
telnet gateway.standingtech.com 443
```

---

## 🔬 Advanced Debugging

### **1. Enable HTTP Request Logging**

Add to `config/logging.php`:

```php
'channels' => [
    'http' => [
        'driver' => 'single',
        'path' => storage_path('logs/http.log'),
        'level' => 'debug',
    ],
],
```

Then in your service:
```php
Log::channel('http')->debug('StandingTech Request', [
    'url' => $this->apiUrl,
    'data' => $data,
    'headers' => [
        'Authorization' => 'Bearer ' . substr($this->apiToken, 0, 20) . '...'
    ]
]);
```

### **2. Test API Directly with cURL**

```bash
curl --location 'https://gateway.standingtech.com/api/v4/sms/send' \
--header 'Authorization: Bearer 534|ya4bex01Ef8UykzsFG9NKfeYzZaORJ5tVe8Miq6x751bd377' \
--header 'Content-Type: application/json' \
--header 'Accept: application/json' \
--data '{
  "recipient": "9647502171212",
  "sender_id": "SterenApp",
  "type": "sms",
  "message": "Test message",
  "lang": "en"
}' \
--verbose
```

This shows:
- Exact HTTP status code
- Response headers
- Response body
- SSL/TLS handshake details

### **3. Check SSL Certificate**

```bash
# Test SSL connection
openssl s_client -connect gateway.standingtech.com:443 -servername gateway.standingtech.com
```

If SSL fails, you might need to update CA certificates:
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install ca-certificates

# CentOS/RHEL
sudo yum update ca-certificates
```

### **4. Monitor API Response Times**

Add monitoring to your service:

```php
$startTime = microtime(true);

$response = Http::timeout($this->timeout)
    ->post($this->apiUrl, $data);

$duration = (microtime(true) - $startTime) * 1000; // Convert to ms

Log::info('API Response Time', [
    'duration_ms' => $duration,
    'status' => $response->status()
]);

if ($duration > 5000) { // > 5 seconds
    Log::warning('Slow API response', ['duration_ms' => $duration]);
}
```

---

## 📊 Monitoring & Alerts

### **Set up Laravel Logs Monitoring:**

```bash
# Watch for errors in real-time
tail -f storage/logs/laravel.log | grep -i "standingtech\|error"
```

### **Create Alert for Failed Messages:**

```php
// In your service after catching error
if ($failedCount > 10) {
    Mail::to('admin@example.com')->send(new AlertMail(
        'StandingTech: High failure rate',
        "Failed to send {$failedCount} messages in the last hour"
    ));
}
```

---

## ✅ Verification Checklist

Before contacting support, verify:

- [ ] `.env` file has `STANDINGTECH_API_TOKEN` set
- [ ] Config cache is cleared (`php artisan config:clear`)
- [ ] Server is restarted
- [ ] Phone number format is correct (digits only, 10-15 chars)
- [ ] Message is not empty and under 1000 characters
- [ ] Laravel logs show detailed error
- [ ] API token is valid (not expired)
- [ ] Network can reach `gateway.standingtech.com`
- [ ] SSL certificates are up to date
- [ ] Account has sufficient credits

---

## 📞 Getting Help

### **Laravel Logs:**
Check `storage/logs/laravel.log` for detailed errors

### **Test Endpoint:**
```bash
GET /api/messaging/test-connection (requires auth)
```

### **Status Endpoint:**
```bash
GET /api/messaging/status (requires auth)
```

### **StandingTech Support:**
- **Email:** akam@standingtech.com
- **WhatsApp:** +964-750-2171212

### **When Contacting Support, Provide:**
1. Full error message from Laravel logs
2. HTTP status code
3. Request data you're sending
4. Your API token (first 20 characters only)
5. Timestamp of the error
6. PHP version and Laravel version

---

## 🎯 Quick Fixes Summary

| Error | Quick Fix |
|-------|-----------|
| "Service not configured" | Add `STANDINGTECH_API_TOKEN` to `.env` |
| "Failed to send message" | Check Laravel logs for detailed error |
| HTTP 401 | Verify API token is correct |
| HTTP 400 | Check phone number format and message content |
| HTTP 402 | Top up account balance |
| HTTP 429 | Implement rate limiting |
| HTTP 503 | Wait and retry, or contact support |
| Timeout | Increase `STANDINGTECH_TIMEOUT` |
| SSL Error | Update CA certificates |

---

## 🔄 Quick Test Flow

```bash
# 1. Check configuration
php artisan tinker
>>> config('services.standingtech')

# 2. Clear cache
php artisan config:clear && php artisan cache:clear

# 3. Test connection (with auth token)
curl http://localhost:8000/api/messaging/test-connection \
  -H "Authorization: Bearer YOUR_TOKEN"

# 4. Send test SMS
curl http://localhost:8000/api/messaging/sms/send \
  -H "Content-Type: application/json" \
  -d '{"recipient":"9647502171212","message":"Test","lang":"en"}'

# 5. Check logs
tail -f storage/logs/laravel.log
```

---

## 💡 Need More Help?

See also:
- `STANDINGTECH_INTEGRATION.md` - Full API documentation
- `config/services.php` - Service configuration
- `app/Services/StandingTechService.php` - Service implementation

**Still stuck?** Open an issue or contact StandingTech support with the verification checklist completed.

