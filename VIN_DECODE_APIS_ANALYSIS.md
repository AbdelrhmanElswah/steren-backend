# VIN Decode APIs Analysis

## Overview
Two APIs for VIN decoding that extract vehicle information and format it for car listing creation.

---

## 1. `/api/vin/decode-for-listing`

### Purpose
Decode a VIN number and return filtered, structured data ready for car listing creation.

### HTTP Method
`POST`

### Request

**Content-Type:** `application/json`

**Body:**
```json
{
  "vin": "1HGBH41JXMN109186",
  "cache": true  // optional, defaults to true
}
```

**Parameters:**
- `vin` (string, required): 17-character VIN (must match regex: `/^[A-HJ-NPR-Z0-9]{17}$/i`)
  - Cannot contain I, O, or Q
  - Must be exactly 17 characters
- `cache` (boolean, optional): Use cached result if available (default: `true`)

### How It Works

1. **VIN Validation**
   - Validates VIN format (17 chars, no I/O/Q)
   - Uses `VinDecodeRequest` validation

2. **VIN Decoding**
   - Calls `VehicleDatabasesService::decodeVin($vin, $cache)`
   - Uses Vehicle Databases API v2 endpoint (`https://api.vehicledatabases.com/advanced-vin-decode/v2/{vin}`)
   - Provides comprehensive vehicle data including specifications, dimensions, colors, and performance metrics
   - Checks cache first if `cache=true`
   - Cache stored in `vin_decoders` table (valid for 24 hours by default)

3. **Raw Data Fallback** (optional)
   - Attempts to get raw decode data for additional fields
   - Uses `decodeVin($vin, false, true)` for raw payload
   - Falls back gracefully if raw decode fails

4. **Data Filtering & Mapping**
   - Calls `filterVinDataForListing($vinData, $rawResult)`
   - Extracts and maps data from multiple sources:
     - `additional_data.data`
     - `data` (basic data)
     - `rawResult` (raw API response)
   - Maps to system format:
     - Brand/Model IDs from database
     - Fuel type mapping (Electric, Gasoline, Diesel, Hybrid)
     - Transmission mapping (Automatic, Manual, CVT)
     - Drive type mapping (RWD, FWD, AWD, 4WD)
     - Body type ID from database
     - Engine size normalization (converts CC to Liters)
     - GCC/Import classification

5. **Brand Validation**
   - Checks if brand exists in `car_brands` table
   - Returns 422 error if brand not found
   - Includes debug info: extracted brand, brand_id, available brands

### Response Structure

**Success (200):**
```json
{
  "success": true,
  "message": "VIN decoded and filtered for listing creation",
  "data": {
    "general": {
      "vin": "1HGBH41JXMN109186",
      "make": "Honda",
      "model": "Accord",
      "model_year": "2021",
      "trim": "EX-L",
      "body_type": "Sedan",
      "seating_capacity": 5,
      "gcc_import": "Japanese",
      "gcc_import_country": "Japan",
      "damaged_parts": null
    },
    "engine": {
      "engine_type": "Gasoline",
      "engine_size": "2.0",
      "engine_cylinders": 4,
      "transmission": "Automatic",
      "transmission_gears": 10,
      "drive_type": "FWD",
      "fuel_type": "Gasoline"
    },
    "brand_id": "5",
    "brand_logo_url": "https://...",
    "model_id": "123",
    "exterior_color": "Black",
    "doors": 4,
    "horsepower": 192,
    "torque": 192,
    "acceleration_0_100": 8.2,
    "top_speed": 220,
    "mileage_efficiency": 30,
    "length": 4885.0,
    "width": 1840.0,
    "height": 1445.0
  }
}
```

**Error - Brand Not Found (422):**
```json
{
  "success": false,
  "message": "This vehicle brand is not supported in our system",
  "debug": {
    "extracted_brand": "Unknown Brand",
    "brand_id": "Not found",
    "available_brands": {
      "1": "Toyota",
      "2": "Honda",
      ...
    }
  },
  "data": null
}
```

**Error - Invalid VIN (400):**
```json
{
  "success": false,
  "message": "Invalid VIN format. VIN must be 17 characters long...",
  "data": null
}
```

