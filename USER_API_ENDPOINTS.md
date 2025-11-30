# User API Endpoints Documentation

Complete list of all APIs that deal with user authentication, registration, profile management, and related functionality.

**Base URL:** `/api`

---

## 🔐 Authentication & Registration

### 1. **POST** `/api/auth/register`
Register a new user account.

**Auth Required:** ❌ No

**Request Body:**
```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "9647502171212",
  "password": "password123",
  "firebase_uid": "optional-firebase-uid"
}
```

**Response:**
- Returns user object, token, and OTP sent status
- OTP is automatically sent during registration

---

### 2. **POST** `/api/auth/login`
Login with email/phone and password.

**Auth Required:** ❌ No

**Request Body:**
```json
{
  "identifier": "john@example.com",
  "password": "password123"
}
```

**Response:**
- Returns user object and authentication token

---

### 3. **POST** `/api/auth/send-otp`
Send OTP code to phone number.

**Auth Required:** ❌ No

**Request Body:**
```json
{
  "phone": "9647502171212"
}
```

**Response:**
- Returns OTP sent status and expiration time

**Note:** Used for phone verification after registration or when needed for account verification.

---

### 4. **POST** `/api/auth/verify-otp`
Verify OTP code.

**Auth Required:** ✅ Yes (Sanctum)

**Request Body:**
```json
{
  "code": "123456"
}
```

**Response:**
- Returns updated user object and token
- Marks user as verified (`is_verified = true`)
- Syncs user data to Odoo if integration exists

**Note:** This endpoint verifies the phone number and completes the registration process.

---

### 5. **POST** `/api/auth/social-login`
Login/Register using social media (Google, Apple, Facebook, etc.).

**Auth Required:** ❌ No

**Request Body:**
```json
{
  "provider": "google",
  "firebase_token": "firebase-auth-token",
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "9647502171212",
  "profile_picture": "https://example.com/pic.jpg"
}
```

**Response:**
- Returns user object and token
- May return `is_pending: true` if account needs completion (missing phone/email)
- Syncs with Odoo if integration exists

**Supported Providers:**
- `google`
- `apple`
- `facebook`
- Others as configured

---

### 6. **GET** `/api/auth/me`
Get current authenticated user information.

**Auth Required:** ✅ Yes (Sanctum)

**Response:**
- Returns complete user object with relationships

---

### 7. **POST** `/api/auth/logout`
Logout current session.

**Auth Required:** ✅ Yes (Sanctum)

**Response:**
- Success message

**Note:** Revokes only the current authentication token.

---

### 8. **POST** `/api/auth/logout-all`
Logout from all devices.

**Auth Required:** ✅ Yes (Sanctum)

**Response:**
- Success message

**Note:** Revokes all authentication tokens for the user.

---

### 9. **POST** `/api/auth/complete-pending-account`
Complete a pending account (e.g., after social login missing required fields).

**Auth Required:** ✅ Yes (Sanctum)

**Request Body:**
```json
{
  "full_name": "John Doe",
  "phone": "9647502171212"
}
```

**Response:**
- Returns updated user object and Odoo client integration info

**Note:** Used when social login created a pending account without phone/email.

---

## 🔑 Password Management

### 10. **POST** `/api/auth/password/forgot`
Request password reset OTP code.

**Auth Required:** ❌ No

**Request Body:**
```json
{
  "phone": "9647502171212"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Reset code sent",
  "data": {
    "sent": true,
    "otp": "1234"
  }
}
```

**Note:** 
- Sends OTP/reset code to the phone number for password reset
- Returns OTP code in response (for testing purposes only, will be removed in production)
- OTP is sent via WhatsApp for Iraq numbers

---

### 11. **POST** `/api/auth/password/verify-otp`
Verify OTP code for password reset.

**Auth Required:** ❌ No

**Request Body:**
```json
{
  "phone": "9647502171212",
  "code": "1234"
}
```

**Response:**
```json
{
  "success": true,
  "message": "OTP verified successfully",
  "data": {
    "verified": true
  }
}
```

**Note:** 
- Verifies the OTP code sent to phone for password reset
- Must be called before `/api/auth/password/reset`
- Accepts test code "1234" for testing purposes (will be removed in production)
- Verification status is stored in cache for 10 minutes

---

### 12. **POST** `/api/auth/password/reset`
Complete password reset with new password.

**Auth Required:** ❌ No

**Request Body:**
```json
{
  "phone": "9647502171212",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response:**
- Success message

**Note:** 
- Resets password after OTP has been verified
- Requires phone number to be verified first via `/api/auth/password/verify-otp`
- Password must be confirmed with `password_confirmation` field

---

### 13. **PUT** `/api/auth/profile/password`
Update password for authenticated user.

**Auth Required:** ✅ Yes (Sanctum)

**Request Body:**
```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

**Response:**
- Success message

