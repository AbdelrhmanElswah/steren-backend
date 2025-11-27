# Odoo CRUD Endpoints Analysis

This document provides a comprehensive overview of all CRUD endpoints for Odoo integration entities.

## Base URL
- **POST Routes (Protected)**: `/api/odoo` (with `odoo.tracking` middleware)
- **GET Routes (Public)**: `/api/odoo` (no authentication required)

---

## 1. Car Brands (Brands)

### Create
- **Endpoint**: `POST /api/odoo/car-brands`
- **Request Class**: `StoreCarBrandRequest`
- **Required Fields**:
  - `name` (required|string|max:255)
- **Optional Fields**:
  - `odoo_id` (nullable|string|max:255|unique:car_brands,odoo_id)
  - `media` (nullable - file upload or base64 string, max 5MB, image types: jpeg, png, gif, bmp, webp)

### Read
- **Endpoint**: `GET /api/odoo/brands`
- **Request Class**: `GetCarBrandsRequest`
- **Query Parameters**:
  - `search` (optional|string|max:255)
  - `id` (optional|integer|min:1)
  - `odoo_id` (optional|string|max:255)
  - `per_page` (optional|integer|min:1|max:100)
  - `page` (optional|integer|min:1)

### Update
- **Endpoint**: `POST /api/odoo/car-brands/odoo/{odooId}/update`
- **Request Class**: `UpdateCarBrandRequest`
- **Fields** (all optional):
  - `odoo_id` (sometimes|string|max:255|unique:car_brands,odoo_id)
  - `name` (sometimes|string|max:255)
  - `media` (sometimes|nullable - file upload or base64 string)

### Delete
- **Endpoint**: `POST /api/odoo/car-brands/odoo/{odooId}/delete`
- **Method**: Soft delete

### Restore
- **Endpoint**: `POST /api/odoo/car-brands/odoo/{odooId}/restore`
- **Method**: Restore soft-deleted record

---

## 2. Car Models (Models)

### Create
- **Endpoint**: `POST /api/odoo/car-models`
- **Request Class**: `StoreCarModelRequest`
- **Required Fields**:
  - `name` (required|string|max:255)
  - `car_brand_id` (required - can be odoo_id or Laravel id)
- **Optional Fields**:
  - `odoo_id` (nullable|max:255|unique:car_models,odoo_id)

### Read
- **Endpoint**: `GET /api/odoo/car-models`
- **Request Class**: `GetCarModelsRequest`
- **Query Parameters**:
  - `search` (optional|string|max:255)
  - `id` (optional|integer|min:1)
  - `odoo_id` (optional|string|max:255)
  - `brand_id` (optional|integer|min:1)

### Update
- **Endpoint**: `POST /api/odoo/car-models/odoo/{odooId}/update`
- **Request Class**: `UpdateCarModelRequest`
- **Fields** (all optional):
  - `odoo_id` (sometimes|max:255|unique:car_models,odoo_id)
  - `name` (sometimes|string|max:255)
  - `car_brand_id` (sometimes - can be odoo_id or Laravel id)

### Delete
- **Endpoint**: `POST /api/odoo/car-models/odoo/{odooId}/delete`
- **Method**: Soft delete

### Restore
- **Endpoint**: `POST /api/odoo/car-models/odoo/{odooId}/restore`
- **Method**: Restore soft-deleted record

---

## 3. Body Types

### Create
- **Endpoint**: `POST /api/odoo/body-types`
- **Request Class**: `StoreBodyTypeRequest`
- **Required Fields**:
  - `name` (required|string|max:255)
- **Optional Fields**:
  - `odoo_id` (nullable|string|max:255|unique:body_types,odoo_id)
  - `media` (nullable|string)

### Read
- **Endpoint**: `GET /api/odoo/body-types`
- **Request Class**: `GetBodyTypesRequest`
- **Query Parameters**:
  - `search` (optional|string|max:255)
  - `id` (optional|integer|min:1)
  - `odoo_id` (optional|string|max:255)
  - `per_page` (optional|integer|min:1|max:100)
  - `page` (optional|integer|min:1)

