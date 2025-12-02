# Dealer Application APIs

Complete list of all APIs that deal with the `DealerApplication` model.

---

## 📋 User-Facing APIs (Protected)

**Base Path**: `/api/dealer`  
**Middleware**: `auth:sanctum`, `phone.verified`

### 1. **GET** `/api/dealer/status`
Get dealer application status for the authenticated user.

**Auth Required**: ✅ Yes (Sanctum + Phone Verified)

**Response**:
```json
{
  "success": true,
  "data": {
    "has_application": true,
    "application": {
      "id": 1,
      "status": "pending",
      "status_badge_color": "#F59E0B",
      "seller_type": "dealer",
      "dealership_company_name": "ABC Motors",
      "subscription_plan": {
        "id": 10,
        "name": "Premium Plan",
        "price": 100.00,
        "formatted_price": "$100.00"
      },
      "payment_completed": false,
      "created_at": "2024-01-01T00:00:00.000000Z",
      "reviewed_at": null,
      "approved_at": null,
      "expires_at": null,
      "rejection_reason": null,
      "days_until_expiration": null
    },
    "is_active": false,
    "is_expiring_soon": false,
    "is_expired": false,
    "message": "Your application is pending review"
  }
}
```

**Controller**: `DealerApplicationController::status()`

---

### 2. **GET** `/api/dealer/subscription-plans`
Get available subscription plans for dealer applications.

**Auth Required**: ✅ Yes (Sanctum + Phone Verified)

**Response**:
```json
{
  "success": true,
  "data": {
    "subscription_plans": [
      {
        "id": 10,
        "name": "Premium Plan",
        "description": "Premium dealer plan",
        "price": 100.00,
        "formatted_price": "$100.00",
        "currency": "USD",
        "billing_cycle": "monthly",
        "features": {...},
        "max_listings": 50,
        "is_popular": true,
        "sort_order": 1
      }
    ]
  }
}
```

**Controller**: `DealerApplicationController::subscriptionPlans()`

---

### 3. **GET** `/api/dealer/application/{id}`
Get dealer application details.

**Auth Required**: ✅ Yes (Sanctum + Phone Verified)

**Response**:
```json
{
  "success": true,
  "data": {
    "application": {
      "id": 1,
      "seller_type": "dealer",
      "owner_manager_name": "John Doe",
      "phone_number": "+9647501234567",
      "email": "john@example.com",
      "dealership_company_name": "ABC Motors",
      "vehicles_in_stock": 50,
      "years_in_business": 5,
      "city_address": "Baghdad, Iraq",
      "automotive_brand_represented": "Toyota",
      "authorization_letter_url": "https://...",
      "business_license_number": "BL123456",
      "status": "pending",
      "status_badge_color": "#F59E0B",
      "subscription_plan": {...},
      "payment_completed": false,
      "terms_accepted": true,
      "information_confirmed": true,
      "created_at": "2024-01-01T00:00:00.000000Z",
      "reviewed_at": null,
      "approved_at": null,
      "expires_at": null,
      "rejection_reason": null,
      "reviewer": null
    }
  }
}
```

**Controller**: `DealerApplicationController::show()`

---

### 4. **PUT** `/api/dealer/application/{id}`
Update dealer application (only if pending).

**Auth Required**: ✅ Yes (Sanctum + Phone Verified)

**Request Body**: Same as POST `/api/dealer/application`

**Response**:
```json
{
  "success": true,
  "message": "Dealer application updated successfully",
  "data": {
    "application": {
      "id": 1,
      "status": "pending",
      "message": "Dealer application updated successfully"
    }
  }
}
```

**Controller**: `DealerApplicationController::update()`  
**Request Validation**: `DealerApplicationRequest`

**Notes**:
- Only allows updates if application status is "pending"
- Handles authorization letter file replacement (deletes old file)
- Sets default subscription plan if not provided

---

### 5. **DELETE** `/api/dealer/application/{id}`
Cancel dealer application (only if pending).

**Auth Required**: ✅ Yes (Sanctum + Phone Verified)

**Response**:
```json
{
  "success": true,
  "message": "Dealer application cancelled successfully",
  "data": null
}
```

**Controller**: `DealerApplicationController::destroy()`

**Notes**:
- Only allows deletion if application status is "pending"
- Deletes authorization letter file from storage
- Soft deletes the application record

---

## 🔐 Verification APIs

**Base Path**: `/api/verification`

### 7. **POST** `/api/verification/submit`
Submit dealer verification to Odoo.

**Auth Required**: ✅ Yes (Sanctum)

**Request Body**: `DealerVerificationRequest`

**Response**:
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

**Controller**: `DealerVerificationController::submit()`  
**Service**: `DealerVerificationService::submitVerification()`

---

### 8. **GET** `/api/verification/status`
Get verification status for authenticated user.

**Auth Required**: ✅ Yes (Sanctum)

**Response**: Similar to `/api/dealer/status`

**Controller**: `DealerVerificationController::status()`

---

### 9. **POST** `/api/verification/update-status`
Update verification status (Odoo webhook).

**Auth Required**: ❌ No (Odoo webhook)

**Request Body**:
```json
{
  "application_id": 1,
  "status": "approved",
  "reason": "All documents verified"
}
```

**Controller**: `DealerVerificationController::updateStatus()`  
**Service**: `DealerVerificationService::updateVerificationStatus()`

---

## 🔧 Admin/Odoo APIs

**Base Path**: `/api/dealer-applications`  
**Middleware**: `odoo.tracking` (Odoo API Key)

### 10. **POST** `/api/dealer-applications/{applicationId}/approve`
Approve a dealer application by application ID.

**Auth Required**: ⚠️ Odoo API Key

