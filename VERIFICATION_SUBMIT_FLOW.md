# `/api/verification/submit` - Complete Flow Analysis

## Route Definition
**File**: `routes/api.php` (lines 390-402)

```php
Route::prefix('verification')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/submit', [DealerVerificationController::class, 'submit']);
    });
});
```

**Full URL**: `POST /api/verification/submit`  
**Middleware**: `auth:sanctum` (requires authentication token)

---

## Step-by-Step Execution Flow

### Step 1: Request Hits Route
- **URL**: Must be exactly `/api/verification/submit` (with `/api` prefix)
- **Method**: Must be `POST`
- **Headers Required**:
  - `Authorization: Bearer {token}` (for Sanctum authentication)
  - `Content-Type: multipart/form-data` (if uploading files)
  - `Accept: application/json` (recommended)

### Step 2: Middleware Check (`auth:sanctum`)
- Checks if request has valid Sanctum token
- If **NO TOKEN** or **INVALID TOKEN**:
  - Throws `AuthenticationException`
  - Should return JSON 401 (we added exception handler for this)
  - **If still getting HTML**: Request might not be reaching Laravel API routes

### Step 3: Controller Method
**File**: `app/Http/Controllers/Api/Dealer/DealerVerificationController.php` (line 32)

```php
public function submit(DealerVerificationRequest $request)
{
    try {
        $data = $request->validated();  // ← Validation happens here
        $user = $request->user();        // ← Gets authenticated user
        
        $result = $this->dealerVerificationService->submitVerification($data, $user);
        
        return $this->successResponse($result, $message, 201);
    } catch (Exception $e) {
        return $this->errorResponse('Failed to save dealer application: ' . $e->getMessage(), 500);
    }
}
```

### Step 4: Request Validation (`DealerVerificationRequest`)
**File**: `app/Http/Requests/Dealer/DealerVerificationRequest.php`

**Authorization Check** (line 13-16):
```php
public function authorize(): bool
{
    return auth()->check();  // ← Returns false if user not authenticated
}
```
- If `authorize()` returns `false`:
  - Laravel throws `AuthorizationException`
  - Should return JSON 403 (we added exception handler for this)

**Validation Rules** (line 23-39):
```php
'partner_id' => 'required|integer',
'seller_type' => 'required|string|in:authorized,independent',
'dealership_company_name' => 'required|string|max:255',
'owner_manager_name' => 'required|string|max:255',
'phone_number' => 'nullable|string|max:20',
'email' => 'required|email|max:255',
'city_address' => 'required|string|max:500',
'subscription_plan_id' => 'required|exists:subscription_plans,id',
'brand_id' => 'nullable|integer|exists:car_brands,id',
'automotive_brand_represented' => 'nullable|string|max:255',
'business_license_number' => 'nullable|string|max:255',
'vehicles_in_stock' => 'nullable|numeric|min:0|max:9999',
'years_in_business' => 'nullable|numeric|min:0|max:100',
'authorization_letter' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
'business_license' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
```

- If validation fails:
  - Laravel returns JSON 422 with validation errors
  - **If getting HTML here**: Something is wrong with exception handling

### Step 5: Service Method (`DealerVerificationService::submitVerification`)
**File**: `app/Services/DealerVerificationService.php` (line 39)

**What it does**:
1. Normalizes data (maps `email_address` → `email`, etc.)
2. Finds user by `partner_id` or uses authenticated user
3. Checks if user is already a dealer (throws exception if yes)
4. Finds subscription plan (by ID or `odoo_id`)
5. Creates local dealer application record
6. Sets status to `application_applied`
7. Sends notification to user
8. Syncs to Odoo (if configured)
9. Returns result array

---

## Why You Might Be Getting HTML Instead of JSON

### Possible Causes:

1. **Wrong URL** ❌
   - Calling `/verification/submit` instead of `/api/verification/submit`
   - **Fix**: Ensure URL includes `/api` prefix

2. **Request Not Reaching Laravel** ❌
   - Server configuration issue (nginx/apache)
   - Route not registered
   - **Fix**: Check server logs, verify route with `php artisan route:list`

3. **Authentication Failing Silently** ❌
   - Missing or invalid token
   - Token expired
   - **Fix**: Check `Authorization: Bearer {token}` header

4. **Exception Handler Not Working** ❌
   - Exception thrown before reaching our handlers
   - **Fix**: Check Laravel logs for actual exception

5. **Content-Type Issue** ❌
   - Browser making request instead of API client
   - Missing `Accept: application/json` header
   - **Fix**: Ensure frontend sends proper headers

---

## Debugging Steps

### 1. Check Route Registration
```bash
php artisan route:list --path=verification/submit
```
Should show:
```
POST  api/verification/submit  DealerVerificationController@submit
```

### 2. Check Request Headers
Ensure frontend sends:
```
POST /api/verification/submit
Authorization: Bearer {your-token}
Content-Type: multipart/form-data
Accept: application/json
```

### 3. Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```
Look for:
- Authentication errors
- Route not found errors
- Validation errors
- Any exceptions

### 4. Test with cURL
```bash
curl -X POST http://your-domain/api/verification/submit \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: multipart/form-data" \
  -F "partner_id=123" \
  -F "seller_type=independent" \
  -F "dealership_company_name=Test" \
  -F "owner_manager_name=Test" \
  -F "email=test@example.com" \
  -F "city_address=Test" \
  -F "subscription_plan_id=1"
```

### 5. Check Server Configuration
If using nginx/apache, ensure:
- API routes are properly proxied to Laravel
- `/api/*` requests go to Laravel, not static files

---

## Expected JSON Responses

### Success (201):
```json
{
  "success": true,
  "message": "Dealer verification submitted and synced successfully",
  "data": {
    "application_id": 1,
    "synced_with_odoo": true,
    "odoo_id": "123"
  }
}
```

### Authentication Error (401):
```json
{
  "success": false,
  "message": "Unauthenticated",
  "error": "UNAUTHENTICATED"
}
```

### Validation Error (422):
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "partner_id": ["Partner ID is required."],
    "email": ["Email address is required."]
  }
}
```

### Route Not Found (404):
```json
{
  "success": false,
  "message": "Route not found",
  "error": "NOT_FOUND"
}
```

---

## If Still Getting HTML

The HTML response (Laravel welcome page) means:
1. **Request is NOT reaching the API route** - Check URL and server config
2. **Exception handler is NOT catching the exception** - Check Laravel version and exception handling
3. **Request is being handled by web routes instead** - Check route registration

**Most Likely Issue**: The request URL is wrong or the request isn't reaching Laravel's API routes at all.