### Update
- **Endpoint**: `POST /api/odoo/body-types/odoo/{odooId}/update`
- **Request Class**: `UpdateBodyTypeRequest`
- **Fields** (all optional):
  - `odoo_id` (nullable|string|max:255)
  - `name` (sometimes|required|string|max:255)
  - `media` (nullable|string)
  - `active` (nullable|boolean)

### Delete
- **Endpoint**: `POST /api/odoo/body-types/odoo/{odooId}/delete`
- **Method**: Soft delete

### Restore
- **Endpoint**: `POST /api/odoo/body-types/odoo/{odooId}/restore`
- **Method**: Restore soft-deleted record

---

## 4. Cities

### Create
- **Endpoint**: `POST /api/odoo/cities`
- **Request Class**: `StoreCityRequest`
- **Required Fields**:
  - `name` (required|string|max:255)
- **Optional Fields**:
  - `odoo_id` (nullable|string|max:255|unique:cities,odoo_id)

### Read
- **Endpoint**: `GET /api/odoo/cities`
- **Request Class**: `GetCitiesRequest`
- **Query Parameters**:
  - `search` (optional|string|max:255)
  - `id` (optional|integer|min:1)
  - `odoo_id` (optional|string|max:255)
  - `per_page` (optional|integer|min:1|max:100)
  - `page` (optional|integer|min:1)

### Update
- **Endpoint**: `POST /api/odoo/cities/odoo/{odooId}/update`
- **Request Class**: `UpdateCityRequest`
- **Fields** (all optional):
  - `odoo_id` (sometimes|string|max:255|unique:cities,odoo_id)
  - `name` (sometimes|string|max:255)

### Delete
- **Endpoint**: `POST /api/odoo/cities/odoo/{odooId}/delete`
- **Method**: Soft delete

### Restore
- **Endpoint**: `POST /api/odoo/cities/odoo/{odooId}/restore`
- **Method**: Restore soft-deleted record

---

## 5. Budgets

### Create
- **Endpoint**: `POST /api/odoo/budgets`
- **Request Class**: `StoreBudgetRequest`
- **Required Fields**:
  - `odoo_id` (required|string|unique:budgets,odoo_id)
  - `name` (required|string|max:255)
  - `value` (required|numeric|min:0)
  - `sequence` (required|integer|min:0)
- **Optional Fields**:
  - `is_active` (nullable|boolean)

### Read
- **Endpoint**: `GET /api/odoo/budgets`
- **Request Class**: None (returns all active budgets, ordered)
- **Query Parameters**: None (returns all active budgets)

### Update
- **Endpoint**: `POST /api/odoo/budgets/odoo/{odooId}/update`
- **Request Class**: `UpdateBudgetRequest`
- **Fields** (all optional):
  - `name` (sometimes|required|string|max:255)
  - `value` (sometimes|required|numeric|min:0)
  - `sequence` (sometimes|required|integer|min:0)
  - `is_active` (nullable|boolean)

### Delete
- **Endpoint**: `POST /api/odoo/budgets/odoo/{odooId}/delete`
- **Method**: Soft delete

### Restore
- **Endpoint**: `POST /api/odoo/budgets/odoo/{odooId}/restore`
- **Method**: Restore soft-deleted record

---

## 6. Authorized Agencies

### Create
- **Endpoint**: `POST /api/odoo/authorized-agencies`
- **Request Class**: `StoreAuthorizedAgencyRequest`
- **Required Fields**:
  - `name` (required|string|max:255)
  - `partner_id` (required|string|max:255)
- **Optional Fields**:
  - `media` (nullable - file upload or base64 string, max 5MB, image types: jpeg, png, gif, bmp, webp)

**Note**: Uses `partner_id` instead of `odoo_id` as the identifier.

