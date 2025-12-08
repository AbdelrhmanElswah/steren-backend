# Laravel Quick Reference - Copy & Paste Code

## 1. Migration File (Run: `php artisan make:migration add_translation_fields_to_all_models`)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('car_brands', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        Schema::table('car_models', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        Schema::table('body_types', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('description_kr')->nullable()->after('description_ar');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        Schema::table('carousels', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->string('title_kr')->nullable()->after('title_ar');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('description_kr')->nullable()->after('description_ar');
            $table->string('button_text_ar')->nullable()->after('button_text');
            $table->string('button_text_kr')->nullable()->after('button_text_ar');
        });

        Schema::table('authorized_agencies', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });
    }

    public function down()
    {
        Schema::table('car_brands', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });
        Schema::table('car_models', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });
        Schema::table('body_types', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr', 'description_ar', 'description_kr']);
        });
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });
        Schema::table('carousels', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'title_kr', 'description_ar', 'description_kr', 'button_text_ar', 'button_text_kr']);
        });
        Schema::table('authorized_agencies', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });
    }
};
```

---

## 2. Model Fillable Updates

### CarBrand.php
```php
protected $fillable = [
    'odoo_id',
    'name',
    'name_ar',      // ADD THIS
    'name_kr',     // ADD THIS
    'media',
];
```

### CarModel.php
```php
protected $fillable = [
    'odoo_id',
    'name',
    'name_ar',      // ADD THIS
    'name_kr',      // ADD THIS
    'car_brand_id',
];
```

### City.php (or State.php)
```php
protected $fillable = [
    'odoo_id',
    'name',
    'name_ar',      // ADD THIS
    'name_kr',      // ADD THIS
    'code',
    'country_id',
];
```

### BodyType.php
```php
protected $fillable = [
    'odoo_id',
    'name',
    'name_ar',      // ADD THIS
    'name_kr',      // ADD THIS
    'active',
    'media',
];
```

### SubscriptionPlan.php
```php
protected $fillable = [
    'plan_id',
    'name',
    'name_ar',          // ADD THIS
    'name_kr',          // ADD THIS
    'description',
    'description_ar',   // ADD THIS
    'description_kr',   // ADD THIS
    'price',
    'currency',
    'currency_symbol',
    'billing_cycle',
    'max_listings',
    'max_photos',
    // ... rest of fields
];
```

### Budget.php
```php
protected $fillable = [
    'odoo_id',
    'name',
    'name_ar',      // ADD THIS
    'name_kr',      // ADD THIS
    'value',
    'is_active',
    'sequence',
];
```

### Carousel.php
```php
protected $fillable = [
    'odoo_id',
    'title',
    'title_ar',             // ADD THIS
    'title_kr',             // ADD THIS
    'description',
    'description_ar',       // ADD THIS
    'description_kr',       // ADD THIS
    'button_text',
    'button_text_ar',       // ADD THIS
    'button_text_kr',       // ADD THIS
    'media',
    'link_url',
    'start_date',
    'end_date',
    'is_active',
    'sort_order',
];
```

### AuthorizedAgency.php
```php
protected $fillable = [
    'name',
    'name_ar',      // ADD THIS
    'name_kr',      // ADD THIS
    'partner_id',
    'media',
];
```

---

## 3. Sync Endpoint Validation Rules

### CarBrand Sync Endpoint
```php
$validated = $request->validate([
    'odoo_id' => 'required|string',
    'name' => 'required|string',
    'name_ar' => 'nullable|string',      // ADD THIS
    'name_kr' => 'nullable|string',      // ADD THIS
    'media' => 'nullable|string',
]);
```

### CarModel Sync Endpoint
```php
$validated = $request->validate([
    'odoo_id' => 'required|string',
    'name' => 'required|string',
    'name_ar' => 'nullable|string',      // ADD THIS
    'name_kr' => 'nullable|string',      // ADD THIS
    'car_brand_id' => 'nullable|integer',
]);
```

### City Sync Endpoint
```php
$validated = $request->validate([
    'odoo_id' => 'required|string',
    'name' => 'required|string',
    'name_ar' => 'nullable|string',      // ADD THIS
    'name_kr' => 'nullable|string',      // ADD THIS
]);
```

### BodyType Sync Endpoint
```php
$validated = $request->validate([
    'odoo_id' => 'required|string',
    'name' => 'required|string',
    'name_ar' => 'nullable|string',      // ADD THIS
    'name_kr' => 'nullable|string',      // ADD THIS
    'active' => 'nullable|boolean',
    'media' => 'nullable|string',
]);
```

### SubscriptionPlan Sync Endpoint
```php
$validated = $request->validate([
    'plan_id' => 'required|integer',
    'name' => 'required|string',
    'name_ar' => 'nullable|string',          // ADD THIS
    'name_kr' => 'nullable|string',          // ADD THIS
    'description' => 'nullable|string',
    'description_ar' => 'nullable|string',   // ADD THIS
    'description_kr' => 'nullable|string',   // ADD THIS
    'price' => 'required|numeric',
    // ... rest of fields
]);
```

### Budget Sync Endpoint
```php
$validated = $request->validate([
    'odoo_id' => 'required|string',
    'name' => 'required|string',
    'name_ar' => 'nullable|string',      // ADD THIS
    'name_kr' => 'nullable|string',      // ADD THIS
    'value' => 'required|numeric',
    'is_active' => 'nullable|boolean',
    'sequence' => 'nullable|integer',
]);
```

### Carousel Sync Endpoint
```php
$validated = $request->validate([
    'odoo_id' => 'required|string',
    'title' => 'required|string',
    'title_ar' => 'nullable|string',             // ADD THIS
    'title_kr' => 'nullable|string',             // ADD THIS
    'description' => 'required|string',
    'description_ar' => 'nullable|string',        // ADD THIS
    'description_kr' => 'nullable|string',        // ADD THIS
    'button_text' => 'required|string',
    'button_text_ar' => 'nullable|string',        // ADD THIS
    'button_text_kr' => 'nullable|string',        // ADD THIS
    'media' => 'nullable|string',
    'link_url' => 'nullable|string',
    'start_date' => 'required|date',
    'end_date' => 'required|date',
    'is_active' => 'nullable|boolean',
    'sort_order' => 'nullable|integer',
]);
```

### AuthorizedAgency Sync Endpoint
```php
$validated = $request->validate([
    'name' => 'required|string',
    'name_ar' => 'nullable|string',      // ADD THIS
    'name_kr' => 'nullable|string',      // ADD THIS
    'partner_id' => 'required|string',
    'media' => 'nullable|string',
]);
```

---

## 4. Sync Endpoint Handler Example

```php
public function syncCarBrand(Request $request)
{
    $validated = $request->validate([
        'odoo_id' => 'required|string',
        'name' => 'required|string',
        'name_ar' => 'nullable|string',
        'name_kr' => 'nullable|string',
        'media' => 'nullable|string',
    ]);

    CarBrand::updateOrCreate(
        ['odoo_id' => $validated['odoo_id']],
        [
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'] ?? null,
            'name_kr' => $validated['name_kr'] ?? null,
            'media' => $validated['media'] ?? null,
        ]
    );

    return response()->json(['success' => true]);
}
```

---

## 5. Public API Response Example

```php
public function getBrands(Request $request)
{
    $brands = CarBrand::all();
    
    return response()->json([
        'success' => true,
        'data' => $brands->map(function ($brand) {
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'name_ar' => $brand->name_ar,      // ADD THIS
                'name_kr' => $brand->name_kr,      // ADD THIS
                'image' => $brand->media,
            ];
        })
    ]);
}
```

---

## 6. All Endpoints That Need Updates

### Sync Endpoints (POST):
- `/api/odoo/car-brands`
- `/api/odoo/car-brands/odoo/{id}/update`
- `/api/odoo/car-models`
- `/api/odoo/car-models/odoo/{id}/update`
- `/api/odoo/cities`
- `/api/odoo/cities/odoo/{id}/update`
- `/api/odoo/body-types`
- `/api/odoo/body-types/odoo/{id}/update`
- `/api/odoo/subscription-plans`
- `/api/odoo/subscription-plans/odoo/{id}/update`
- `/api/odoo/budgets`
- `/api/odoo/budgets/odoo/{id}/update`
- `/api/odoo/carousels`
- `/api/odoo/carousels/odoo/{id}/update`
- `/api/odoo/authorized-agencies`
- `/api/odoo/authorized-agencies/odoo/{id}/update`

### Public API Endpoints (GET):
- `/api/brands`
- `/api/brands/{id}`
- `/api/models`
- `/api/models/{id}`
- `/api/states`
- `/api/states/{id}`
- `/api/bodytypes`
- `/api/bodytypes/{id}`
- `/api/steren/subscription-plans`
- `/api/budgets`
- `/api/carousel`
- `/api/authorized-agencies`
- `/api/authorized-agencies/{id}`

---

## Quick Summary Table

| Model | New Fields | Type |
|-------|-----------|------|
| CarBrand | `name_ar`, `name_kr` | string |
| CarModel | `name_ar`, `name_kr` | string |
| City | `name_ar`, `name_kr` | string |
| BodyType | `name_ar`, `name_kr` | string |
| SubscriptionPlan | `name_ar`, `name_kr`, `description_ar`, `description_kr` | string, text |
| Budget | `name_ar`, `name_kr` | string |
| Carousel | `title_ar`, `title_kr`, `description_ar`, `description_kr`, `button_text_ar`, `button_text_kr` | string, text |
| AuthorizedAgency | `name_ar`, `name_kr` | string |

**Total: 20 new nullable fields**

