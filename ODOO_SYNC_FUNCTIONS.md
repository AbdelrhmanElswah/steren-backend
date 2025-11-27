# Odoo Sync Functions & APIs

This document lists all APIs, functions, and commands that fetch data from Odoo and populate local database tables.

---

## 📋 Overview

The application has multiple ways to sync data from Odoo ERP to local database:

1. **Database Seeders** - Fetch and populate tables during seeding
2. **Console Commands** - Reusable commands to sync specific entities
3. **Service Methods** - Programmatic sync functions
4. **API Endpoints** - GET endpoints that fetch from Odoo (read-only)

---

## 🗄️ Database Seeders

These seeders fetch data from Odoo API and populate local database tables. Run them using:
```bash
php artisan db:seed --class=SeederName
```

### 1. **CarBrandSeeder**
**File**: `database/seeders/CarBrandSeeder.php`

**Odoo Endpoint**: `POST /api/brands`

**What it does**:
- Fetches all car brands from Odoo
- Creates/updates `car_brands` table
- Handles brand logos (base64 to image conversion)
- Uses `updateOrCreate` with `odoo_id` as unique identifier

**Usage**:
```bash
php artisan db:seed --class=CarBrandSeeder
```

**Table**: `car_brands`
**Fields synced**: `odoo_id`, `name`, `image` (via media table)

---

### 2. **CarModelSeeder**
**File**: `database/seeders/CarModelSeeder.php`

**Odoo Endpoint**: `POST /api/models`

**What it does**:
- Fetches car models from Odoo with pagination (100 per page)
- Creates/updates `car_models` table
- Links models to brands using `brand_id` (Odoo ID)
- Handles pagination automatically

**Usage**:
```bash
php artisan db:seed --class=CarModelSeeder
```

**Table**: `car_models`
**Fields synced**: `odoo_id`, `name`, `car_brand_id` (linked via odoo_id)

**Note**: Requires brands to be seeded first (models reference brands)

---

### 3. **BudgetSeeder**
**File**: `database/seeders/BudgetSeeder.php`

**Odoo Endpoint**: `GET /api/budgets`

**What it does**:
- Fetches budget ranges from Odoo
- Creates/updates `budgets` table
- Has fallback default budgets if API fails

**Usage**:
```bash
php artisan db:seed --class=BudgetSeeder
```

**Table**: `budgets`
**Fields synced**: `odoo_id`, `name`, `value`, `sequence`, `is_active`

---

### 4. **SubscriptionPlanSeeder**
**File**: `database/seeders/SubscriptionPlanSeeder.php`

**Odoo Endpoint**: `GET /api/steren/subscription-plans`

**What it does**:
- Fetches subscription plans from Odoo
- Creates/updates `subscription_plans` table
- Syncs all plan features and settings

**Usage**:
```bash
php artisan db:seed --class=SubscriptionPlanSeeder
```

**Table**: `subscription_plans`
**Fields synced**: 
- `odoo_id`, `name`, `description`, `price`, `currency`
- `billing_cycle`, `features`, `max_listings`, `max_photos`
- `is_active`, `is_popular`, `is_featured`
- All feature flags (is_whatsapp_phone, is_badge, is_search, etc.)

---

### 5. **BodyTypeSeeder**
**File**: `database/seeders/BodyTypeSeeder.php`

**Odoo Endpoint**: `POST /api/bodytypes`

**What it does**:
- Fetches body types from Odoo
- Creates/updates `body_types` table
- Handles body type icons (base64 to image conversion)
- Has fallback default body types if API fails

**Usage**:
```bash
php artisan db:seed --class=BodyTypeSeeder
```

**Table**: `body_types`
**Fields synced**: `odoo_id`, `name`, `image` (via media table)

---

### 6. **CarouselSeeder**
**File**: `database/seeders/CarouselSeeder.php`

**Odoo Endpoint**: `GET /api/carousel`

**What it does**:
- Fetches carousels/banners from Odoo
- Creates/updates `carousels` table
- Handles carousel images (base64 to image conversion)

**Usage**:
```bash
php artisan db:seed --class=CarouselSeeder
```

**Table**: `carousels`
**Fields synced**: 
- `odoo_id`, `title`, `description`, `button_text`
- `link_url`, `start_date`, `end_date`, `is_active`, `sort_order`
- `image` (via media table)

---

### 7. **AuthorizedAgencySeeder**
**File**: `database/seeders/AuthorizedAgencySeeder.php`

