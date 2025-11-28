# User Profile Update API - Payload Examples

## 1. Update Profile Information Only
**Endpoint:** `PUT /api/auth/profile`  
**Content-Type:** `application/json`  
**Authentication:** Required (Bearer token)

### Example 1: Update Full Name Only
```json
{
  "full_name": "John Doe"
}
```

### Example 2: Update Multiple Fields
```json
{
  "full_name": "John Doe",
  "email": "john.doe@example.com",
  "phone": "+1234567890",
  "birthday": "1990-01-15",
  "notifications_enabled": true,
  "marketing_emails_enabled": false,
  "language_id": 1
}
```

### Example 3: Update Notification Preferences
```json
{
  "notification_preferences": {
    "new_listings": true,
    "price_drops": true,
    "messages": false,
    "promotions": true
  }
}
```

### Example 4: Update All Fields
```json
{
  "full_name": "John Doe",
  "email": "john.doe@example.com",
  "phone": "+1234567890",
  "birthday": "1990-01-15",
  "notifications_enabled": true,
  "marketing_emails_enabled": false,
  "notification_preferences": {
    "new_listings": true,
    "price_drops": true,
    "messages": false
  },
  "language_id": 1
}
```

**Note:** All fields are optional. Send only the fields you want to update.

---

## 2. Update Profile Picture Only
**Endpoint:** `POST /api/auth/profile/picture`  
**Content-Type:** `multipart/form-data`  
**Authentication:** Required (Bearer token)

### Example Request
```
POST /api/auth/profile/picture
Content-Type: multipart/form-data
Authorization: Bearer {token}

profile_picture: [binary file data]
```

### cURL Example
```bash
curl -X POST "https://api.example.com/api/auth/profile/picture" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: multipart/form-data" \
  -F "profile_picture=@/path/to/image.jpg"
```

### Flutter/Dart Example
```dart
var request = http.MultipartRequest(
  'POST',
  Uri.parse('https://api.example.com/api/auth/profile/picture'),
);

request.headers['Authorization'] = 'Bearer $token';
request.files.add(
  await http.MultipartFile.fromPath(
    'profile_picture',
    imagePath,
  ),
);

var response = await request.send();
```

**File Requirements:**
- Format: JPEG, PNG, JPG, GIF, or WebP
- Max size: 5MB (5120 KB)
- Field name: `profile_picture`

---

## 3. Update Profile Complete (Info + Picture in One Request)
**Endpoint:** `PUT /api/auth/profile/complete`  
**Content-Type:** `multipart/form-data`  
**Authentication:** Required (Bearer token)

### Example 1: Update Profile Info Only (No Picture)
```
PUT /api/auth/profile/complete
Content-Type: multipart/form-data
Authorization: Bearer {token}

full_name: John Doe
email: john.doe@example.com
phone: +1234567890
birthday: 1990-01-15
notifications_enabled: true
marketing_emails_enabled: false
language_id: 1
```

### Example 2: Update Profile Picture Only
```
PUT /api/auth/profile/complete
Content-Type: multipart/form-data
Authorization: Bearer {token}

profile_picture: [binary file data]
```

### Example 3: Update Both Profile Info and Picture
```
PUT /api/auth/profile/complete
Content-Type: multipart/form-data
Authorization: Bearer {token}

full_name: John Doe
email: john.doe@example.com
phone: +1234567890
birthday: 1990-01-15
notifications_enabled: true
marketing_emails_enabled: false
notification_preferences[new_listings]: true
notification_preferences[price_drops]: true
notification_preferences[messages]: false
language_id: 1
profile_picture: [binary file data]
```

### cURL Example (Update Both)
```bash
curl -X PUT "https://api.example.com/api/auth/profile/complete" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: multipart/form-data" \
  -F "full_name=John Doe" \
  -F "email=john.doe@example.com" \
  -F "phone=+1234567890" \
  -F "birthday=1990-01-15" \
  -F "notifications_enabled=true" \
  -F "marketing_emails_enabled=false" \
  -F "language_id=1" \
  -F "profile_picture=@/path/to/image.jpg"
```

