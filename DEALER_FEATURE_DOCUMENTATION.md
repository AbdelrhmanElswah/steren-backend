# Become a Dealer Feature Implementation

## Overview
This document outlines the complete implementation of the "Become a Dealer" feature for the Laravel application, based on the provided UI mockups.

## Features Implemented

### 1. Database Structure

#### DealerApplication Model
- **File**: `app/Models/DealerApplication.php`
- **Migration**: `database/migrations/2025_10_04_113316_create_dealer_applications_table.php`

**Fields:**
- Basic Info: `seller_type`, `owner_manager_name`, `phone_number`, `email`
- Business Details: `dealership_company_name`, `vehicles_in_stock`, `years_in_business`, `city_address`
- Authorized Seller Fields: `automotive_brand_represented`, `authorization_letter_path`, `business_license_number`
- Application Status: `status`, `rejection_reason`, `reviewed_at`, `reviewed_by`
- Subscription: `subscription_plan_id`, `payment_completed`, `approved_at`, `expires_at`
- Terms: `terms_accepted`, `information_confirmed`

**Status Flow:**
- `pending` → `under_review` → `approved` → `active` (after payment)
- `pending` → `under_review` → `rejected`

### 2. Subscription Plans

#### Predefined Plans (Matching UI)
1. **Starter** - $50/month
   - Post up to 10 listings per month
   - Verified Dealer badge
   - Standard visibility in search results

2. **Pro** - $100/month (Recommended)
   - Post up to 50 listings per month
   - Verified Dealer badge
   - Standard visibility in search results
   - Access to Dealer Dashboard with views & leads analytics

3. **Hero** - $150/month
   - Unlimited listings
   - Verified Dealer badge
   - Featured Ads placement (always on top)
   - Full dealer analytics & insights
   - Dedicated support

### 3. API Endpoints

#### Application Management
```
GET    /api/dealer/status                    - Get dealer application status
GET    /api/dealer/subscription-plans        - Get available subscription plans
POST   /api/dealer/application               - Submit new dealer application
GET    /api/dealer/application/{id}          - Get application details
PUT    /api/dealer/application/{id}          - Update application (pending only)
DELETE /api/dealer/application/{id}          - Cancel application (pending only)
```

#### Subscription Management
```
GET    /api/dealer/subscription/current      - Get current subscription details
GET    /api/dealer/subscription/plans        - Get all subscription plans
PUT    /api/dealer/subscription/update-plan  - Update subscription plan
POST   /api/dealer/subscription/complete-payment - Complete payment for approved application
POST   /api/dealer/subscription/renew        - Renew expired subscription
GET    /api/dealer/subscription/billing-history - Get billing history
```

### 4. User Interface States

#### Account Screen States
1. **No Application**: Show "Become a Dealer" option
2. **Pending**: Show "Pending" badge
3. **Under Review**: Show popup with "Under Review" message
4. **Approved**: Show "Approved" badge, payment completion required
5. **Active**: Show "Verified" badge
6. **Expiring Soon**: Show expiration warning
7. **Expired**: Show "Expired" status with renewal option
8. **Rejected**: Show "Rejected" badge with reason

#### Application Flow
1. **Step 1**: Account & Role Information
   - Seller Type (Authorized/Independent)
   - Owner/Manager Name
   - Phone Number
   - Email

2. **Step 2**: Business Details
   - **Authorized Sellers**: Brand represented, authorization letter, business license
   - **Both Types**: Company name, vehicles in stock, years in business, city & address

3. **Step 3**: Subscription Plan Selection
   - Choose from Starter, Pro, or Hero plans
   - Confirm information and accept terms

### 5. Notification System

#### DealerNotificationService
- **File**: `app/Services/DealerNotificationService.php`
- Handles all dealer-related notifications
- Automatic status change notifications
- Subscription expiration warnings
- Payment completion confirmations

#### Notification Types
- `dealer_application_submitted`
- `dealer_application_review`
- `dealer_application_approved`
- `dealer_application_rejected`
- `dealer_subscription_expiring`
- `dealer_subscription_expired`
- `dealer_activated`

### 6. Automated Tasks