**Error - API Failure (500):**
```json
{
  "success": false,
  "message": "Failed to decode VIN: [error message]",
  "data": null
}
```

### Key Features

- **Caching**: Results cached in database for 24 hours
- **Brand Matching**: Fuzzy matching with variations (e.g., "Mercedes-Benz" → "Mercedes")
- **Model Matching**: Multiple strategies:
  - Exact match
  - Variations (e.g., "C-Class" → "Class C")
  - Normalized matching (removes common words)
  - Fuzzy matching (70% similarity threshold)
- **GCC/Import Classification**: 
  - Maps countries to categories: GCC, American, Canadian, European, Japanese, Korean, Chinese, Others
  - Uses brand-to-country mapping as fallback
- **Data Extraction**: Extracts from multiple nested sources in API response
- **Normalization**: 
  - Engine size: Converts CC to Liters (e.g., 2000cc → 2.0L)
  - Removes units and formats consistently

---

## 2. `/api/vin/ocr-and-decode`

### Purpose
Extract VIN from an image using OCR, then decode it and return filtered data for car listing creation.

### HTTP Method
`POST`

### Request

**Content-Type:** `multipart/form-data`

**Body:**
- `file` OR `image` OR `upload` (file, required): Image file containing VIN
  - Accepted formats: PNG, JPG, JPEG
  - Max size: 10MB (10240 KB)

**Note:** Must use `multipart/form-data`, NOT JSON. In Postman:
1. Select Body tab
2. Choose "form-data" (not raw/JSON)
3. Add key "file" with type "File"
4. Select your image file

### How It Works

1. **File Validation**
   - Accepts file in fields: `file`, `image`, or `upload`
   - Validates: required, file type (png/jpg/jpeg), max 10MB
   - Returns helpful error if sent as JSON instead of form-data

2. **VIN Extraction (OCR)**
   - Calls `extractVinFromImage($file)`
   - Stores file temporarily
   - Calls Vehicle Databases OCR API:
     - Endpoint: `https://api.vehicledatabases.com/vin-ocr` (or configured endpoint)
     - Method: POST with file attachment
     - Headers: `x-AuthKey` (API key), `Accept: application/json`
     - Timeout: 30 seconds (configurable)
   - Extracts VIN from OCR response
   - Cleans up temp file
   - Returns error if OCR fails

3. **VIN Decoding**
   - Uses extracted VIN
   - Calls `VehicleDatabasesService::decodeVin($vin, true)`
   - Same process as `/decode-for-listing` from step 2 onwards

4. **Data Filtering & Mapping**
   - Same as `/decode-for-listing`
   - Calls `filterVinDataForListing($vinData, $rawResult)`

5. **Brand Validation**
   - Same as `/decode-for-listing`

### Response Structure

**Success (200):**
```json
{
  "success": true,
  "message": "VIN extracted from image and decoded successfully",
  "data": {
    // Same structure as /decode-for-listing
    "general": { ... },
    "engine": { ... },
    "brand_id": "...",
    ...
  }
}
```

**Error - File Missing (400):**
```json
{
  "success": false,
  "message": "File is required. Please upload an image file (PNG, JPG, JPEG) using the \"file\", \"image\", or \"upload\" field in multipart/form-data format.",
  "data": null
}
```

**Error - Invalid File Format (400):**
```json
{
  "success": false,
  "message": "Invalid file: The file must be a file of type: png, jpg, jpeg.",
  "data": null
}
```

**Error - OCR Failed (400):**
```json
{
  "success": false,
  "message": "Unable to extract VIN from image: Failed to extract VIN from image. Status: 400",
  "data": null
}
```

**Error - OCR Service Unavailable (400):**
```json
{
  "success": false,
  "message": "OCR service unavailable: Cannot connect to OCR service. Please check your network connection and API endpoint configuration.",
  "data": null
}
```

**Error - Brand Not Found (422):**
```json
{
  "success": false,
  "message": "This vehicle brand is not supported in our system",
  "data": null
}
```

### Key Features

- **OCR Integration**: Uses Vehicle Databases OCR API
- **Multiple File Field Support**: Accepts `file`, `image`, or `upload`
- **Helpful Error Messages**: Detects if request sent as JSON and provides instructions
- **Same Data Processing**: After VIN extraction, uses same filtering/mapping as `/decode-for-listing`

