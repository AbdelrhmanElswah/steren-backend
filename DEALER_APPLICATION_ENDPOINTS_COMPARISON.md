# Dealer Application Endpoints

## ⚠️ IMPORTANT: `/api/dealer/application` (POST) has been REMOVED

**Status**: ❌ **DELETED** - This endpoint has been removed from the codebase.

**Reason**: It did not sync to Odoo and was causing issues. Use `/api/verification/submit` instead.

---

## ✅ Active Endpoint for Creating Dealer Applications

### `/api/verification/submit` (POST)
**Controller**: `DealerVerificationController::submit()`  
**Request**: `DealerVerificationRequest`  
**Auth**: `auth:sanctum` (no phone verification required)

**Features**:
- ✅ Automatically syncs to Odoo
- ✅ Supports both authorization letter and business license file uploads
- ✅ No phone verification requirement
- ✅ Sets status to `application_applied`

---

## `/api/verification/submit` Payload

**Required Fields:**
- `partner_id` (integer) - **MUST be provided**
- `seller_type` (string: 'authorized' or 'independent')
- `dealership_company_name` (string)
- `owner_manager_name` (string)
- `email` (string)
- `city_address` (string)
- `subscription_plan_id` (integer) - **MUST be provided**

**Optional Fields:**
- `phone_number` (string)
- `brand_id` (integer)
- `automotive_brand_represented` (string)
- `business_license_number` (string)
- `vehicles_in_stock` (numeric)
- `years_in_business` (numeric)
- `authorization_letter` (file: PDF, JPG, JPEG, PNG, max 10MB)
- `business_license` (file: PDF, JPG, JPEG, PNG, max 10MB)

**File Uploads:**
- `authorization_letter` (file)
- `business_license` (file)

---

## Form Data Support

**The endpoint supports `multipart/form-data`** for file uploads.

### Example for `/api/verification/submit`:
```
POST /api/verification/submit
Content-Type: multipart/form-data
Authorization: Bearer {token}

partner_id: 123
seller_type: authorized
dealership_company_name: ABC Motors
owner_manager_name: John Doe
email: john@example.com
city_address: Baghdad, Iraq
subscription_plan_id: 10
automotive_brand_represented: Toyota
business_license_number: BL123456
brand_id: 1
vehicles_in_stock: 50
years_in_business: 5
authorization_letter: [file]
business_license: [file]
```

---

## Important Notes

1. **Partner ID**: Must be provided. Get it from authenticated user: `$user->partner_id`
2. **Subscription Plan ID**: Must be provided (no default)
3. **File Uploads**: Supports both `authorization_letter` and `business_license` files
4. **Odoo Sync**: Automatically syncs to Odoo after creating the application
5. **Status**: Application is created with status `application_applied`