#### Console Command
- **Command**: `dealer:check-subscriptions`
- **File**: `app/Console/Commands/CheckDealerSubscriptions.php`
- Checks for expiring and expired subscriptions
- Sends automatic notifications
- Can be scheduled via cron

### 7. User Model Extensions

#### New Methods
- `isDealer()` - Check if user is an active dealer
- `hasPendingDealerApplication()` - Check for pending applications
- `hasApprovedDealerApplication()` - Check for approved applications
- `isDealerExpiringSoon()` - Check if subscription expires within 3 days
- `isDealerExpired()` - Check if subscription has expired
- `dealer_status` - Get current dealer status for display

#### New Relationships
- `dealerApplications()` - All dealer applications
- `latestDealerApplication()` - Most recent application
- `activeDealerApplication()` - Currently active application

### 8. Account Integration

#### Updated Account Info
- **File**: `app/Http/Controllers/Data/AccountController.php`
- Added dealer status information to account endpoint
- Includes dealer status, application state, and expiration info

## Usage Examples

### 1. Submit Dealer Application
```bash
POST /api/dealer/application
Content-Type: application/json
Authorization: Bearer {token}

{
    "seller_type": "authorized",
    "owner_manager_name": "John Doe",
    "phone_number": "+9647501234567",
    "email": "john@example.com",
    "dealership_company_name": "ABC Motors",
    "vehicles_in_stock": 25,
    "years_in_business": 5,
    "city_address": "Baghdad, Iraq",
    "automotive_brand_represented": "Toyota",
    "business_license_number": "BL123456",
    "subscription_plan_id": 2,
    "terms_accepted": true,
    "information_confirmed": true
}
```

### 2. Check Dealer Status
```bash
GET /api/dealer/status
Authorization: Bearer {token}
```

### 3. Complete Payment
```bash
POST /api/dealer/subscription/complete-payment
Authorization: Bearer {token}

{
    "subscription_plan_id": 2,
    "payment_method": "credit_card",
    "payment_data": {
        "card_number": "****1234",
        "expiry_date": "12/25"
    }
}
```

## File Structure

```
app/
├── Models/
│   ├── DealerApplication.php
│   ├── User.php (updated)
│   └── SubscriptionPlan.php (existing)
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── Dealer/
│   │           ├── DealerApplicationController.php
│   │           └── DealerSubscriptionController.php
│   └── Requests/
│       └── Dealer/
│           ├── DealerApplicationRequest.php
│           └── SubscriptionPlanRequest.php
├── Services/
│   └── DealerNotificationService.php
└── Console/
    └── Commands/
        └── CheckDealerSubscriptions.php

database/
├── migrations/
│   └── 2025_10_04_113316_create_dealer_applications_table.php
└── seeders/
    └── DealerSubscriptionPlanSeeder.php

routes/
└── api.php (updated)
```

## Setup Instructions

1. **Run Migration**:
   ```bash
   php artisan migrate
   ```

2. **Seed Subscription Plans**:
   ```bash
   php artisan db:seed --class=DealerSubscriptionPlanSeeder
   ```

3. **Schedule Subscription Checks** (Optional):
   Add to `app/Console/Kernel.php`:
   ```php
   $schedule->command('dealer:check-subscriptions')->daily();
   ```

## Testing

### Manual Testing
1. Create a user account
2. Submit a dealer application via API
3. Check application status
4. Complete payment process
5. Verify dealer status activation

### API Testing
Use the provided endpoints to test the complete flow:
- Application submission
- Status checking
- Payment completion
- Subscription management

## Security Considerations

1. **Authentication**: All endpoints require valid Sanctum tokens
2. **Authorization**: Users can only access their own applications
3. **File Upload**: Authorization letters are validated and stored securely
4. **Data Validation**: Comprehensive validation rules for all inputs
5. **Transaction Safety**: Database transactions ensure data consistency

## Future Enhancements

1. **Admin Panel**: Create admin interface for managing applications
2. **Email Notifications**: Add email notifications alongside in-app notifications
3. **Payment Gateway Integration**: Integrate with actual payment providers
4. **Analytics Dashboard**: Create dealer analytics dashboard
5. **Automated Approval**: Implement automated approval for certain criteria
6. **Document Verification**: Add document verification service integration
