# Steren Backend API Documentation

## 📋 Table of Contents

1. [Project Overview](#project-overview)
2. [Core Functionality](#core-functionality)
3. [API Endpoints](#api-endpoints)
4. [Authentication](#authentication)
5. [Key Features](#key-features)
6. [Integrations](#integrations)

---

## 🎯 Project Overview

**Steren Backend** is a comprehensive Laravel-based car marketplace and classifieds platform designed to connect car buyers and sellers. The platform provides a full-featured ecosystem for managing car listings, dealer operations, user interactions, and business analytics.

### Technology Stack
- **Framework**: Laravel 12.0
- **PHP Version**: 8.2+
- **Authentication**: Laravel Sanctum
- **Database**: MySQL/PostgreSQL
- **Storage**: AWS S3
- **Queue System**: Laravel Queue
- **Real-time**: Firebase Cloud Messaging (FCM)

---

## 🚀 Core Functionality

### 1. **Car Listings Management**
- Create, update, and manage car listings with comprehensive vehicle details
- VIN decoding and OCR capabilities for automatic data extraction
- Car inspections and Carfax report integration
- Listing promotions, boosts, and featured listings
- Status management (pause, unpause, archive, mark as sold)

### 2. **User & Dealer Management**
- Multi-factor authentication (email/phone, OTP verification, social login)
- Dealer application and verification system
- Subscription-based access control for dealers
- Studio management for dealers to organize listings
- User profiles with preferences and settings

### 3. **Search & Discovery**
- Advanced search with multiple filter options
- Similar car recommendations engine
- Watchlist and favorites management
- Search history tracking
- Sponsored listings support

### 4. **Analytics & Tracking**
- User behavior analytics
- Car listing performance metrics
- Event tracking (views, shares, clicks, favorites)
- Boost options and promotion analytics

### 5. **Integrations**
- **Odoo ERP**: Full synchronization of listings, users, dealers, and master data
- **StandingTech**: SMS/WhatsApp messaging gateway
- **Vehicle Databases**: VIN decoding service
- **AWS S3**: Media and document storage

### 6. **Notifications System**
- Push notifications via Firebase Cloud Messaging (FCM)
- SMS/WhatsApp notifications
- Scheduled notifications
- Notification preferences and management

---

## 📡 API Endpoints

### Base URL
All API endpoints are prefixed with `/api`

### Authentication
Most endpoints require authentication using Laravel Sanctum. Include the token in the Authorization header:
```
Authorization: Bearer {token}
```

---

## 🔐 Authentication & User Management

**Base Path**: `/api/auth`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| POST | `/register` | User registration | ❌ |
| POST | `/login` | User login | ❌ |
| POST | `/send-otp` | Send OTP code | ❌ |
| POST | `/verify-otp` | Verify OTP code | ✅ |
| POST | `/social-login` | Social media login | ❌ |
| POST | `/password/forgot` | Request password reset | ❌ |
| POST | `/password/reset` | Complete password reset | ❌ |
| GET | `/me` | Get current user | ✅ |
| POST | `/logout` | Logout current session | ✅ |
| POST | `/logout-all` | Logout all devices | ✅ |
| POST | `/complete-pending-account` | Complete pending account | ✅ |
| GET | `/profile` | Get user profile | ✅ |
| PUT | `/profile` | Update user profile | ✅ |
| PUT | `/profile/password` | Update password | ✅ |
| POST | `/profile/picture` | Update profile picture | ✅ |
| PUT | `/profile/preferences` | Update preferences | ✅ |
| GET | `/partner/info` | Get Odoo partner info | ✅ |
| PUT | `/partner/update` | Update partner info | ✅ |

### 📝 Endpoint Details

#### POST `/register`
Register a new user account.

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

**Parameters:**
- `full_name` (string, nullable): User's full name
- `email` (string, nullable, unique): Email address
- `phone` (string, required, unique, max:32): Phone number with country code
- `password` (string, nullable, min:6): Password
- `firebase_uid` (string, nullable, unique): Firebase user ID

**Response:**
```json
{
  "success": true,
  "message": "Registration initiated",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe",
      "email": "john@example.com",
      "phone": "9647502171212",
      "is_verified": false,
      "is_dealer": false
    },
    "otp_sent": true,
    "token": "1|abc123def456..."
  }
}
```

---

#### POST `/login`
Login with email/phone and password.

**Request Body:**
```json
{
  "identifier": "john@example.com",
  "password": "password123"
}
```

**Parameters:**
- `identifier` (string, required): Email or phone number
- `password` (string, required): User password

**Response:**
```json
{
  "success": true,
  "message": "Logged in",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe",
      "email": "john@example.com",
      "phone": "9647502171212"
    },
    "token": "1|abc123def456..."
  }
}
```

---

#### POST `/send-otp`
Send OTP code to phone number.

**Request Body:**
```json
{
  "phone": "9647502171212"
}
```

**Parameters:**
- `phone` (string, required, max:32): Phone number with country code

**Response:**
```json
{
  "success": true,
  "message": "OTP sent",
  "data": {
    "sent": true,
    "expires_at": "2025-01-20T10:15:00Z"
  }
}
```

---

#### POST `/verify-otp`
Verify OTP code (requires authentication).

**Request Body:**
```json
{
  "code": "123456"
}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Parameters:**
- `code` (string, required): 6-digit OTP code

**Response:**
```json
{
  "success": true,
  "message": "Verified",
  "data": {
    "user": {
      "id": 1,
      "phone_verified_at": "2025-01-20T10:16:00Z"
    },
    "token": "1|abc123def456...",
    "client_integration": {
      "partner_id": 12345
    }
  }
}
```

---

#### POST `/complete-pending-account`
Complete a pending account setup.

**Request Body:**
```json
{
  "full_name": "John Doe",
  "phone": "9647502171212"
}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Parameters:**
- `full_name` (string, required, max:255): User's full name
- `phone` (string, required, max:20): Phone number

**Response:**
```json
{
  "success": true,
  "message": "Account completed successfully",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe",
      "phone": "9647502171212",
      "is_pending": false
    },
    "client_integration": {
      "partner_id": 12345
    }
  }
}
```

---

#### PUT `/profile`
Update user profile.

**Request Body:**
```json
{
  "full_name": "John Doe Updated",
  "email": "newemail@example.com",
  "birthday": "1990-01-15",
  "language_id": 1
}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Parameters:**
- `full_name` (string, nullable): Full name
- `email` (string, nullable, email): Email address
- `birthday` (date, nullable): Date of birth
- `language_id` (integer, nullable): Language preference ID

**Response:**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe Updated",
      "email": "newemail@example.com"
    }
  }
}
```

---

## 🚗 Car Listings

**Base Path**: `/api/car-listings`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/` | Get all car listings | ❌ |
| GET | `/{id}` | Get listing details | ❌ |
| GET | `/{id}/complete` | Get complete listing details | ❌ |
| GET | `/{id}/with-media` | Get listing with media | ❌ |
| GET | `/studio/{studioId}` | Get listings by studio | ❌ |
| GET | `/filter-options` | Get filter options | ❌ |
| GET | `/trims/{modelId}` | Get car trims for model | ❌ |
| GET | `/news` | Get news articles | ❌ |
| GET | `/similar/search` | Get similar cars | ❌ |
| GET | `/you-may-also-like` | Get recommendations | ✅ |
| GET | `/brand/{brandId}/condition-groups` | Get brand listings by condition | ❌ |
| GET | `/studio/{studioId}/condition-groups` | Get studio listings by condition | ❌ |
| POST | `/` | Create new listing | ✅ (Phone Verified) |
| POST | `/form-data` | Create listing via form data | ✅ (Phone Verified) |

### 📝 Endpoint Details

#### POST `/car-listings`
Create a new car listing (JSON format with base64 images).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "title": "2020 Toyota Camry - Excellent Condition",
  "description": "Well maintained car with full service history",
  "price": 25000,
  "price_currency": "IQD",
  "car_info": {
    "vin": "1HGBH41JXMN109186",
    "brand": 1,
    "model": 5,
    "condition": "used",
    "year": 2020,
    "gcc_import": "GCC",
    "body_type": 2,
    "engine_size": 2.5,
    "fuel_type": "Petrol",
    "cylinders": 4,
    "transmission": "Automatic",
    "seating_capacity": 5,
    "exterior_colour": "White",
    "warranty": "yes",
    "drive_type": "FWD",
    "mileage_type": "KM",
    "mileage": 50000,
    "plate_status": "Valid",
    "city_id": 1
  },
  "seller_info": {
    "type": "private_seller"
  },
  "images": [
    "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
    "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
  ],
  "inspection": {
    "right_side": true,
    "left_side": true,
    "roof": false,
    "front": true,
    "back": true
  },
  "inspection_file": "data:application/pdf;base64,JVBERi0xLjQK...",
  "inspection_file_name": "inspection_report.pdf",
  "promotion_type": "free"
}
```

**Parameters:**
- `title` (string, required, max:255): Listing title
- `description` (string, nullable, max:2000): Listing description
- `price` (numeric, required, min:0): Listing price
- `price_currency` (string, required, size:3): Currency code (e.g., "IQD", "USD")
- `car_info` (object, required): Car information object
  - `vin` (string, nullable, size:17): Vehicle Identification Number
  - `brand` (integer, required): Car brand ID
  - `model` (integer, required): Car model ID
  - `condition` (string, required, in:new,used): Car condition
  - `year` (integer, required, min:1900, max:current+1): Manufacturing year
  - `gcc_import` (string, nullable): "GCC" or "Import"
  - `body_type` (integer, required): Body type ID
  - `engine_size` (numeric, nullable, min:0, max:10): Engine size in liters
  - `fuel_type` (string, nullable): Fuel type name
  - `cylinders` (integer, nullable, min:1, max:16): Number of cylinders
  - `transmission` (string, nullable): Transmission type
  - `seating_capacity` (integer, nullable, min:1, max:12): Seating capacity
  - `exterior_colour` (string, nullable): Exterior color name
  - `warranty` (string, nullable, in:yes,no): Warranty status
  - `drive_type` (string, nullable): Drive type (FWD, RWD, AWD, 4WD)
  - `mileage_type` (string, nullable, in:KM,Miles): Mileage unit
  - `mileage` (integer, required, min:0): Vehicle mileage
  - `plate_status` (string, nullable): Plate status
  - `city_id` (integer, nullable): City ID
- `seller_info` (object, required): Seller information
  - `type` (string, required, in:verified_seller,private_seller,dealer): Seller type
- `images` (array, nullable): Array of base64 encoded images (max: 5 for non-dealers, 10 for dealers, 20 for verified dealers)
- `inspection` (object, nullable): Inspection data
  - `right_side` (boolean, nullable): Right side inspection
  - `left_side` (boolean, nullable): Left side inspection
  - `roof` (boolean, nullable): Roof inspection
  - `front` (boolean, nullable): Front inspection
  - `back` (boolean, nullable): Back inspection
- `inspection_file` (string, nullable): Base64 encoded PDF file
- `inspection_file_name` (string, nullable, max:255): Inspection file name
- `promotion_type` (string, nullable): Promotion type ("free", "featured", etc.)

**Response:**
```json
{
  "success": true,
  "message": "Car listing created successfully and is pending approval",
  "data": {
    "car_listing": {
      "id": 123,
      "title": "2020 Toyota Camry - Excellent Condition",
      "price": 25000,
      "price_currency": "IQD",
      "status": "pending",
      "year": 2020,
      "mileage": 50000
    },
    "status": "pending",
    "message": "Car listing created successfully and is pending approval from our team.",
    "subscription_info": {
      "remaining_listings": 4,
      "max_listings": 5,
      "current_listings": 1,
      "plan_name": "Free"
    },
    "odoo_response": {
      "result": {
        "data": {
          "listing_id": 456,
          "vehicle_id": 789
        }
      }
    }
  }
}
```

---

#### POST `/car-listings/form-data`
Create a new car listing (multipart/form-data format).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body (Form Data):**
```
title: 2020 Toyota Camry - Excellent Condition
description: Well maintained car with full service history
price: 25000
price_currency: IQD
car_brand_id: 1
car_model_id: 5
condition_type: used
year: 2020
gcc_import: GCC
car_body_type_id: 2
engine_size: 2.5
fuel_type_id: 1
cylinder_id: 1
transmission_id: 1
seating_capacity: 5
exterior_color_id: 1
has_warranty: true
drive_type_id: 1
mileage_type_id: 1
mileage: 50000
plate_status: Valid
city_id: 1
vin: 1HGBH41JXMN109186
seller_type: private_seller
images[]: [file]
images[]: [file]
inspection_right_side: true
inspection_left_side: true
inspection_roof: false
inspection_front: true
inspection_back: true
inspection_file: [file]
promotion_type: free
```

**Response:** Same as POST `/car-listings`

---

#### GET `/car-listings`
Get all car listings with optional filters.

**Query Parameters:**
- `page` (integer, nullable, min:1): Page number (default: 1)
- `per_page` (integer, nullable, min:1, max:100): Items per page (default: 20)
- `brand_id` (integer, nullable): Filter by brand ID
- `model_id` (integer, nullable): Filter by model ID
- `condition_id` (integer, nullable): Filter by condition ID
- `year_from` (integer, nullable, min:1990): Minimum year
- `year_to` (integer, nullable, max:current): Maximum year
- `price_min` (numeric, nullable, min:0): Minimum price
- `price_max` (numeric, nullable, min:0): Maximum price
- `city_id` (integer, nullable): Filter by city ID
- `status` (string, nullable): Filter by status (pending, approved, rejected, sold)

**Response:**
```json
{
  "success": true,
  "message": "Car listings retrieved successfully",
  "data": {
    "data": [
      {
        "id": 123,
        "title": "2020 Toyota Camry",
        "price": 25000,
        "price_currency": "IQD",
        "year": 2020,
        "mileage": 50000,
        "brand": {
          "id": 1,
          "name": "Toyota"
        },
        "model": {
          "id": 5,
          "name": "Camry"
        }
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

---

#### GET `/car-listings/{id}`
Get detailed information about a specific car listing.

**Response:**
```json
{
  "success": true,
  "message": "Car listing retrieved successfully",
  "data": {
    "id": 123,
    "title": "2020 Toyota Camry - Excellent Condition",
    "description": "Well maintained car...",
    "price": 25000,
    "price_currency": "IQD",
    "year": 2020,
    "mileage": 50000,
    "vin": "1HGBH41JXMN109186",
    "status": "approved",
    "brand": {
      "id": 1,
      "name": "Toyota"
    },
    "model": {
      "id": 5,
      "name": "Camry"
    },
    "media": [
      {
        "id": 1,
        "url": "https://s3.amazonaws.com/...",
        "info": "primary"
      }
    ],
    "features": [],
    "specifications": []
  }
}
```

---

## 📝 Listing Management

**Base Path**: `/api/listing`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| POST | `/{listingId}/pause` | Pause listing | ✅ |
| POST | `/{listingId}/unpause` | Unpause listing | ✅ |
| POST | `/{listingId}/boost` | Boost listing | ✅ |
| POST | `/{listingId}/archive` | Archive listing | ✅ |
| POST | `/{listingId}/mark_sold` | Mark listing as sold | ✅ |
| POST | `/odoo/{odooId}/approve` | Approve listing by Odoo ID | ❌ (Odoo) |
| POST | `/odoo/{odooId}/reject` | Reject listing by Odoo ID | ❌ (Odoo) |
| POST | `/odoo/{odooId}/process` | Process listing by Odoo ID | ❌ (Odoo) |

---

## 📋 My Listings

**Base Path**: `/api/my-listings`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/` | Get user's own listings | ✅ |

---

## 🔍 Search

**Base Path**: `/api/search`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/listings` | Search car listings | ❌ |
| GET | `/brands-models` | Search brands and models | ❌ |
| GET | `/brand-suggestions` | Get brand suggestions | ❌ |
| GET | `/sponsored-listings` | Get sponsored listings | ❌ |
| GET | `/brands` | Get all brands | ❌ |
| GET | `/filter-options` | Get filter options | ❌ |
| GET | `/recent-searches` | Get recent searches | ✅ |
| POST | `/save-search` | Save search to history | ✅ |
| DELETE | `/recent-searches/{searchId}` | Delete search from history | ✅ |
| POST | `/clear-cache` | Clear search cache | ✅ (Admin) |

### 📝 Endpoint Details

#### GET `/search/listings`
Search car listings with advanced filters.

**Query Parameters:**
- `search` (string, nullable, max:255): General search term
- `brand_id` (integer, nullable): Filter by brand ID
- `model_id` (integer, nullable): Filter by model ID
- `condition_id` (integer, nullable): Filter by condition ID
- `year_from` (integer, nullable, min:1990, max:current): Minimum year
- `year_to` (integer, nullable, min:1990, max:current, gte:year_from): Maximum year
- `price_min` (numeric, nullable, min:0): Minimum price
- `price_max` (numeric, nullable, min:0, gte:price_min): Maximum price
- `mileage_min` (integer, nullable, min:0): Minimum mileage
- `mileage_max` (integer, nullable, min:0, gte:mileage_min): Maximum mileage
- `body_type_id` (integer, nullable): Filter by body type ID
- `fuel_type_id` (integer, nullable): Filter by fuel type ID
- `transmission_id` (integer, nullable): Filter by transmission ID
- `exterior_color_id` (integer, nullable): Filter by exterior color ID
- `drive_type_id` (integer, nullable): Filter by drive type ID
- `cylinder_id` (integer, nullable): Filter by cylinder option ID
- `city_id` (integer, nullable): Filter by city ID
- `import_type_id` (integer, nullable): Filter by import type ID
- `has_warranty` (boolean, nullable): Filter by warranty status
- `page` (integer, nullable, min:1): Page number (default: 1)
- `per_page` (integer, nullable, min:1, max:100): Items per page (default: 20)

**Example Request:**
```
GET /api/search/listings?brand_id=1&year_from=2018&year_to=2022&price_min=10000&price_max=50000&page=1&per_page=20
```

**Response:**
```json
{
  "success": true,
  "message": "Car listings retrieved successfully",
  "data": {
    "listings": [
      {
        "id": 123,
        "title": "2020 Toyota Camry",
        "price": 25000,
        "year": 2020,
        "mileage": 50000
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "last_page": 8,
      "has_more_pages": true,
      "total": 150
    },
    "filters_applied": {
      "brand_id": 1,
      "year_from": 2018,
      "year_to": 2022
    }
  }
}
```

---

#### GET `/search/brands-models`
Search for car brands and models.

**Query Parameters:**
- `search` (string, required): Search term
- `limit` (integer, nullable, min:1): Maximum results (default: 10)

**Example Request:**
```
GET /api/search/brands-models?search=toyota&limit=10
```

**Response:**
```json
{
  "success": true,
  "message": "Search results retrieved successfully",
  "data": {
    "brands": [
      {
        "id": 1,
        "name": "Toyota",
        "logo": "https://..."
      }
    ],
    "models": [
      {
        "id": 5,
        "name": "Camry",
        "brand_id": 1
      }
    ],
    "meta": {
      "total_brands": 1,
      "total_models": 5,
      "search_term": "toyota"
    }
  }
}
```

---

#### POST `/search/save-search`
Save a search to user's history.

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "search_term": "Toyota Camry 2020"
}
```

**Parameters:**
- `search_term` (string, required): Search term to save

**Response:**
```json
{
  "success": true,
  "message": "Search term saved successfully",
  "data": []
}
```

---

## 🔄 Similar Cars

**Base Path**: `/api/similar-cars`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/car/{carListingId}` | Get similar cars | ❌ |
| GET | `/brand-model` | Get by brand/model | ❌ |
| GET | `/price` | Get by price range | ❌ |
| GET | `/year` | Get by year | ❌ |
| GET | `/body-type/{bodyTypeId}` | Get by body type | ❌ |
| GET | `/advanced` | Advanced similar cars search | ❌ |
| GET | `/recommendations` | Get recommendations | ❌ |
| POST | `/clear-cache` | Clear similar cars cache | ✅ (Admin) |

---

## ⭐ Watchlist & Favorites

**Base Path**: `/api/watch-list`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/` | Get user's watchlist | ✅ |
| GET | `/search-history` | Get search history | ✅ |
| GET | `/sort-options` | Get sort options | ✅ |
| POST | `/favorites` | Add to watchlist | ✅ |
| DELETE | `/favorites/{favoriteId}` | Remove from watchlist | ✅ |
| PUT | `/favorites/{favoriteId}/notifications` | Toggle favorite notifications | ✅ |
| POST | `/search-history` | Save search to history | ✅ |
| DELETE | `/search-history/{searchId}` | Delete search from history | ✅ |
| PUT | `/search-history/{searchId}/notifications` | Toggle search notifications | ✅ |

---

## 🏢 Dealer Management

**Base Path**: `/api/dealer`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/status` | Get dealer application status | ✅ (Phone Verified) |
| GET | `/subscription-plans` | Get available subscription plans | ✅ (Phone Verified) |
| POST | `/application` | Submit dealer application | ✅ (Phone Verified) |
| GET | `/application/{id}` | Get application details | ✅ (Phone Verified) |
| PUT | `/application/{id}` | Update application | ✅ (Phone Verified) |
| DELETE | `/application/{id}` | Delete application | ✅ (Phone Verified) |
| GET | `/subscription/current` | Get current subscription | ✅ (Phone Verified) |
| GET | `/subscription/plans` | Get subscription plans | ✅ (Phone Verified) |
| PUT | `/subscription/update-plan` | Update subscription plan | ✅ (Phone Verified) |
| POST | `/subscription/complete-payment` | Complete subscription payment | ✅ (Phone Verified) |
| POST | `/subscription/renew` | Renew subscription | ✅ (Phone Verified) |
| GET | `/subscription/billing-history` | Get billing history | ✅ (Phone Verified) |

### 📝 Endpoint Details

#### POST `/dealer/application`
Submit a new dealer application.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body (Form Data):**
```
seller_type: authorized
owner_manager_name: John Doe
phone_number: 9647502171212
email: john@example.com
email_address: john@example.com
dealership_company_name: ABC Motors
dealership_name: ABC Motors
vehicles_in_stock: 50
years_in_business: 10
city_address: Baghdad, Iraq
automotive_brand_represented: Toyota
authorization_letter: [file - PDF/JPG/PNG, max 10MB]
business_license_number: BL123456
subscription_plan_id: 1
terms_accepted: true
information_confirmed: true
```

**Parameters:**
- `seller_type` (string, required, in:authorized,independent): Type of seller
- `owner_manager_name` (string, required, max:255): Owner/Manager full name
- `phone_number` (string, required, max:20): Phone number
- `email` (string, required, email, max:255): Email address
- `email_address` (string, required, email, max:255): Email address (duplicate field)
- `dealership_company_name` (string, required, max:255): Company name
- `dealership_name` (string, required, max:255): Dealership name
- `vehicles_in_stock` (integer, nullable, min:0, max:9999): Number of vehicles in stock
- `years_in_business` (integer, nullable, min:0, max:100): Years in business
- `city_address` (string, required, max:500): City and address
- `automotive_brand_represented` (string, nullable, max:255, required if seller_type=authorized): Brand represented
- `authorization_letter` (file, nullable, mimes:pdf,jpg,jpeg,png, max:10240, required if seller_type=authorized): Authorization letter file
- `business_license_number` (string, nullable, max:255, required if seller_type=authorized): Business license number
- `subscription_plan_id` (integer, nullable, exists:subscription_plans,id): Selected subscription plan
- `terms_accepted` (boolean, required, accepted): Terms acceptance
- `information_confirmed` (boolean, required, accepted): Information confirmation

**Response:**
```json
{
  "success": true,
  "message": "Dealer application submitted successfully",
  "data": {
    "application": {
      "id": 1,
      "seller_type": "authorized",
      "status": "pending",
      "dealership_company_name": "ABC Motors",
      "created_at": "2025-01-20T10:00:00Z"
    }
  }
}
```

---

#### GET `/dealer/status`
Get dealer application status for authenticated user.

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Status retrieved successfully",
  "data": {
    "has_application": true,
    "application": {
      "id": 1,
      "status": "approved",
      "status_badge_color": "#10B981",
      "seller_type": "authorized",
      "dealership_company_name": "ABC Motors",
      "subscription_plan": {
        "id": 1,
        "name": "Premium",
        "price": 100000,
        "formatted_price": "100,000 IQD"
      },
      "payment_completed": true,
      "created_at": "2025-01-20T10:00:00Z",
      "approved_at": "2025-01-21T10:00:00Z",
      "expires_at": "2026-01-21T10:00:00Z",
      "days_until_expiration": 365
    },
    "is_active": true,
    "is_expiring_soon": false,
    "is_expired": false,
    "message": "You are now a verified dealer"
  }
}
```

---

## 👥 Dealer Management (Admin)

**Base Path**: `/api/dealer-management`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/status` | Check dealer status | ✅ |
| POST | `/make-dealer` | Make current user a dealer | ✅ |
| DELETE | `/remove-dealer` | Remove dealer status | ✅ |
| GET | `/statistics` | Get dealer statistics | ✅ |
| GET | `/all-dealers` | Get all dealers | ✅ (Admin) |
| POST | `/make-user-dealer` | Make user a dealer | ✅ (Admin) |
| DELETE | `/remove-user-dealer` | Remove user dealer status | ✅ (Admin) |
| POST | `/bulk-make-dealers` | Bulk make users dealers | ✅ (Admin) |
| DELETE | `/bulk-remove-dealers` | Bulk remove dealer status | ✅ (Admin) |

---

## ✅ Dealer Verification

**Base Path**: `/api/verification`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| POST | `/submit` | Submit verification documents | ✅ |
| GET | `/status` | Get verification status | ✅ |
| POST | `/update-status` | Update verification status (webhook) | ❌ (Odoo) |
| GET | `/subscription-plans` | Get subscription plans | ❌ |

---

## 📄 Dealer Applications (Admin)

**Base Path**: `/api/dealer-applications`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| POST | `/{applicationId}/approve` | Approve application | ✅ |
| POST | `/{applicationId}/reject` | Reject application | ✅ |
| POST | `/{applicationId}/process` | Process application | ✅ |
| POST | `/odoo/{odooId}/approve` | Approve by Odoo ID | ❌ (Odoo) |
| POST | `/odoo/{odooId}/reject` | Reject by Odoo ID | ❌ (Odoo) |
| POST | `/odoo/{odooId}/process` | Process by Odoo ID | ❌ (Odoo) |

---

## 🏪 Studios

**Base Path**: `/api/studios`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/` | Get all studios | ❌ |

---

## 💳 Subscription-Based Listings

**Base Path**: `/api/subscription-listings`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/top-picks` | Get top picks (public) | ❌ |
| GET | `/` | Get car listings | ✅ |
| GET | `/you-may-also-like/{listingId}` | Get recommendations | ✅ |
| GET | `/plan-features` | Get plan features | ✅ |
| GET | `/dealer/studios` | Get dealer studios | ✅ (Dealer) |
| POST | `/dealer/studios` | Create studio | ✅ (Dealer) |
| GET | `/dealer/studios/{studioId}/listings` | Get studio listings | ✅ (Dealer) |
| POST | `/dealer/listings/{listingId}/associate-studio` | Associate listing with studio | ✅ (Dealer) |
| GET | `/dealer/auto-studio/status` | Get auto studio status | ✅ (Dealer) |
| POST | `/dealer/auto-studio/process` | Process auto studio | ✅ (Dealer) |

---

## 📦 Subscriptions

**Base Path**: `/api/subscriptions`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/plans` | Get subscription plans | ❌ |
| GET | `/current` | Get current subscription | ✅ |
| GET | `/limits` | Get subscription limits | ✅ |
| GET | `/history` | Get subscription history | ✅ |
| POST | `/subscribe` | Subscribe to plan | ✅ |
| POST | `/cancel` | Cancel subscription | ✅ |
| POST | `/renew` | Renew subscription | ✅ |
| POST | `/change-plan` | Change subscription plan | ✅ |

---

## 🎯 Listing Promotions

**Base Path**: `/api/listing-promotions`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/` | Get all promotions | ❌ |
| GET | `/pricing` | Get pricing information | ❌ |
| GET | `/default` | Get default promotion | ❌ |
| GET | `/compare` | Compare promotions | ❌ |
| GET | `/{id}` | Get promotion details | ❌ |

---

## 🔢 VIN Decoder

**Base Path**: `/api/vin`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| POST | `/decode` | Decode VIN number | ❌ |
| POST | `/decode-raw` | Decode VIN (raw response) | ❌ |
| POST | `/decode-for-listing` | Decode VIN for listing | ❌ |
| POST | `/ocr-and-decode` | OCR and decode VIN | ❌ |
| POST | `/ocr-and-decode-raw` | OCR and decode (raw) | ❌ |
| GET | `/info` | Get VIN information | ❌ |
| GET | `/status` | Get service status | ❌ |
| POST | `/ocr-and-auction` | OCR and get auction data | ❌ |
| POST | `/ocr` | OCR only | ❌ |
| GET | `/auction/{vin}` | Get auction information | ❌ |

### 📝 Endpoint Details

#### POST `/vin/decode`
Decode a VIN number and get vehicle information.

**Request Body:**
```json
{
  "vin": "1HGBH41JXMN109186",
  "cache": true,
  "save_to_database": true
}
```

**Parameters:**
- `vin` (string, required, size:17, regex:/^[A-HJ-NPR-Z0-9]{17}$/i): 17-character VIN (cannot contain I, O, or Q)
- `cache` (boolean, nullable): Use cached result if available
- `save_to_database` (boolean, nullable): Save decoded data to database

**Response:**
```json
{
  "success": true,
  "message": "VIN decoded successfully",
  "data": {
    "vin": "1HGBH41JXMN109186",
    "make": "Honda",
    "model": "Accord",
    "year": 2021,
    "engine": "2.0L I4",
    "transmission": "Automatic",
    "drive_type": "FWD",
    "fuel_type": "Gasoline",
    "body_type": "Sedan",
    "doors": 4,
    "seating_capacity": 5,
    "specifications": {
      "horsepower": 192,
      "torque": 192,
      "mpg_city": 30,
      "mpg_highway": 38
    },
    "decoded_at": "2025-01-20T10:00:00Z"
  }
}
```

---

#### POST `/vin/ocr-and-decode`
Extract VIN from image using OCR and decode it.

**Request Body:**
```json
{
  "image": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
  "cache": true
}
```

**Parameters:**
- `image` (string, required): Base64 encoded image containing VIN
- `cache` (boolean, nullable): Use cached result if available

**Response:**
```json
{
  "success": true,
  "message": "VIN extracted and decoded successfully",
  "data": {
    "extracted_vin": "1HGBH41JXMN109186",
    "confidence": 0.95,
    "decoded_data": {
      "make": "Honda",
      "model": "Accord",
      "year": 2021
    }
  }
}
```

---

#### POST `/vin/decode-for-listing`
Decode VIN specifically formatted for car listing creation.

**Request Body:**
```json
{
  "vin": "1HGBH41JXMN109186"
}
```

**Response:**
```json
{
  "success": true,
  "message": "VIN decoded for listing",
  "data": {
    "vin": "1HGBH41JXMN109186",
    "car_brand_id": 1,
    "car_model_id": 5,
    "year": 2021,
    "fuel_type_id": 1,
    "transmission_id": 1,
    "body_type_id": 2,
    "engine_size": 2.0,
    "cylinders": 4,
    "drive_type_id": 1,
    "seating_capacity": 5,
    "doors": 4
  }
}
```

---

## 🔍 Car Inspections

**Base Path**: `/api/inspections`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/statistics` | Get inspection statistics | ❌ |
| GET | `/{carListingId}` | Get inspection for listing | ❌ |
| GET | `/inspector/{inspectorId}` | Get inspections by inspector | ❌ |

---

## 📊 Carfax Reports

**Base Path**: `/api/carfax`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/statistics` | Get Carfax statistics | ❌ |
| GET | `/vin/{vin}` | Get report by VIN | ❌ |
| GET | `/{carListingId}` | Get report for listing | ❌ |
| GET | `/search` | Search Carfax reports | ❌ |
| POST | `/report` | Submit Carfax report | ❌ |

---

## 🚨 Car Damage

**Base Path**: `/api/car-damages`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/car-parts` | Get car parts list | ❌ |
| GET | `/statistics/{carListingId}` | Get damage statistics | ❌ |
| GET | `/{carListingId}` | Get car damage details | ❌ |

---

## 📋 Reports

**Base Path**: `/api/reports`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/reasons` | Get report reasons | ❌ |
| GET | `/user` | Get user's reports | ✅ |
| GET | `/{reportId}` | Get report details | ✅ |
| GET | `/statistics/admin` | Get admin statistics | ✅ (Admin) |

---

## 👤 Account

**Base Path**: `/api/account`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/info` | Get account information | ✅ |
| GET | `/analytics` | Get account analytics | ✅ |
| GET | `/my-ads` | Get user's ads | ✅ |
| GET | `/wallet` | Get wallet information | ✅ |
| GET | `/wallet/transactions` | Get wallet transactions | ✅ |
| GET | `/payment-methods` | Get payment methods | ✅ |
| GET | `/my-subscription` | Get subscription details | ✅ |
| GET | `/language-options` | Get language options | ✅ |
| GET | `/theme-options` | Get theme options | ✅ |
| GET | `/social-auth-options` | Get social auth options | ✅ |
| GET | `/device-permissions` | Get device permissions | ✅ |
| PUT | `/device-permissions` | Update multiple permissions | ✅ |
| PUT | `/device-permissions/{permission}` | Update single permission | ✅ |
| POST | `/device-permissions/reset` | Reset permissions | ✅ |

---

## 📈 Analytics

**Base Path**: `/api/analytics`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/user` | Get user analytics | ✅ |
| GET | `/car-listing/{carListingId}` | Get listing analytics | ✅ |
| GET | `/boost-options` | Get boost options | ✅ |
| GET | `/my-ads` | Get my ads analytics | ✅ |

---

## 📊 Analytics Tracking

**Base Path**: `/api/track`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET/POST | `/car-listing/{carListingId}/view` | Track listing view | ❌ |
| GET/POST | `/car-listing/{carListingId}/share` | Track listing share | ❌ |
| GET/POST | `/car-listing/{carListingId}/call-tap` | Track call button tap | ❌ |
| GET/POST | `/car-listing/{carListingId}/whatsapp-click` | Track WhatsApp click | ❌ |
| GET/POST | `/car-listing/{carListingId}/sms-click` | Track SMS click | ❌ |
| GET/POST | `/car-listing/{carListingId}/email-click` | Track email click | ❌ |
| GET/POST | `/car-listing/{carListingId}/watchlist-save` | Track watchlist save | ❌ |
| GET/POST | `/car-listing/{carListingId}/favorite` | Track favorite action | ❌ |
| GET/POST | `/car-listing/bulk` | Bulk tracking | ❌ |

---

## 💰 Payments

**Base Path**: `/api/payments`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/status/{paymentId}` | Get payment status | ✅ |

---

## 🔔 Notifications

**Base Path**: `/api/notifications`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/` | Get user notifications | ✅ |
| GET | `/types` | Get notification types | ✅ |
| GET | `/analytics` | Get notification analytics | ✅ |
| PUT | `/{notificationId}/read` | Mark notification as read | ✅ |
| PUT | `/mark-all-read` | Mark all as read | ✅ |
| DELETE | `/{notificationId}` | Delete notification | ✅ |
| POST | `/fcm/register` | Register FCM token | ✅ |
| POST | `/fcm/deactivate` | Deactivate FCM token | ✅ |
| POST | `/send/user` | Send to user | ✅ (Admin) |
| POST | `/send/users` | Send to multiple users | ✅ (Admin) |
| POST | `/send/all` | Send to all users | ✅ (Admin) |
| POST | `/send/dealers` | Send to dealers | ✅ (Admin) |
| POST | `/send/template` | Send template notification | ✅ (Admin) |
| POST | `/schedule` | Schedule notification | ✅ (Admin) |

---

## 🧪 Test Notifications

**Base Path**: `/api/test-notifications`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| POST | `/send` | Test notification | ✅ |
| POST | `/register-fcm` | Test FCM registration | ✅ |
| POST | `/push` | Test push notification | ✅ |
| GET | `/types` | Get notification types | ✅ |
| POST | `/template` | Test template notification | ✅ |

---

## 📚 Data Options

**Base Path**: `/api/options`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/car-brands` | Get car brands | ❌ |
| GET | `/car-models` | Get car models | ❌ |
| GET | `/body-types` | Get body types | ❌ |
| GET | `/fuel-types` | Get fuel types | ❌ |
| GET | `/transmissions` | Get transmissions | ❌ |
| GET | `/drive-types` | Get drive types | ❌ |
| GET | `/exterior-colors` | Get exterior colors | ❌ |
| GET | `/car-conditions` | Get car conditions | ❌ |
| GET | `/condition-types` | Get condition types | ❌ |
| GET | `/cities` | Get cities | ❌ |
| GET | `/statuses` | Get statuses | ❌ |
| GET | `/promotion-types` | Get promotion types | ❌ |
| GET | `/report-reasons` | Get report reasons | ❌ |

---

## 🎠 Carousels

**Base Path**: `/api/carousels`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/` | Get carousels | ❌ |
| GET | `/all` | Get all carousels | ❌ |
| GET | `/{id}` | Get carousel by ID | ❌ |

---

## 📞 Contact Methods

**Base Path**: `/api/contact-methods`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| GET | `/` | Get contact methods | ❌ |

---

## 🔗 Odoo Integration

**Base Path**: `/api/odoo`

### GET Routes (Public)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/brands` | Get car brands |
| GET | `/body-types` | Get body types |
| GET | `/cities` | Get cities |
| GET | `/car-models` | Get car models |
| GET | `/locations` | Get locations |
| GET | `/studios` | Get studios |
| GET | `/subscription-plans` | Get subscription plans |
| GET | `/authorized-agencies` | Get authorized agencies |
| GET | `/budgets` | Get budgets |

### POST Routes (Protected with `odoo.tracking` middleware)

All POST routes support CRUD operations for the following entities:
- `car-listings`
- `car-submissions`
- `car-inspections`
- `carfax-reports`
- `cities`
- `car-brands`
- `carousels`
- `car-models`
- `locations`
- `studios`
- `users`
- `body-types`
- `subscription-plans`
- `authorized-agencies`
- `budgets`

**Pattern**: 
- `POST /{entity}` - Create
- `POST /{entity}/odoo/{odooId}/update` - Update by Odoo ID
- `POST /{entity}/odoo/{odooId}/delete` - Delete by Odoo ID
- `POST /{entity}/odoo/{odooId}/restore` - Restore by Odoo ID

---

## 👥 Client

**Base Path**: `/api/client`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| POST | `/create` | Create client | ❌ |
| GET | `/partner/{partnerId}` | Get client by partner ID | ❌ |

---

## 🗑️ Cache

**Base Path**: `/api/cache`

| Method | Endpoint | Description | Auth Required |
|--------|----------|------------|---------------|
| POST | `/clear` | Clear cache | ✅ (Odoo Tracking) |

---

## ✨ Key Features

### 1. **Multi-Tenant Architecture**
- Full Odoo ERP integration for master data synchronization
- Support for multiple dealers and studios
- Centralized user and listing management

### 2. **Dealer Management System**
- Three-tier dealer verification system:
  - **Basic Dealer**: Approved application
  - **Active Dealer**: Approved + Not expired
  - **Verified Dealer**: Approved + Not expired + Documents verified + Complete business info
- Subscription-based access control
- Studio management for organizing listings

### 3. **Advanced Search & Discovery**
- Multi-criteria filtering (brand, model, price, year, condition, etc.)
- Similar car recommendations engine
- Search history and saved searches
- Sponsored listings support

### 4. **VIN Decoding & OCR**
- Automatic VIN decoding from Vehicle Databases
- OCR capabilities for extracting VIN from images
- Auction data integration
- Raw and processed decoding options

### 5. **Comprehensive Analytics**
- User behavior tracking
- Listing performance metrics
- Event tracking (views, shares, clicks, favorites)
- Boost and promotion analytics

### 6. **Multi-Channel Notifications**
- Push notifications via Firebase Cloud Messaging (FCM)
- SMS notifications via StandingTech
- WhatsApp notifications via StandingTech
- Scheduled notifications
- Notification preferences management

### 7. **Subscription Management**
- Multiple subscription tiers
- Plan features and limits
- Billing history
- Subscription renewal and cancellation

### 8. **Media Management**
- AWS S3 integration for media storage
- Profile pictures
- Car listing images
- Document storage

### 9. **Multi-Language Support**
- English and Arabic support
- Language preferences
- Localized content

### 10. **Security Features**
- Phone verification required for critical operations
- OTP-based authentication
- Social login support
- Role-based access control
- Device permissions management

---

## 🔌 Integrations

### 1. **Odoo ERP Integration**
- Full bidirectional synchronization
- Master data management (brands, models, cities, etc.)
- Listing synchronization
- User and dealer management
- Webhook support for real-time updates

### 2. **StandingTech SMS/WhatsApp Gateway**
- SMS messaging
- WhatsApp messaging
- OTP delivery
- Bulk messaging
- Multi-language support (English/Arabic)

### 3. **Vehicle Databases**
- VIN decoding service
- Vehicle specification data
- Auction information
- OCR capabilities

### 4. **AWS S3**
- Media file storage
- Document storage
- Public and private file access
- CDN integration

### 5. **Firebase Cloud Messaging (FCM)**
- Push notifications
- Token management
- Notification scheduling
- Multi-device support

---

## 📝 Notes

### Authentication Levels
- **❌ Public**: No authentication required
- **✅ Protected**: Requires valid Sanctum token
- **✅ (Phone Verified)**: Requires authentication + phone verification
- **✅ (Admin)**: Requires authentication + admin role
- **✅ (Dealer)**: Requires authentication + dealer status
- **❌ (Odoo)**: Requires Odoo API key in headers

### Response Format
All API responses follow a consistent format:
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Error Handling
Errors are returned in the following format:
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... }
}
```

### Pagination
List endpoints support pagination using Laravel's standard pagination:
```json
{
  "data": [ ... ],
  "current_page": 1,
  "per_page": 15,
  "total": 100,
  "last_page": 7
}
```

---

## 📞 Support

For API support or questions, please contact the development team.

---

**Document Version**: 1.0  
**Last Updated**: 2025  
**Framework**: Laravel 12.0

