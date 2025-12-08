# Laravel Migration Guide - Arabic & Kurdish Translation Fields

This document contains all the new fields added to models that sync with Laravel. Use this to update your Laravel database schema, models, and API endpoints.

---

## 1. Brands (`car-brands` / `fleet.vehicle.model.brand`)

### New Fields:
```php
'name_ar' => 'string',  // Arabic name (nullable)
'name_kr' => 'string',  // Kurdish name (nullable)
```

### Migration Example:
```php
Schema::table('car_brands', function (Blueprint $table) {
    $table->string('name_ar')->nullable()->after('name');
    $table->string('name_kr')->nullable()->after('name_ar');
});
```

### API Sync Payload:
```json
{
    "odoo_id": "123",
    "name": "Toyota",
    "name_ar": "تويوتا",
    "name_kr": "تۆیۆتا",
    "media": "base64_image_data"
}
```

---

## 2. Models (`car-models` / `fleet.vehicle.model`)

### New Fields:
```php
'name_ar' => 'string',  // Arabic name (nullable)
'name_kr' => 'string',  // Kurdish name (nullable)
```

### Migration Example:
```php
Schema::table('car_models', function (Blueprint $table) {
    $table->string('name_ar')->nullable()->after('name');
    $table->string('name_kr')->nullable()->after('name_ar');
});
```

### API Sync Payload:
```json
{
    "odoo_id": "456",
    "name": "Camry",
    "name_ar": "كامري",
    "name_kr": "کامری",
    "car_brand_id": 1
}
```

---

## 3. States/Cities (`cities` / `res.country.state`)

### New Fields:
```php
'name_ar' => 'string',  // Arabic name (nullable)
'name_kr' => 'string',  // Kurdish name (nullable)
```

### Migration Example:
```php
Schema::table('cities', function (Blueprint $table) {
    $table->string('name_ar')->nullable()->after('name');
    $table->string('name_kr')->nullable()->after('name_ar');
});
```

### API Sync Payload:
```json
{
    "odoo_id": "111",
    "name": "Baghdad",
    "name_ar": "بغداد",
    "name_kr": "بەغدا"
}
```

---

## 4. Body Types (`body-types` / `fleet.vehicle.model.bodytype`)

### New Fields:
```php
'name_ar' => 'string',  // Arabic name (nullable)
'name_kr' => 'string',  // Kurdish name (nullable)
```

### Migration Example:
```php
Schema::table('body_types', function (Blueprint $table) {
    $table->string('name_ar')->nullable()->after('name');
    $table->string('name_kr')->nullable()->after('name_ar');
});
```

### API Sync Payload:
```json
{
    "odoo_id": "9",
    "name": "Sedan",
    "name_ar": "سيدان",
    "name_kr": "سێدان",
    "active": true,
    "media": "base64_image_data"
}
```

---

## 5. Subscription Plans (`subscription-plans` / `subscription.plan`)

### New Fields:
```php
'name_ar' => 'string',           // Arabic name (nullable)
'name_kr' => 'string',           // Kurdish name (nullable)
'description_ar' => 'text',      // Arabic description (nullable)
'description_kr' => 'text',      // Kurdish description (nullable)
```

### Migration Example:
```php
Schema::table('subscription_plans', function (Blueprint $table) {
    $table->string('name_ar')->nullable()->after('name');
    $table->string('name_kr')->nullable()->after('name_ar');
    $table->text('description_ar')->nullable()->after('description');
    $table->text('description_kr')->nullable()->after('description_ar');
});
```

### API Sync Payload:
```json
{
    "plan_id": 1,
    "name": "Starter Plan",
    "name_ar": "الخطة الأساسية",
    "name_kr": "پلانی سەرەکی",
    "description": "Perfect for individual sellers",
    "description_ar": "مثالي للبائعين الأفراد",
    "description_kr": "بۆ فرۆشیارە تاکەکان بەباشترین شێوە",
    "price": 50.0,
    "currency": "USD",
    "currency_symbol": "$",
    "billing_cycle": "month",
    "max_listings": 5,
    "max_photos": 10,
    "subscriber_count": 0,
    "is_featured": false,
    "is_popular": true,
    "is_whatsapp_phone": true,
    "is_badge": true,
    "is_search": false,
    "is_similar_cars": true,
    "is_dedicated_account_rep": false,
    "is_analytics": false,
    "is_top_picks": true,
    "is_priority_support": false,
    "is_you_may_also_like": true,
    "features": [...]
}
```