### Flutter/Dart Example (Update Both)
```dart
var request = http.MultipartRequest(
  'PUT',
  Uri.parse('https://api.example.com/api/auth/profile/complete'),
);

request.headers['Authorization'] = 'Bearer $token';

// Add text fields
request.fields['full_name'] = 'John Doe';
request.fields['email'] = 'john.doe@example.com';
request.fields['phone'] = '+1234567890';
request.fields['birthday'] = '1990-01-15';
request.fields['notifications_enabled'] = 'true';
request.fields['marketing_emails_enabled'] = 'false';
request.fields['language_id'] = '1';

// Add notification preferences as JSON string
request.fields['notification_preferences'] = jsonEncode({
  'new_listings': true,
  'price_drops': true,
  'messages': false,
});

// Add file
request.files.add(
  await http.MultipartFile.fromPath(
    'profile_picture',
    imagePath,
  ),
);

var response = await request.send();
```

### JavaScript/Fetch Example (Update Both)
```javascript
const formData = new FormData();
formData.append('full_name', 'John Doe');
formData.append('email', 'john.doe@example.com');
formData.append('phone', '+1234567890');
formData.append('birthday', '1990-01-15');
formData.append('notifications_enabled', 'true');
formData.append('marketing_emails_enabled', 'false');
formData.append('language_id', '1');
formData.append('notification_preferences', JSON.stringify({
  new_listings: true,
  price_drops: true,
  messages: false
}));

// Get file from input
const fileInput = document.querySelector('input[type="file"]');
if (fileInput.files[0]) {
  formData.append('profile_picture', fileInput.files[0]);
}

fetch('https://api.example.com/api/auth/profile/complete', {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

**Note:** 
- All fields are optional in this endpoint
- You can update just profile info, just picture, or both
- When updating both, use `multipart/form-data`
- When updating only profile info (no file), you can use either `multipart/form-data` or `application/json`

---

## Field Descriptions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `full_name` | string | No | User's full name (max 255 chars) |
| `email` | string (email) | No | User's email address (must be unique) |
| `phone` | string | No | User's phone number (max 20 chars, must be unique) |
| `birthday` | date | No | User's birthday (format: YYYY-MM-DD, must be before today) |
| `notifications_enabled` | boolean | No | Enable/disable notifications |
| `marketing_emails_enabled` | boolean | No | Enable/disable marketing emails |
| `notification_preferences` | object | No | Custom notification preferences object |
| `language_id` | integer | No | ID of the selected language (must exist in languages table) |
| `profile_picture` | file | No | Profile picture image file (JPEG, PNG, JPG, GIF, WebP, max 5MB) |

---

## Response Format

All endpoints return the same response structure:

### Success Response (200)
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "user": {
      "id": 1,
      "full_name": "John Doe",
      "email": "john.doe@example.com",
      "phone": "+1234567890",
      "birthday": "1990-01-15",
      // ... other user fields
    },
    "profile_picture": "https://s3.amazonaws.com/bucket/path/to/image.jpg" // Only if picture was updated
  }
}
```

### Error Response (400/422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["This email is already taken"],
    "phone": ["This phone number is already taken"]
  }
}
```

---

## Odoo Sync

All profile updates are automatically synced to Odoo if:
- User has a `partner_id` or `odoo_id`
- At least one field that Odoo supports was updated

**Fields synced to Odoo:**
- `full_name` → `full_name`
- `phone` → `phone`
- `email` → `email`
- `birthday` → `birth_date` (formatted as YYYY-MM-DD)
- `profile_picture` → `profile_img` (URL string)

**Note:** Odoo sync failures are logged but don't fail the request. The Laravel update will succeed even if Odoo sync fails.

