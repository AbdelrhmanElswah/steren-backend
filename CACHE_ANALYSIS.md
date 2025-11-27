# Cache Analysis for API Endpoints

This document provides a comprehensive analysis of cache usage across all API endpoints in the project.

## Summary

### APIs WITH Cache ✅
1. **Odoo GET Endpoints:**
   - `GET /api/odoo/brands` - CarBrandController::getBrands() - 24h cache ✅
   - `GET /api/odoo/body-types` - BodyTypeController::getBodyTypes() - 24h cache ✅
   - `GET /api/odoo/car-models` - CarModelController::getCarModels() - 24h cache ✅
   - `GET /api/odoo/authorized-agencies` - AuthorizedAgencyController::getAuthorizedAgencies() - 24h cache ✅

2. **Car Listing Endpoints:**
   - `GET /api/car-listings` - CarListingController::getCarListings() - 5min cache ✅
   - `GET /api/car-listings/studio/{studioId}` - CarListingController::getCarListingsByStudio() - 5min cache ✅
   - Multiple other car listing endpoints with 5min cache ✅

3. **Other Endpoints:**
   - `GET /api/you-may-also-like` - CarListingDetailsController::getYouMayAlsoLike() - 1h cache ✅
   - `GET /api/subscription-based-listings/top-picks` - SubscriptionBasedListingController - 1h cache ✅
   - `GET /api/watch-list` - WatchListController - 30min cache ✅
   - `GET /api/carousels` - Data\CarouselController - 24h cache ✅
   - SearchService methods - Various cache durations (5min-1h) ✅
   - SimilarCarsService methods - 10-15min cache ✅

### APIs WITHOUT Cache ❌ (Need to Add)
1. **Odoo GET Endpoints:**
   - `GET /api/odoo/cities` - CityController::getCities() ❌
   - `GET /api/odoo/budgets` - BudgetController::getBudgets() ❌
   - `GET /api/odoo/carousels` - CarouselController::getCarousels() ❌
   - `GET /api/odoo/subscription-plans` - SubscriptionPlanController::getSubscriptionPlans() ❌

2. **Other Endpoints:**
   - Various Data controller endpoints (TransmissionOptions, FuelTypeOptions, etc.) - Most don't have cache ❌

## Cache Duration Recommendations

### Odoo GET Endpoints (Reference Data)
- **Duration**: 24 hours (86400 seconds)
- **Reason**: These are reference data that don't change frequently
- **Cache Key**: Should include all filter parameters

### Car Listing Endpoints
- **Duration**: 5 minutes (300 seconds)
- **Reason**: Listings change frequently, but 5min cache reduces database load
- **Cache Key**: Should include all filter parameters and pagination

### User-Specific Endpoints
- **Duration**: 30 minutes - 1 hour (1800-3600 seconds)
- **Reason**: Personalized data that changes based on user preferences
- **Cache Key**: Should include user ID

### Search Endpoints
- **Duration**: 5-10 minutes (300-600 seconds)
- **Reason**: Search results should be relatively fresh
- **Cache Key**: Should include search query and filters

## Cache Key Patterns

### Good Patterns ✅
```php
// Includes all relevant parameters
$cacheKey = 'odoo_brands_' . md5(serialize($request->only(['search', 'id', 'odoo_id'])));
```

### Issues Found ⚠️
1. **BodyTypeController**: Cache key doesn't include `is_active` filter (FIXED in recent changes)
2. **CarBrandController**: Cache key doesn't include `is_active` if it exists
3. **Missing cache keys**: Several endpoints don't have cache at all

## Recommendations

### High Priority
1. ✅ Add cache to all Odoo GET endpoints (cities, budgets, carousels, subscription-plans)
2. ✅ Ensure all cache keys include all filter parameters (including is_active)

### Medium Priority
1. Review and standardize cache durations
2. Add cache invalidation on POST/PUT/DELETE operations
3. Consider cache tags for better invalidation

### Low Priority
1. Add cache to Data controller endpoints
2. Consider Redis for distributed caching
3. Add cache warming strategies

## Cache Invalidation

Currently, cache invalidation is handled in:
- CarouselService - Invalidates carousel cache on create/update/delete
- SimilarCarsService - Flushes cache on listing updates
- SearchService - Forgets specific cache keys

**Recommendation**: Implement a more systematic cache invalidation strategy.