---

## 6. Budgets (`budgets` / `budget`)

### New Fields:
```php
'name_ar' => 'string',  // Arabic name (nullable)
'name_kr' => 'string',  // Kurdish name (nullable)
```

### Migration Example:
```php
Schema::table('budgets', function (Blueprint $table) {
    $table->string('name_ar')->nullable()->after('name');
    $table->string('name_kr')->nullable()->after('name_ar');
});
```

### API Sync Payload:
```json
{
    "odoo_id": "1",
    "name": "Under 10K",
    "name_ar": "أقل من 10 ألف",
    "name_kr": "کەمتر لە 10 هەزار",
    "value": 10000,
    "is_active": true,
    "sequence": 1
}
```

---

## 7. Carousels (`carousels` / `carousel`)

### New Fields:
```php
'title_ar' => 'string',           // Arabic title (nullable)
'title_kr' => 'string',           // Kurdish title (nullable)
'description_ar' => 'text',       // Arabic description (nullable)
'description_kr' => 'text',        // Kurdish description (nullable)
'button_text_ar' => 'string',     // Arabic button text (nullable)
'button_text_kr' => 'string',     // Kurdish button text (nullable)
```

### Migration Example:
```php
Schema::table('carousels', function (Blueprint $table) {
    $table->string('title_ar')->nullable()->after('title');
    $table->string('title_kr')->nullable()->after('title_ar');
    $table->text('description_ar')->nullable()->after('description');
    $table->text('description_kr')->nullable()->after('description_ar');
    $table->string('button_text_ar')->nullable()->after('button_text');
    $table->string('button_text_kr')->nullable()->after('button_text_ar');
});
```

### API Sync Payload:
```json
{
    "odoo_id": "123",
    "title": "Special Offer",
    "title_ar": "عرض خاص",
    "title_kr": "پێشکەشی تایبەت",
    "description": "Get 20% off on all cars",
    "description_ar": "احصل على خصم 20٪ على جميع السيارات",
    "description_kr": "20% کەمکردنەوە لە هەموو ئۆتۆمبێلەکان",
    "media": "base64_image_data",
    "button_text": "Shop Now",
    "button_text_ar": "تسوق الآن",
    "button_text_kr": "ئێستا بفرۆشە",
    "link_url": "https://example.com",
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "is_active": true,
    "sort_order": 1
}
```

---

## 8. Authorized Agencies (`authorized-agencies` / `authorized.agency`)

### New Fields:
```php
'name_ar' => 'string',  // Arabic name (nullable)
'name_kr' => 'string',  // Kurdish name (nullable)
```

### Migration Example:
```php
Schema::table('authorized_agencies', function (Blueprint $table) {
    $table->string('name_ar')->nullable()->after('name');
    $table->string('name_kr')->nullable()->after('name_ar');
});
```

### API Sync Payload:
```json
{
    "name": "Premium Auto Dealer",
    "name_ar": "تاجر السيارات المميز",
    "name_kr": "فرۆشیاری ئۆتۆمبێلی پێشەنگ",
    "partner_id": "123",
    "media": "base64_image_data"
}
```

---

