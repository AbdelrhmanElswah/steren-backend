# Profile Complete API - Payload Examples

## Endpoint: `PUT /api/auth/profile/complete`

This is a unified API endpoint that allows updating **all user profile data** in a single request:
- Profile information (name, email, phone, birthday, preferences)
- Profile picture
- Password

**Content-Type:** `multipart/form-data`  
**Authentication:** Required (Bearer token)

---

## Supported Payload Combinations

### Example 1: Update Profile Info Only (No Picture, No Password)
```
PUT /api/auth/profile/complete
Content-Type: multipart/form-data
Authorization: Bearer {token}

full_name: John Doe
email: john.doe@example.com
phone: +9647501234567
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

### Example 3: Update Password Only
```
PUT /api/auth/profile/complete
Content-Type: multipart/form-data
Authorization: Bearer {token}

current_password: oldpassword123
new_password: newpassword123
new_password_confirmation: newpassword123
```

### Example 4: Update Profile Info + Picture
```
PUT /api/auth/profile/complete
Content-Type: multipart/form-data
Authorization: Bearer {token}

full_name: John Doe
email: john.doe@example.com
phone: +9647501234567
birthday: 1990-01-15
notifications_enabled: true
marketing_emails_enabled: false
language_id: 1
profile_picture: [binary file data]
```

### Example 5: Update Profile Info + Password
```
PUT /api/auth/profile/complete
Content-Type: multipart/form-data
Authorization: Bearer {token}

full_name: John Doe
email: john.doe@example.com
phone: +9647501234567
birthday: 1990-01-15
current_password: oldpassword123
new_password: newpassword123
new_password_confirmation: newpassword123
```

### Example 6: Update Picture + Password
```
PUT /api/auth/profile/complete
Content-Type: multipart/form-data
Authorization: Bearer {token}

profile_picture: [binary file data]
current_password: oldpassword123
new_password: newpassword123
new_password_confirmation: newpassword123
```

### Example 7: Update Everything (Profile Info + Picture + Password)
```
PUT /api/auth/profile/complete
Content-Type: multipart/form-data
Authorization: Bearer {token}

full_name: John Doe
email: john.doe@example.com
phone: +9647501234567
birthday: 1990-01-15
notifications_enabled: true
marketing_emails_enabled: false
notification_preferences[new_listings]: true
notification_preferences[price_drops]: true
notification_preferences[messages]: false
language_id: 1
profile_picture: [binary file data]
current_password: oldpassword123
new_password: newpassword123
new_password_confirmation: newpassword123
```

---

## Field Descriptions

### Profile Information Fields (All Optional)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `full_name` | string | No | User's full name (max 255 chars) |
| `email` | string (email) | No | User's email address (must be unique if provided) |
| `phone` | string | No | User's phone number (max 20 chars, must be unique if provided) |
| `birthday` | date | No | User's birthday (format: YYYY-MM-DD, must be before today) |
| `notifications_enabled` | boolean | No | Enable/disable notifications |
| `marketing_emails_enabled` | boolean | No | Enable/disable marketing emails |
| `notification_preferences` | object | No | Custom notification preferences object |
| `language_id` | integer | No | ID of the selected language (must exist in languages table) |

### Profile Picture Field (Optional)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `profile_picture` | file | No | Profile picture image file (JPEG, PNG, JPG, GIF, WebP, max 5MB) |

### Password Fields (All Optional, but All Required Together if Updating Password)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `current_password` | string | Conditional | Current password (required if `new_password` is provided) |
| `new_password` | string | Conditional | New password (min 8 chars, required if `current_password` is provided) |
| `new_password_confirmation` | string | Conditional | Confirm new password (must match `new_password`) |

**Note:** To update password, you must provide all three fields: `current_password`, `new_password`, and `new_password_confirmation`.

---

## Code Examples

### cURL Example (Update Everything)
```bash
curl -X PUT "https://api.example.com/api/auth/profile/complete" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: multipart/form-data" \
  -F "full_name=John Doe" \
  -F "email=john.doe@example.com" \
  -F "phone=+9647501234567" \
  -F "birthday=1990-01-15" \
  -F "notifications_enabled=true" \
  -F "marketing_emails_enabled=false" \
  -F "language_id=1" \
  -F "current_password=oldpassword123" \
  -F "new_password=newpassword123" \
  -F "new_password_confirmation=newpassword123" \
  -F "profile_picture=@/path/to/image.jpg"
```

### Flutter/Dart Example (Update Everything)
```dart
var request = http.MultipartRequest(
  'PUT',
  Uri.parse('https://api.example.com/api/auth/profile/complete'),
);