**Odoo Endpoint**: (Check seeder file for exact endpoint)

**What it does**:
- Fetches authorized agencies from Odoo
- Creates/updates `authorized_agencies` table

**Usage**:
```bash
php artisan db:seed --class=AuthorizedAgencySeeder
```

**Table**: `authorized_agencies`

---

## 🔧 Console Commands

Reusable commands to sync specific entities from Odoo.

### 1. **SyncBudgetsFromOdoo**
**File**: `app/Console/Commands/SyncBudgetsFromOdoo.php`

**Command**: 
```bash
php artisan odoo:sync-budgets
```

**Odoo Endpoint**: `GET /api/budgets`

**What it does**:
- Fetches budgets from Odoo API
- Updates or creates records in `budgets` table
- Uses `updateOrCreate` with `odoo_id` as unique identifier
- Provides detailed sync summary (created, updated, errors)

**Output**:
```
Starting budget sync from Odoo...
Found 7 budgets
✓ Created: Under 10K
✓ Updated: 10K - 20K
...
=== Sync Summary ===
Created: 2
Updated: 5
Total: 7
Budget sync completed successfully!
```

**Table**: `budgets`

---

## 🔌 Service Methods

Programmatic functions to sync data from Odoo.

### 1. **SubscriptionPlanService::syncFromOdoo()**
**File**: `app/Services/Odoo/SubscriptionPlanService.php`

**Method**: `syncFromOdoo(array $odooPlans): array`

**What it does**:
- Takes array of Odoo subscription plan data
- Creates or updates subscription plans in database
- Returns sync summary with created/updated counts and errors

**Usage**:
```php
use App\Services\Odoo\SubscriptionPlanService;

$service = new SubscriptionPlanService();
$result = $service->syncFromOdoo($odooPlansArray);

// Result structure:
// [
//     'synced' => [...],      // Array of synced plans
//     'errors' => [...],       // Array of errors
//     'total_synced' => 5,     // Count of synced
//     'total_errors' => 0      // Count of errors
// ]
```

**Table**: `subscription_plans`

---

## 🌐 API Endpoints (Read-Only)

These GET endpoints fetch data from local database (which was previously synced from Odoo). They don't directly call Odoo, but serve the synced data.

**Base Path**: `/api/odoo`

### Available Endpoints:

| Endpoint | Controller Method | Table |
|----------|------------------|-------|
| `GET /api/odoo/brands` | `CarBrandController::getBrands()` | `car_brands` |
| `GET /api/odoo/body-types` | `BodyTypeController::getBodyTypes()` | `body_types` |
| `GET /api/odoo/cities` | `CityController::getCities()` | `cities` |
| `GET /api/odoo/car-models` | `CarModelController::getCarModels()` | `car_models` |
| `GET /api/odoo/subscription-plans` | `SubscriptionPlanController::getSubscriptionPlans()` | `subscription_plans` |
| `GET /api/odoo/authorized-agencies` | `AuthorizedAgencyController::getAuthorizedAgencies()` | `authorized_agencies` |
| `GET /api/odoo/budgets` | `BudgetController::getBudgets()` | `budgets` |

**Note**: These endpoints read from local database, not directly from Odoo. To get fresh data, run the seeders or sync commands first.

---

## 📝 How to Use

### Option 1: Run Individual Seeders
```bash
# Sync car brands
php artisan db:seed --class=CarBrandSeeder

# Sync car models (requires brands to be synced first)
php artisan db:seed --class=CarModelSeeder

# Sync budgets
php artisan db:seed --class=BudgetSeeder

# Sync subscription plans
php artisan db:seed --class=SubscriptionPlanSeeder

# Sync body types
php artisan db:seed --class=BodyTypeSeeder

# Sync carousels
php artisan db:seed --class=CarouselSeeder
```

### Option 2: Run Console Commands
```bash
# Sync budgets using dedicated command
php artisan odoo:sync-budgets
```

### Option 3: Use Service Methods Programmatically
```php
use App\Services\Odoo\SubscriptionPlanService;

$service = new SubscriptionPlanService();

// Fetch from Odoo API first
$response = Http::get(config('services.odoo.base_url') . '/api/steren/subscription-plans');
$odooPlans = $response->json()['result']['data'];

// Sync to database
$result = $service->syncFromOdoo($odooPlans);
```

---

## 🔄 Sync Pattern

All sync functions follow this pattern:

