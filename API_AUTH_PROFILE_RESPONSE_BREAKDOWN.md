# `/api/auth/profile` Response Field Breakdown

This document explains where each field in the `/api/auth/profile` response comes from.

## Response Structure

```json
{
    "success": true,
    "message": "Profile information retrieved successfully",
    "data": {
        "user": { ... },
        "dealer_status": { ... },
        "authorized_agency": { ... }
    }
}
```

---

## 1. `user` Object

**Source:** `UserResource` (app/Http/Resources/UserResource.php)  
**Controller:** `UserProfileController::getProfile()` (line 716)

| Field | Source | Model/Table | Notes |
|-------|--------|-------------|-------|
| `id` | Direct attribute | `users.id` | Primary key |
| `partner_id` | Direct attribute | `users.partner_id` | Odoo partner ID |
| `full_name` | Direct attribute | `users.full_name` | User's full name |
| `display_name` | Accessor | `users.display_name` OR `users.full_name` | Accessor: `getDisplayNameAttribute()` - falls back to `full_name` if `display_name` is null |
| `email` | Direct attribute | `users.email` | User's email |
| `phone` | Direct attribute | `users.phone` | User's phone number |
| `phone_verified` | Computed | `users.phone_verified_at` | Boolean: `!is_null($this->phone_verified_at)` |
| `birthday` | Direct attribute (formatted) | `users.birthday` | Formatted as `Y-m-d` string, null if not set |
| `profile_picture` | Accessor | `users` → `media` table | Accessor: `getProfilePictureAttribute()` - returns `profilePictureMedia->public_url` |
| `profile_picture_media` | Relationship | `users` → `media` table | Returns `profilePictureMedia->media` (filename) |
| `language` | Relationship | `users.language_id` → `languages` table | Returns `language` relationship (full object or null) |
| `theme` | Direct attribute | `users.theme` | User's theme preference (light/dark/system) |
| `is_verified` | Direct attribute (cast) | `users.is_verified` | Boolean cast |

**Note:** `dealer_application` was previously in `UserResource` but was removed. Use `dealer_status.application` instead.

---

## 2. `dealer_status` Object

**Source:** Built manually in `UserProfileController::getProfile()` (lines 618-681)  
**Based on:** `User::latestDealerApplication` relationship

| Field | Source | Model/Table | Notes |
|-------|--------|-------------|-------|
| `has_application` | Computed | `DealerApplication` | Boolean: checks if `latestDealerApplication` exists |
| `application.id` | Direct attribute | `dealer_applications.id` | Primary key |
| `application.status` | Relationship | `dealer_applications.status_id` → `statuses.name` | Returns `status->name` (e.g., "pending", "under_review", "approved", "rejected") |
| `application.status_badge_color` | Accessor | `dealer_applications.status_id` → `statuses.color` | Accessor: `getStatusBadgeColorAttribute()` - returns `status->color ?? '#6B7280'` |
| `application.seller_type` | Direct attribute | `dealer_applications.seller_type` | Enum: "authorized" or "independent" |
| `application.dealership_company_name` | Direct attribute | `dealer_applications.dealership_company_name` | Company name |
| `application.subscription_plan.id` | Relationship | `dealer_applications.subscription_plan_id` → `subscription_plans.id` | Primary key |
| `application.subscription_plan.name` | Relationship | `subscription_plans.name` | Plan name |
| `application.subscription_plan.price` | Relationship | `subscription_plans.price` | Decimal price |
| `application.subscription_plan.formatted_price` | Accessor | `subscription_plans.price` + `currency` | Accessor: `getFormattedPriceAttribute()` - returns `"{currency} {formatted_price}"` (e.g., "IQD 0.00") |
| `application.payment_completed` | Direct attribute | `dealer_applications.payment_completed` | Boolean |
| `application.reviewed_at` | Direct attribute | `dealer_applications.reviewed_at` | DateTime or null |
| `application.approved_at` | Direct attribute | `dealer_applications.approved_at` | DateTime or null |
| `application.expires_at` | Direct attribute | `dealer_applications.expires_at` | DateTime or null |
| `application.rejection_reason` | Direct attribute | `dealer_applications.rejection_reason` | Text or null |
| `application.days_until_expiration` | Accessor | `dealer_applications.expires_at` | Accessor: `getDaysUntilExpirationAttribute()` - returns `expires_at->diffInDays(now())` or null |
| `is_active` | Method call | `DealerApplication::isActive()` | Returns `isVerified() && payment_completed && (expires_at === null || expires_at->isFuture())` |
| `is_expiring_soon` | Method call | `DealerApplication::isExpiringSoon()` | Returns true if expires within 3 days |
| `is_expired` | Method call | `DealerApplication::isExpired()` | Returns true if `isApproved() && expires_at && expires_at->isPast()` |
| `message` | Computed | Based on `status` | Switch statement based on `status->name` (lines 651-674) |