### Read
- **Endpoint**: `GET /api/odoo/authorized-agencies`
- **Request Class**: `GetAuthorizedAgenciesRequest`
- **Query Parameters**:
  - `search` (optional|string|max:255)
  - `id` (optional|integer|min:1)
  - `odoo_id` (optional|string|max:255)
  - `partner_id` (optional|string|max:255)
  - `per_page` (optional|integer|min:1|max:100)
  - `page` (optional|integer|min:1)

### Update
- **Endpoint**: `POST /api/odoo/authorized-agencies/partner/{partnerId}/update`
- **Request Class**: `UpdateAuthorizedAgencyRequest`
- **Fields** (all optional):
  - `name` (sometimes|required|string|max:255)
  - `partner_id` (sometimes|required|string|max:255)
  - `media` (nullable - file upload or base64 string)

**Note**: Uses `partnerId` in the route instead of `odooId`.

### Delete
- **Endpoint**: `POST /api/odoo/authorized-agencies/partner/{partnerId}/delete`
- **Method**: Soft delete

**Note**: Uses `partnerId` in the route instead of `odooId`.

### Restore
- **Endpoint**: `POST /api/odoo/authorized-agencies/partner/{partnerId}/restore`
- **Method**: Restore soft-deleted record

**Note**: Uses `partnerId` in the route instead of `odooId`.

---

## 7. Carousels

### Create
- **Endpoint**: `POST /api/odoo/carousels`
- **Request Class**: `StoreCarouselRequest`
- **Required Fields**:
  - `title` (required|string|max:255)
  - `start_date` (required|date)
  - `end_date` (required|date|after_or_equal:start_date)
- **Optional Fields**:
  - `odoo_id` (nullable|string|max:255|unique:carousels,odoo_id)
  - `description` (nullable|string)
  - `button_text` (nullable|string|max:255)
  - `link_url` (nullable|url|max:2048)
  - `is_active` (boolean)
  - `sort_order` (integer|min:0)
  - `media` (nullable|string - Base64 image)

### Read
- **Endpoint**: `GET /api/odoo/carousels`
- **Request Class**: `GetCarouselsRequest`
- **Query Parameters**:
  - `search` (nullable|string|max:255)
  - `id` (nullable|integer)
  - `odoo_id` (nullable|string)
  - `active_only` (nullable|boolean)
  - `sort_by` (nullable|string|in:title,start_date,end_date,created_at,sort_order)
  - `sort_order` (nullable|string|in:asc,desc)

### Update
- **Endpoint**: `POST /api/odoo/carousels/odoo/{odooId}/update`
- **Request Class**: `UpdateCarouselRequest`
- **Fields** (all optional):
  - `title` (sometimes|string|max:255)
  - `description` (nullable|string)
  - `button_text` (nullable|string|max:255)
  - `link_url` (nullable|url|max:2048)
  - `start_date` (sometimes|date)
  - `end_date` (sometimes|date|after_or_equal:start_date)
  - `is_active` (boolean)
  - `sort_order` (integer|min:0)
  - `media` (nullable|string - Base64 image)

### Delete
- **Endpoint**: `POST /api/odoo/carousels/odoo/{odooId}/delete`
- **Method**: Soft delete

### Restore
- **Endpoint**: `POST /api/odoo/carousels/odoo/{odooId}/restore`
- **Method**: Restore soft-deleted record

---

## 8. Subscription Plans

### Create
- **Endpoint**: `POST /api/odoo/subscription-plans`
- **Request Class**: `StoreSubscriptionPlanRequest`
- **Required Fields**:
  - `plan_id` (required|integer)
  - `name` (required|string|max:255)
  - `price` (required|numeric|min:0)
  - `billing_cycle` (required|string|in:monthly,yearly,year)