1. **Fetch from Odoo**: Make HTTP request to Odoo API endpoint
2. **Parse Response**: Extract data from `result.data` or `data` array
3. **Map Data**: Transform Odoo data format to local model format
4. **Update or Create**: Use `updateOrCreate()` with `odoo_id` as unique key
5. **Handle Media**: If images are present, convert base64 to files and store
6. **Error Handling**: Log errors and continue with remaining items

### Example Pattern:
```php
$response = Http::get($odooUrl . '/api/endpoint');
$data = $response->json();

$items = $data['result']['data'] ?? $data['data'] ?? [];

foreach ($items as $item) {
    Model::updateOrCreate(
        ['odoo_id' => $item['id']],
        [
            'odoo_id' => $item['id'],
            'name' => $item['name'],
            // ... other fields
        ]
    );
}
```

---

## ⚙️ Configuration

**All sync functions use the same base URL** configured in `config/services.php`:

```php
'odoo' => [
    'base_url' => env('ODOO_BASE_URL', 'https://odoo-clients.teleoceans.cloud'),
    'database' => env('ODOO_DATABASE', ''),
    'username' => env('ODOO_USERNAME', ''),
    'password' => env('ODOO_PASSWORD', ''),
    'api_key' => env('ODOO_API_KEY', ''),
],
```

### Base URL
- **Default**: `https://odoo-clients.teleoceans.cloud`
- **Configuration**: Set `ODOO_BASE_URL` in your `.env` file
- **All seeders/commands use**: `config('services.odoo.base_url')`

### How It's Used
All sync functions construct URLs like this:
```php
config('services.odoo.base_url') . '/api/brands'
config('services.odoo.base_url') . '/api/models'
config('services.odoo.base_url') . '/api/budgets'
// etc.
```

### Environment Variables
Set these in your `.env` file:
```env
ODOO_BASE_URL=https://odoo-clients.teleoceans.cloud
ODOO_DATABASE=your_database
ODOO_USERNAME=your_username
ODOO_PASSWORD=your_password
ODOO_API_KEY=your_api_key
```

### Current Base URL
The default base URL is: **`https://odoo-clients.teleoceans.cloud`**

To change it, update your `.env` file:
```env
ODOO_BASE_URL=https://your-custom-odoo-instance.com
```

---

## 📊 Summary Table

| Entity | Seeder | Command | Service Method | API Endpoint | Table |
|--------|--------|---------|----------------|--------------|-------|
| Car Brands | ✅ | ❌ | ❌ | ✅ | `car_brands` |
| Car Models | ✅ | ❌ | ❌ | ✅ | `car_models` |
| Budgets | ✅ | ✅ | ❌ | ✅ | `budgets` |
| Subscription Plans | ✅ | ❌ | ✅ | ✅ | `subscription_plans` |
| Body Types | ✅ | ❌ | ❌ | ✅ | `body_types` |
| Carousels | ✅ | ❌ | ❌ | ❌ | `carousels` |
| Authorized Agencies | ✅ | ❌ | ❌ | ✅ | `authorized_agencies` |
| Cities | ❌ | ❌ | ❌ | ✅ | `cities` |

---

## 🚀 Creating New Sync Functions

To create a new sync function for a new entity:

1. **Create a Seeder** (recommended):
   ```php
   class NewEntitySeeder extends Seeder
   {
       public function run(): void
       {
           $response = Http::get(config('services.odoo.base_url') . '/api/new-entity');
           $data = $response->json()['result']['data'];
           
           foreach ($data as $item) {
               NewEntity::updateOrCreate(
                   ['odoo_id' => $item['id']],
                   [
                       'odoo_id' => $item['id'],
                       'name' => $item['name'],
                       // ... other fields
                   ]
               );
           }
       }
   }
   ```

2. **Or Create a Console Command**:
   ```php
   class SyncNewEntityFromOdoo extends Command
   {
       protected $signature = 'odoo:sync-new-entity';
       
       public function handle()
       {
           // Similar logic to seeder
       }
   }
   ```

---

## 📝 Notes

- All sync functions use `odoo_id` as the unique identifier to match records
- `updateOrCreate()` ensures idempotency (can run multiple times safely)
- Most seeders handle base64 image conversion automatically
- Error handling is built-in with logging
- Some seeders have fallback default data if Odoo API fails
- Car models require brands to be synced first (foreign key dependency)

---

**Last Updated**: 2025  
**Framework**: Laravel 12.0