**Relationships loaded:**
- `latestDealerApplication.status` (Status model)
- `latestDealerApplication.subscriptionPlan` (SubscriptionPlan model)
- `activeDealerApplication` (for `is_active`, `is_expiring_soon` checks)

---

## 3. `authorized_agency` Object

**Source:** Built manually in `UserProfileController::getProfile()` (lines 683-713)  
**Based on:** `AuthorizedAgency` model lookup by `partner_id`

| Field | Source | Model/Table | Notes |
|-------|--------|-------------|-------|
| `is_authorized_agency` | Computed | `authorized_agencies` table | Boolean: checks if `AuthorizedAgency::where('partner_id', $user->partner_id)->first()` exists |
| `brand.id` | Relationship | `dealer_applications.brand_id` → `car_brands.id` | Only if `is_authorized_agency === true` AND `latestDealerApplication->brand_id` exists |
| `brand.name` | Relationship | `car_brands.name` | Brand name |
| `brand.models` | Relationship | `car_brands.id` → `car_models.car_brand_id` | Array of models: `[{id, name}, ...]` - loaded via `latestDealerApplication.brand.carModels` relationship |

**Relationships loaded:**
- `latestDealerApplication.brand.carModels` (with select: `id`, `name`, `car_brand_id`, ordered by `name`)

**Note:** `brand` is only populated if:
1. User has `partner_id`
2. `AuthorizedAgency` record exists for that `partner_id`
3. `latestDealerApplication` exists
4. `latestDealerApplication->brand_id` is set
5. `latestDealerApplication->brand` relationship is loaded

---

## Summary of Data Sources

### Database Tables:
- `users` - Main user data
- `dealer_applications` - Dealer application data
- `subscription_plans` - Subscription plan details
- `statuses` - Status definitions (for dealer applications)
- `car_brands` - Car brand data
- `car_models` - Car model data
- `authorized_agencies` - Authorized agency lookup
- `media` - Profile picture media records
- `languages` - Language preferences

### Key Relationships:
- `User::latestDealerApplication()` - HasOne relationship
- `User::activeDealerApplication()` - HasOne relationship (scoped to active)
- `User::profilePictureMedia()` - MorphOne relationship
- `User::language()` - BelongsTo relationship
- `DealerApplication::status()` - BelongsTo relationship
- `DealerApplication::subscriptionPlan()` - BelongsTo relationship
- `DealerApplication::brand()` - BelongsTo relationship
- `CarBrand::carModels()` - HasMany relationship

### Accessors (Computed Fields):
- `User::getDisplayNameAttribute()` - Falls back to `full_name`
- `User::getProfilePictureAttribute()` - Returns media public URL
- `DealerApplication::getStatusBadgeColorAttribute()` - Returns status color
- `DealerApplication::getDaysUntilExpirationAttribute()` - Calculates days until expiration
- `SubscriptionPlan::getFormattedPriceAttribute()` - Formats price with currency

### Methods (Computed Logic):
- `DealerApplication::isActive()` - Checks if dealer is active
- `DealerApplication::isExpiringSoon()` - Checks if subscription expires soon
- `DealerApplication::isExpired()` - Checks if subscription is expired

---

## Potential Unused Fields to Review

Based on the response structure, here are fields that might be unused or redundant:

### In `user` object:
- `language` - Full language object (might only need `language_id` or `language->code`)
- `theme` - Might not be used by frontend

### In `dealer_status.application`:
- `reviewed_at` - Might not be needed if `approved_at`/`rejected_at` are present
- `days_until_expiration` - Could be computed on frontend from `expires_at`
- `message` - Computed message (could be computed on frontend)

### In `authorized_agency.brand.models`:
- Full model objects with `id` and `name` - Check if frontend needs all models or just IDs

---

## Files to Check for Usage

To determine which fields are actually used, check:
1. Frontend code that consumes `/api/auth/profile`
2. Any API documentation that references this endpoint
3. Mobile app code (if applicable)
4. Any other services that call this endpoint