**Response**:
```json
{
  "success": true,
  "message": "Dealer application approved successfully",
  "data": {
    "application_id": 1,
    "status": "approved",
    "approved_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

**Controller**: `DealerVerificationController::approve()`  
**Service**: `DealerVerificationService::approveApplication()`

**Notes**:
- Sends notification to user
- Updates application status
- Sets `approved_at` timestamp

---

### 11. **POST** `/api/dealer-applications/{applicationId}/reject`
Reject a dealer application by application ID.

**Auth Required**: ⚠️ Odoo API Key

**Request Body**:
```json
{
  "reason": "Incomplete documentation"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Dealer application rejected successfully",
  "data": {
    "application_id": 1,
    "status": "rejected",
    "rejection_reason": "Incomplete documentation"
  }
}
```

**Controller**: `DealerVerificationController::reject()`  
**Service**: `DealerVerificationService::rejectApplication()`

**Notes**:
- Sends notification to user
- Updates application status
- Stores rejection reason

---

### 12. **POST** `/api/dealer-applications/{applicationId}/process`
Process a dealer application (approve or reject) by application ID.

**Auth Required**: ⚠️ Odoo API Key

**Request Body**:
```json
{
  "action": "approve",
  "reason": "Optional reason"
}
```

**Response**: Same as approve/reject endpoints

**Controller**: `DealerVerificationController::processApplication()`

---

### 13. **POST** `/api/dealer-applications/odoo/{odooId}/approve`
Approve a dealer application by Odoo ID.

**Auth Required**: ⚠️ Odoo API Key

**Response**: Same as approve endpoint

**Controller**: `DealerVerificationController::approveByOdooId()`

**Notes**:
- Finds application by `odoo_id`
- Calls Odoo mark-paid API after approval
- Sends notification to user

---

### 14. **POST** `/api/dealer-applications/odoo/{odooId}/reject`
Reject a dealer application by Odoo ID.

**Auth Required**: ⚠️ Odoo API Key

**Response**: Same as reject endpoint

**Controller**: `DealerVerificationController::rejectByOdooId()`

**Notes**:
- Finds application by `odoo_id`
- Uses default rejection reason: "Rejected via Odoo integration"
- Calls Odoo mark-paid API after rejection

---

### 15. **POST** `/api/dealer-applications/odoo/{odooId}/process`
Process a dealer application (approve or reject) by Odoo ID.

**Auth Required**: ⚠️ Odoo API Key

**Request Body**:
```json
{
  "action": "approve"
}
```

**Response**: Same as approve/reject endpoints

**Controller**: `DealerVerificationController::processByOdooId()`

---

## 📊 Summary Table

| Endpoint | Method | Auth | Purpose | Controller |
|----------|--------|------|---------|------------|
| `/api/dealer/status` | GET | Sanctum + Phone | Get application status | DealerApplicationController |
| `/api/dealer/subscription-plans` | GET | Sanctum + Phone | Get subscription plans | DealerApplicationController |
| `/api/dealer/application/{id}` | GET | Sanctum + Phone | Get application details | DealerApplicationController |
| `/api/dealer/application/{id}` | PUT | Sanctum + Phone | Update application | DealerApplicationController |
| `/api/dealer/application/{id}` | DELETE | Sanctum + Phone | Cancel application | DealerApplicationController |
| `/api/verification/submit` | POST | Sanctum | Submit application (with Odoo sync) | DealerVerificationController |
| `/api/verification/submit` | POST | Sanctum | Submit verification | DealerVerificationController |
| `/api/verification/status` | GET | Sanctum | Get verification status | DealerVerificationController |
| `/api/verification/update-status` | POST | Odoo | Update status (webhook) | DealerVerificationController |
| `/api/dealer-applications/{id}/approve` | POST | Odoo | Approve by ID | DealerVerificationController |
| `/api/dealer-applications/{id}/reject` | POST | Odoo | Reject by ID | DealerVerificationController |
| `/api/dealer-applications/{id}/process` | POST | Odoo | Process by ID | DealerVerificationController |
| `/api/dealer-applications/odoo/{odooId}/approve` | POST | Odoo | Approve by Odoo ID | DealerVerificationController |
| `/api/dealer-applications/odoo/{odooId}/reject` | POST | Odoo | Reject by Odoo ID | DealerVerificationController |
| `/api/dealer-applications/odoo/{odooId}/process` | POST | Odoo | Process by Odoo ID | DealerVerificationController |

---

## 🔄 Related Services

### DealerVerificationService
- `submitVerification()` - Submit application and sync to Odoo
- `approveApplication()` - Approve application
- `rejectApplication()` - Reject application
- `updateVerificationStatus()` - Update status from Odoo webhook

---

## 📝 Notes

1. **Application Status Flow**:
   - `pending` → `under_review` → `approved` / `rejected`

2. **File Uploads**:
   - Authorization letter is stored in `storage/app/public/dealer-applications/authorization-letters/`
   - Old files are deleted when updating

3. **Odoo Integration**:
   - Applications are synced to Odoo when created
   - Odoo can approve/reject via webhooks
   - Odoo mark-paid API is called after approval/rejection

4. **Default Subscription Plan**:
   - If not provided, defaults to plan ID 10
   - Falls back to first active plan if ID 10 doesn't exist

5. **Notifications**:
   - Users receive notifications when application is approved/rejected
   - Sent via `UnifiedNotificationService`

---

## 🔗 Related Models

- `DealerApplication` - Main model
- `SubscriptionPlan` - Subscription plan for dealer
- `Status` - Application status (pending, approved, rejected, etc.)
- `User` - User who submitted the application
- `CarBrand` - Brand associated with authorized agency