**Note:** Requires current password verification.

---

## 👤 User Profile Management

### 13. **GET** `/api/auth/profile`
Get user profile information.

**Auth Required:** ✅ Yes (Sanctum)

**Response:**
- Returns user profile with dealer status and application details
- Includes dealer application status, subscription info, etc.

---

### 14. **PUT** `/api/auth/profile`
Update user profile information.

**Auth Required:** ✅ Yes (Sanctum)

**Request Body:**
```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "9647502171212",
  "birthday": "1990-01-15",
  "notifications_enabled": true,
  "marketing_emails_enabled": false,
  "notification_preferences": {
    "push": true,
    "email": false,
    "sms": true
  },
  "language_id": 1
}
```

**Response:**
- Returns updated user object

**Note:** 
- All fields are optional
- Automatically syncs updates to Odoo (if user has `partner_id` or `odoo_id`)
- Updates Laravel database first, then syncs to Odoo

---

### 15. **PUT** `/api/auth/profile/complete`
Update user profile information and picture in a single request.

**Auth Required:** ✅ Yes (Sanctum)

**Request Body:**
```
Content-Type: multipart/form-data

{
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "9647502171212",
  "birthday": "1990-01-15",
  "notifications_enabled": true,
  "marketing_emails_enabled": false,
  "notification_preferences": {
    "push": true,
    "email": false,
    "sms": true
  },
  "language_id": 1,
  "profile_picture": <file>
}
```

**Response:**
- Returns updated user object and profile picture URL (if picture was updated)

**Note:** 
- All fields are optional
- Can update profile info only, picture only, or both together
- Automatically syncs all updates to Odoo in a single call
- Uses `multipart/form-data` for file upload

---

### 16. **POST** `/api/auth/profile/picture`
Update user profile picture only.

**Auth Required:** ✅ Yes (Sanctum)

**Request Body:**
```
Content-Type: multipart/form-data

{
  "profile_picture": <file>
}
```

**Response:**
- Returns profile picture URL

**Note:**
- Uploads picture to S3
- Creates media record
- Automatically syncs to Odoo (if user has `partner_id` or `odoo_id`)
- Deletes old profile picture automatically

---

### 17. **PUT** `/api/auth/profile/preferences`
Update user preferences only.

**Auth Required:** ✅ Yes (Sanctum)

**Request Body:**
```json
{
  "notifications_enabled": true,
  "marketing_emails_enabled": false,
  "notification_preferences": {
    "push": true,
    "email": false,
    "sms": true
  },
  "language_id": 1,
  "theme": "dark"
}
```

**Response:**
- Returns updated user object

**Note:** All fields are optional.

---

### 18. **GET** `/api/auth/profile/favorites`
Get user favorite preferences (brands, models, body types, cities).

**Auth Required:** ✅ Yes (Sanctum)

**Response:**
```json
{
  "favorites": {
    "brand_ids": [1, 2, 3],
    "model_ids": [10, 20],
    "body_type_ids": [1, 2],
    "city_ids": [5, 6],
    "brands": [...],
    "models": [...],
    "body_types": [...],
    "cities": [...]
  }
}
```

**Note:** Returns both IDs and full details of favorites.

---

### 19. **PUT** `/api/auth/profile/favorites`
Set user favorite preferences.

**Auth Required:** ✅ Yes (Sanctum)

**Request Body:**
```json
{
  "fav_brand_ids": [1, 2, 3],
  "fav_models_ids": [10, 20],
  "fav_body_type_ids": [1, 2],
  "fav_citie_ids": [5, 6]
}
```

**Response:**
- Returns updated user object and favorite IDs

**Note:** All fields are optional. Used to personalize content recommendations.

---

## 🔗 Odoo/Partner Integration

### 20. **GET** `/api/auth/partner/info`
Get Odoo partner information for authenticated user.

**Auth Required:** ✅ Yes (Sanctum)

**Response:**
- Returns partner information from Odoo

**Note:** Requires user to have `partner_id` set. Fetches data from Odoo system.

---

### 21. **PUT** `/api/auth/partner/update`
Update Odoo partner information.

**Auth Required:** ✅ Yes (Sanctum)

**Request Body:**
```json
{
  "full_name": "John Doe",
  "phone": "9647502171212",
  "email": "john@example.com"
}
```

**Response:**
- Success message

**Note:** 
- Updates both Laravel user record and Odoo partner
- Requires user to have `partner_id` set

---

## 📱 Odoo User Management (Odoo → Laravel)

### 22. **POST** `/api/odoo/users`
Create a new user from Odoo.

**Auth Required:** ⚠️ Odoo API Key (middleware: `odoo.tracking`)

**Note:** This is for Odoo to create users in Laravel system. Not for frontend use.

---

### 23. **POST** `/api/odoo/users/odoo/{odooId}/delete`
Delete (soft delete) user by Odoo ID.