- **Optional Fields**:
  - `description` (nullable|string)
  - `currency` (nullable|string|max:10)
  - `currency_symbol` (nullable|string|max:10)
  - `features` (nullable|array)
  - `max_listings` (nullable|integer|min:0)
  - `max_photos` (nullable|integer|min:0)
  - `subscriber_count` (nullable|integer|min:0)
  - `is_popular` (nullable|boolean)
  - `is_featured` (nullable|boolean)
  - `is_whatsapp_phone` (nullable|boolean)
  - `is_badge` (nullable|boolean)
  - `is_search` (nullable|boolean)
  - `is_similar_cars` (nullable|boolean)
  - `is_dedicated_account_rep` (nullable|boolean)
  - `is_analytics` (nullable|boolean)
  - `is_top_picks` (nullable|boolean)
  - `is_priority_support` (nullable|boolean)
  - `is_you_may_also_like` (nullable|boolean)

### Read
- **Endpoint**: `GET /api/odoo/subscription-plans`
- **Request Class**: `GetSubscriptionPlansRequest`
- **Query Parameters**:
  - `search` (nullable|string|max:255)
  - `id` (nullable|integer|exists:subscription_plans,id)
  - `odoo_id` (nullable|string|exists:subscription_plans,odoo_id)
  - `is_active` (nullable|boolean)
  - `is_popular` (nullable|boolean)
  - `is_featured` (nullable|boolean)
  - `billing_cycle` (nullable|string|in:monthly,yearly,year)

### Update
- **Endpoint**: `POST /api/odoo/subscription-plans/odoo/{odooId}/update`
- **Request Class**: `UpdateSubscriptionPlanRequest`
- **Fields** (all optional):
  - `plan_id` (sometimes|integer)
  - `name` (sometimes|string|max:255)
  - `description` (nullable|string)
  - `price` (sometimes|numeric|min:0)
  - `currency` (nullable|string|max:10)
  - `currency_symbol` (nullable|string|max:10)
  - `billing_cycle` (sometimes|string|in:monthly,yearly,year)
  - `features` (nullable|array)
  - `max_listings` (nullable|integer|min:0)
  - `max_photos` (nullable|integer|min:0)
  - `subscriber_count` (nullable|integer|min:0)
  - `is_popular` (nullable|boolean)
  - `is_featured` (nullable|boolean)
  - `is_whatsapp_phone` (nullable|boolean)
  - `is_badge` (nullable|boolean)
  - `is_search` (nullable|boolean)
  - `is_similar_cars` (nullable|boolean)
  - `is_dedicated_account_rep` (nullable|boolean)
  - `is_analytics` (nullable|boolean)
  - `is_top_picks` (nullable|boolean)
  - `is_priority_support` (nullable|boolean)
  - `is_you_may_also_like` (nullable|boolean)

### Delete
- **Endpoint**: `POST /api/odoo/subscription-plans/odoo/{odooId}/delete`
- **Method**: Soft delete

### Restore
- **Endpoint**: `POST /api/odoo/subscription-plans/odoo/{odooId}/restore`
- **Method**: Restore soft-deleted record

---

## Summary Table

| Entity | Create | Read | Update | Delete | Restore |
|--------|--------|------|--------|--------|---------|
| **Car Brands** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Car Models** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Body Types** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Cities** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Budgets** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Authorized Agencies** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Carousels** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Subscription Plans** | ✅ | ✅ | ✅ | ✅ | ✅ |

## Important Notes

1. **Authentication**: All POST routes require `odoo.tracking` middleware. GET routes are public.

2. **Identifier Pattern**: 
   - Most entities use `odoo_id` as the identifier in routes
   - **Exception**: Authorized Agencies use `partner_id` instead of `odoo_id`

3. **Soft Deletes**: All delete operations are soft deletes, and restore endpoints are available.

4. **Media Handling**: 
   - Car Brands and Authorized Agencies support file uploads or base64 strings for media
   - Carousels support base64 strings for media
   - Body Types support string media (likely base64)

5. **Validation**: Each endpoint has its own Request class with specific validation rules.

6. **Base URL**: All endpoints are prefixed with `/api/odoo`