## Complete Laravel Migration File

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Car Brands
        Schema::table('car_brands', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Car Models
        Schema::table('car_models', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Cities/States
        Schema::table('cities', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Body Types
        Schema::table('body_types', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Subscription Plans
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('description_kr')->nullable()->after('description_ar');
        });

        // Budgets
        Schema::table('budgets', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Carousels
        Schema::table('carousels', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->string('title_kr')->nullable()->after('title_ar');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('description_kr')->nullable()->after('description_ar');
            $table->string('button_text_ar')->nullable()->after('button_text');
            $table->string('button_text_kr')->nullable()->after('button_text_ar');
        });

        // Authorized Agencies
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

## Laravel Model Fillable Fields Update

### Example for CarBrand Model:
```php
protected $fillable = [
    'odoo_id',
    'name',
    'name_ar',      // NEW
    'name_kr',      // NEW
    'media',
    // ... other fields
];
```

### Example for SubscriptionPlan Model:
```php
protected $fillable = [
    'plan_id',
    'name',
    'name_ar',          // NEW
    'name_kr',          // NEW
    'description',
    'description_ar',   // NEW
    'description_kr',   // NEW
    'price',
    // ... other fields
];
```

### Example for Carousel Model:
```php
protected $fillable = [
    'odoo_id',
    'title',
    'title_ar',             // NEW
    'title_kr',             // NEW
    'description',
    'description_ar',       // NEW
    'description_kr',       // NEW
    'button_text',
    'button_text_ar',       // NEW
    'button_text_kr',       // NEW
    'media',
    'link_url',
    // ... other fields
];
```

---

## API Endpoint Updates

### Update your Odoo sync endpoints to accept these new fields:

**Example Route Handler:**
```php
// In your OdooSyncController or similar

public function syncCarBrand(Request $request)
{
    $validated = $request->validate([
        'odoo_id' => 'required|string',
        'name' => 'required|string',
        'name_ar' => 'nullable|string',      // NEW
        'name_kr' => 'nullable|string',      // NEW
        'media' => 'nullable|string',
    ]);

    CarBrand::updateOrCreate(
        ['odoo_id' => $validated['odoo_id']],
        [
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'] ?? null,  // NEW
            'name_kr' => $validated['name_kr'] ?? null,  // NEW
            'media' => $validated['media'] ?? null,
        ]
    );

    return response()->json(['success' => true]);
}
```

---

## Public API Response Updates

### Update your public API responses to include these fields:

**Example Response for Brands:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Toyota",
            "name_ar": "تويوتا",
            "name_kr": "تۆیۆتا",
            "image": "base64_or_url"
        }
    ]
}
```

**Example Response for Subscription Plans:**
```json
{
    "success": true,
    "data": [
        {
            "plan_id": 1,
            "name": "Starter Plan",
            "name_ar": "الخطة الأساسية",
            "name_kr": "پلانی سەرەکی",
            "description": "Perfect for individual sellers",
            "description_ar": "مثالي للبائعين الأفراد",
            "description_kr": "بۆ فرۆشیارە تاکەکان بەباشترین شێوە",
            "price": 50.0,
            // ... other fields
        }
    ]
}
```

---

## Important Notes

1. **All new fields are nullable** - They default to empty string (`''`) in Odoo if not set, but should be `null` in Laravel if empty.

2. **Field Naming Convention:**
   - Arabic fields: `{field_name}_ar`
   - Kurdish fields: `{field_name}_kr`

3. **Update All Sync Endpoints:**
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

4. **Update Public API Endpoints:**
   - All GET endpoints that return these models should include the new translation fields.

5. **Database Indexes (Optional):**
   ```php
   // If you need to search by translations
   $table->index('name_ar');
   $table->index('name_kr');
   ```

---

## Testing Checklist

- [ ] Run migrations
- [ ] Update all model fillable arrays
- [ ] Update all sync endpoint validations
- [ ] Update all sync endpoint handlers
- [ ] Update all public API responses
- [ ] Test sync from Odoo (create, update, delete)
- [ ] Test public API responses include new fields
- [ ] Verify null/empty string handling
- [ ] Test with Arabic and Kurdish text input

---

## Summary of All New Fields

| Model | Fields Added |
|-------|-------------|
| Car Brands | `name_ar`, `name_kr` |
| Car Models | `name_ar`, `name_kr` |
| Cities/States | `name_ar`, `name_kr` |
| Body Types | `name_ar`, `name_kr` |
| Subscription Plans | `name_ar`, `name_kr`, `description_ar`, `description_kr` |
| Budgets | `name_ar`, `name_kr` |
| Carousels | `title_ar`, `title_kr`, `description_ar`, `description_kr`, `button_text_ar`, `button_text_kr` |
| Authorized Agencies | `name_ar`, `name_kr` |

**Total: 20 new fields across 8 models**