---

## Common Data Mapping Logic

### Brand Matching
- Exact match (case-insensitive)
- Variations:
  - "Mercedes-Benz" → "Mercedes", "Benz"
  - "BMW" → "Bayerische Motoren Werke"
  - "Land Rover" → "Landrover"
  - "Mini" → "Mini Cooper"

### Model Matching
1. Exact match (case-insensitive)
2. Variations:
   - "C-Class" → "Class C"
   - "3 Series" → "3er", "Series 3"
3. Normalized matching (removes common words like "Sedan", "Coupe")
4. Fuzzy matching (70% similarity threshold)

### Fuel Type Mapping
- `Electric` → `Electric`
- `Gasoline` / `Petrol` → `Gasoline`
- `Diesel` → `Diesel`
- `Hybrid` / `Plug-in Hybrid` → `Hybrid`
- `Battery Electric` → `Electric`

### Transmission Mapping
- `Automatic` / `1-Speed Automatic` → `Automatic`
- `Manual` → `Manual`
- `CVT` → `CVT`
- `Semi-Automatic` → `Semi-Automatic`

### Drive Type Mapping
- `Rear-Wheel Drive` / `RWD` → `RWD`
- `Front-Wheel Drive` / `FWD` → `FWD`
- `All-Wheel Drive` / `AWD` → `AWD`
- `Four-Wheel Drive` / `4WD` → `4WD`

### GCC/Import Classification
Maps countries to categories:
- **GCC**: UAE, Saudi Arabia, Kuwait, Qatar, Bahrain, Oman
- **American**: USA, United States
- **Canadian**: Canada
- **Japanese**: Japan
- **Korean**: South Korea, Korea
- **Chinese**: China, PRC
- **European**: Germany, France, Italy, UK, Sweden, Spain, etc.
- **Others**: Everything else

Also uses brand-to-country mapping as fallback.

### Engine Size Normalization
- Converts CC to Liters: `2000cc` → `2.0L` → `2.0` (stripped)
- Handles various formats: `2.0L`, `2.0 L`, `2000cc`, `2000 cc`
- For electric: Extracts battery capacity in kWh

---

## Configuration

Both APIs require Vehicle Databases API configuration:

**Environment Variables:**
```env
VEHICLEDATABASES_API_KEY=your_api_key_here
VEHICLEDATABASES_API_URL=https://api.vehicledatabases.com/advanced-vin-decode/v2/{vin}
VEHICLEDATABASES_OCR_ENDPOINT=https://api.vehicledatabases.com/vin-ocr
VEHICLEDATABASES_TIMEOUT=30
VEHICLEDATABASES_CACHE_EXPIRY=24
```

**Note:** The system now uses the v2 advanced endpoint which provides:
- Detailed specifications array
- Complete dimensions (exterior, interior, cargo, weights)
- Performance metrics (horsepower, torque)
- Transmission details (gears, ratios)
- Color options (exterior and interior)
- Fuel economy (MPG)
- Warranty information
- Standard features list

**Config File:** `config/services.php`

---

## Error Handling

Both APIs handle:
- **VehicleDatabasesException**: API-specific errors (400, 500, etc.)
- **ValidationException**: Invalid input (VIN format, file validation)
- **General Exceptions**: Unexpected errors (500)

All errors are logged with context (VIN, error message, trace).

---

## Performance Considerations

1. **Caching**: Results cached for 24 hours (configurable)
2. **Database Lookups**: Brand/model matching uses database queries
3. **API Calls**: 
   - `/decode-for-listing`: 1-2 API calls (normal + optional raw)
   - `/ocr-and-decode`: 2-3 API calls (OCR + decode + optional raw)
4. **Timeout**: 30 seconds per API call (configurable)

---

## Database Tables Used

- `vin_decoders`: Cache storage for decoded VINs
- `car_brands`: Brand matching and validation
- `car_models`: Model matching
- `body_types`: Body type matching

---

## Notes

- Both endpoints return the **same data structure** after VIN decoding
- `/ocr-and-decode` adds an OCR step before decoding
- Brand validation is critical - returns 422 if brand not found
- Data extraction is complex - searches multiple nested paths in API response
- Raw data fallback provides additional fields when available
- All mappings are case-insensitive and handle variations

