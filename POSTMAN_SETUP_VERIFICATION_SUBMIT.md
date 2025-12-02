# Postman Setup for `/api/verification/submit`

## Why Frontend Works But Postman Doesn't

**Frontend**: Calls `POST /verification/submit`  
**Frontend Base URL**: Likely includes `/api` (e.g., `https://api.example.com/api`)  
**Actual URL**: `POST /api/verification/submit` ✅

**Postman**: Must call `POST /api/verification/submit` directly  
**If Postman gets HTML**: Missing headers or wrong URL

---

## Postman Configuration

### 1. Request Method & URL
- **Method**: `POST`
- **URL**: `{{base_url}}/api/verification/submit`
  - Replace `{{base_url}}` with your actual base URL (e.g., `https://your-domain.com`)

### 2. Required Headers
Add these headers in Postman:

| Header Name | Header Value |
|-------------|--------------|
| `Authorization` | `Bearer YOUR_TOKEN_HERE` |
| `Accept` | `application/json` |
| `Content-Type` | `multipart/form-data` (Postman sets this automatically for form-data) |

**Important**: The `Accept: application/json` header is crucial to ensure JSON responses.

### 3. Request Body
- Select **Body** tab
- Select **form-data** (not `x-www-form-urlencoded` or `raw`)
- Add these fields:

| Key | Type | Value | Required |
|-----|------|-------|----------|
| `partner_id` | Text | `123` (user's partner_id) | ✅ Yes |
| `seller_type` | Text | `authorized` or `independent` | ✅ Yes |
| `dealership_company_name` | Text | `ABC Motors` | ✅ Yes |
| `owner_manager_name` | Text | `John Doe` | ✅ Yes |
| `phone_number` | Text | `9647501234567` | ❌ No |
| `email` | Text | `john@example.com` | ✅ Yes |
| `city_address` | Text | `Baghdad, Iraq` | ✅ Yes |
| `subscription_plan_id` | Text | `1` (must exist in database) | ✅ Yes |
| `brand_id` | Text | `1` (if authorized seller) | ❌ No |
| `automotive_brand_represented` | Text | `Toyota` (if authorized seller) | ❌ No |
| `business_license_number` | Text | `BL123456` (if authorized seller) | ❌ No |
| `vehicles_in_stock` | Text | `50` | ❌ No |
| `years_in_business` | Text | `5` | ❌ No |
| `authorization_letter` | File | Select PDF/JPG/PNG file (if authorized seller) | ❌ No |
| `business_license` | File | Select PDF/JPG/PNG file (if authorized seller) | ❌ No |

### 4. Example Complete Request

**URL**: `POST https://your-domain.com/api/verification/submit`

**Headers**:
```
Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz1234567890
Accept: application/json
```

**Body** (form-data):
```
partner_id: 123
seller_type: independent
dealership_company_name: ABC Motors
owner_manager_name: John Doe
phone_number: 9647501234567
email: john@example.com
city_address: Baghdad, Iraq
subscription_plan_id: 1
vehicles_in_stock: 50
years_in_business: 5
```

---

## Troubleshooting

### If You Get HTML Response (Laravel Welcome Page)

**Problem**: Request not reaching API route or missing headers

**Solutions**:
1. ✅ **Check URL**: Must be `/api/verification/submit` (with `/api` prefix)
2. ✅ **Add `Accept: application/json` header**: This is critical!
3. ✅ **Check Authentication Token**: Must be valid and not expired
4. ✅ **Verify Base URL**: Make sure it's correct (no trailing slash)

### If You Get 401 Unauthenticated

**Problem**: Invalid or missing authentication token

**Solutions**:
1. ✅ Get a fresh token from `/api/auth/login`
2. ✅ Check token format: `Bearer {token}` (with space after "Bearer")
3. ✅ Verify token hasn't expired

### If You Get 422 Validation Error

**Problem**: Missing required fields or invalid data

**Solutions**:
1. ✅ Check all required fields are present
2. ✅ Verify `subscription_plan_id` exists in database
3. ✅ Check field types match (e.g., `partner_id` must be integer)

### If You Get 404 Not Found

**Problem**: Route not found

**Solutions**:
1. ✅ Verify URL is exactly `/api/verification/submit`
2. ✅ Check if route is registered: `php artisan route:list --path=verification/submit`
3. ✅ Clear route cache: `php artisan route:clear`

---

## Testing Without Authentication

To test if routing works, use the test route:

**URL**: `GET https://your-domain.com/api/verification/test`  
**Headers**: None required  
**Expected Response**: JSON with route information

If this also returns HTML, it's a server configuration issue.

---

## Frontend vs Postman Difference

**Frontend Code**:
```dart
POST /verification/submit  // Relative URL
```

**Frontend Base URL** (configured in app):
```
https://api.example.com/api  // Includes /api prefix
```

**Actual Request**:
```
POST https://api.example.com/api/verification/submit  // Full URL
```

**Postman**:
```
POST https://api.example.com/api/verification/submit  // Must include /api
```

The frontend works because its base URL already includes `/api`, so the relative path `/verification/submit` becomes `/api/verification/submit`.

---

## Quick Test Checklist

- [ ] URL includes `/api` prefix: `/api/verification/submit`
- [ ] Method is `POST`
- [ ] `Authorization: Bearer {token}` header is set
- [ ] `Accept: application/json` header is set
- [ ] Body type is `form-data` (not `x-www-form-urlencoded`)
- [ ] All required fields are filled
- [ ] `subscription_plan_id` exists in database
- [ ] Authentication token is valid and not expired