**Auth Required:** ⚠️ Odoo API Key (middleware: `odoo.tracking`)

**Note:** Odoo webhook endpoint. Not for frontend use.

---

### 24. **POST** `/api/odoo/users/odoo/{odooId}/restore`
Restore soft-deleted user by Odoo ID.

**Auth Required:** ⚠️ Odoo API Key (middleware: `odoo.tracking`)

**Note:** Odoo webhook endpoint. Not for frontend use.

---

## 🎯 Client Management

### 25. **POST** `/api/client/create`
Create a new client (Odoo integration).

**Auth Required:** ❌ No

**Note:** Creates client record for Odoo integration purposes.

---

### 26. **GET** `/api/client/partner/{partnerId}`
Get client information by partner ID.

**Auth Required:** ❌ No

**Note:** Fetches client data from Laravel database by Odoo partner ID.

---

## 📊 Account Information

### 27. **GET** `/api/account/info`
Get account information.

**Auth Required:** ✅ Yes (Sanctum)

**Note:** Comprehensive account details endpoint.

---

### 28. **GET** `/api/user`
Get current authenticated user (Laravel default route).

**Auth Required:** ✅ Yes (Sanctum)

**Note:** Simple endpoint returning authenticated user from Sanctum.

---

## 📝 Summary

### Public Endpoints (No Auth):
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/send-otp`
- `POST /api/auth/social-login`
- `POST /api/auth/password/forgot`
- `POST /api/auth/password/verify-otp`
- `POST /api/auth/password/reset`
- `POST /api/client/create`
- `GET /api/client/partner/{partnerId}`

### Protected Endpoints (Require Auth):
- `GET /api/auth/me`
- `POST /api/auth/logout`
- `POST /api/auth/logout-all`
- `POST /api/auth/verify-otp`
- `POST /api/auth/complete-pending-account`
- `GET /api/auth/profile`
- `PUT /api/auth/profile`
- `PUT /api/auth/profile/complete`
- `POST /api/auth/profile/picture`
- `PUT /api/auth/profile/password`
- `PUT /api/auth/profile/preferences`
- `GET /api/auth/profile/favorites`
- `PUT /api/auth/profile/favorites`
- `GET /api/auth/partner/info`
- `PUT /api/auth/partner/update`
- `GET /api/account/info`
- `GET /api/user`

### Odoo Webhook Endpoints (Odoo API Key):
- `POST /api/odoo/users`
- `POST /api/odoo/users/odoo/{odooId}/delete`
- `POST /api/odoo/users/odoo/{odooId}/restore`

---

## 🔄 Data Flow

### User Registration Flow:
1. `POST /api/auth/register` → Creates user, sends OTP
2. `POST /api/auth/verify-otp` → Verifies phone, syncs to Odoo

### Social Login Flow:
1. `POST /api/auth/social-login` → Creates/authenticates user
2. If pending: `POST /api/auth/complete-pending-account` → Completes account

### Password Reset Flow:
1. `POST /api/auth/password/forgot` → Sends OTP code (returns OTP for testing)
2. `POST /api/auth/password/verify-otp` → Verifies OTP code (accepts "1234" for testing)
3. `POST /api/auth/password/reset` → Updates password (only phone + new password needed)

### Profile Update Flow:
1. `PUT /api/auth/profile` or `PUT /api/auth/profile/complete` → Updates Laravel DB
2. Automatically syncs to Odoo (if `partner_id`/`odoo_id` exists)

---

## 🔐 Authentication Method

All protected endpoints use **Laravel Sanctum** token authentication.

Include token in Authorization header:
```
Authorization: Bearer {token}
```

---

## 📌 Important Notes

1. **Odoo Sync**: 
   - User profile updates (name, email, phone, birthday, profile picture) are automatically synced to Odoo
   - Sync is one-way: Laravel → Odoo
   - Odoo does NOT update Laravel user records directly
   - Sync uses `partner_id` or `odoo_id` to identify the client in Odoo

2. **Phone Verification**:
   - Required for creating car listings
   - Done via OTP verification after registration

3. **Pending Accounts**:
   - Created when social login is missing required fields (phone/email)
   - Must complete via `/api/auth/complete-pending-account`

4. **Profile Picture**:
   - Uploaded to AWS S3
   - Old pictures are automatically deleted
   - Supports multiple formats (JPEG, PNG, WebP)

5. **Password Security**:
   - Password changes require current password verification
   - Password reset uses phone-based OTP verification in 3 steps:
     1. Request OTP via `/api/auth/password/forgot`
     2. Verify OTP via `/api/auth/password/verify-otp`
     3. Reset password via `/api/auth/password/reset`
   - Test bypass code "1234" is accepted for OTP verification (testing only)
   - OTP codes are returned in response for testing (will be removed in production)