request.headers['Authorization'] = 'Bearer $token';

// Add text fields
request.fields['full_name'] = 'John Doe';
request.fields['email'] = 'john.doe@example.com';
request.fields['phone'] = '+9647501234567';
request.fields['birthday'] = '1990-01-15';
request.fields['notifications_enabled'] = 'true';
request.fields['marketing_emails_enabled'] = 'false';
request.fields['language_id'] = '1';
request.fields['current_password'] = 'oldpassword123';
request.fields['new_password'] = 'newpassword123';
request.fields['new_password_confirmation'] = 'newpassword123';

// Add notification preferences as JSON string
request.fields['notification_preferences'] = jsonEncode({
  'new_listings': true,
  'price_drops': true,
  'messages': false,
});

// Add file
if (imagePath != null) {
  request.files.add(
    await http.MultipartFile.fromPath(
      'profile_picture',
      imagePath,
    ),
  );
}

var response = await request.send();
var responseBody = await response.stream.bytesToString();
var data = jsonDecode(responseBody);
```

### JavaScript/Fetch Example (Update Everything)
```javascript
const formData = new FormData();

// Add text fields
formData.append('full_name', 'John Doe');
formData.append('email', 'john.doe@example.com');
formData.append('phone', '+9647501234567');
formData.append('birthday', '1990-01-15');
formData.append('notifications_enabled', 'true');
formData.append('marketing_emails_enabled', 'false');
formData.append('language_id', '1');
formData.append('current_password', 'oldpassword123');
formData.append('new_password', 'newpassword123');
formData.append('new_password_confirmation', 'newpassword123');
formData.append('notification_preferences', JSON.stringify({
  new_listings: true,
  price_drops: true,
  messages: false
}));

// Add file
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

### PHP Example (Update Everything)
```php
$client = new \GuzzleHttp\Client();

$multipart = [
    ['name' => 'full_name', 'contents' => 'John Doe'],
    ['name' => 'email', 'contents' => 'john.doe@example.com'],
    ['name' => 'phone', 'contents' => '+9647501234567'],
    ['name' => 'birthday', 'contents' => '1990-01-15'],
    ['name' => 'notifications_enabled', 'contents' => 'true'],
    ['name' => 'marketing_emails_enabled', 'contents' => 'false'],
    ['name' => 'language_id', 'contents' => '1'],
    ['name' => 'current_password', 'contents' => 'oldpassword123'],
    ['name' => 'new_password', 'contents' => 'newpassword123'],
    ['name' => 'new_password_confirmation', 'contents' => 'newpassword123'],
    ['name' => 'notification_preferences', 'contents' => json_encode([
        'new_listings' => true,
        'price_drops' => true,
        'messages' => false,
    ])],
];

// Add file if provided
if ($imagePath) {
    $multipart[] = [
        'name' => 'profile_picture',
        'contents' => fopen($imagePath, 'r'),
        'filename' => basename($imagePath)
    ];
}

$response = $client->put('https://api.example.com/api/auth/profile/complete', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
    ],
    'multipart' => $multipart
]);

$data = json_decode($response->getBody(), true);
```

---

## Response Format

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
      "phone": "+9647501234567",
      "birthday": "1990-01-15",
      // ... other user fields
    },
    "profile_picture": "https://s3.amazonaws.com/bucket/path/to/image.jpg"
  }
}
```

**Note:** `profile_picture` is only included in the response if a picture was updated.

### Error Response (400/422)
```json
{
  "success": false,
  "message": "Validation failed",
  "data": {
    "current_password": ["Current password is incorrect"],
    "new_password": ["New password confirmation does not match"]
  }
}
```

---

## Important Notes

1. **All fields are optional** - You can update any combination of fields
2. **Password update requires all three password fields** - `current_password`, `new_password`, and `new_password_confirmation` must all be provided together
3. **Password validation** - Current password must be correct, new password must be at least 8 characters and match confirmation
4. **Odoo sync** - All profile updates (except password) are automatically synced to Odoo if user has `partner_id` or `odoo_id`
5. **File upload** - Profile picture must be JPEG, PNG, JPG, GIF, or WebP format, max 5MB
6. **Transaction safety** - All updates happen in a database transaction, so either all updates succeed or all fail

---

## Migration from Separate Endpoints

Previously, you would need to call multiple endpoints:
- `PUT /api/auth/profile` - for profile info
- `POST /api/auth/profile/picture` - for picture
- `PUT /api/auth/profile/password` - for password

**Now:** Use only `PUT /api/auth/profile/complete` for all updates!

