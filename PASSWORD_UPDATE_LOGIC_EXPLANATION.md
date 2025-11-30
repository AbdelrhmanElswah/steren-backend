# Password Update Logic - `/api/auth/profile/complete`

## Summary

**YES, all 3 password fields MUST be sent together** if you want to update the password.

## Required Fields for Password Update

```
current_password: "oldpassword123"
new_password: "newpassword123"
new_password_confirmation: "newpassword123"
```

**All three fields are required together. You cannot send just one or two of them.**

---

## How It Works

### Step 1: Validation Rules

The validation rules enforce that:
- All password fields are **optional** (marked with `sometimes`)
- BUT if you provide **any** password field, you must provide **all three**

```php
// Validation rules:
'current_password' => 'sometimes|required_with:new_password|string',
'new_password' => 'sometimes|required_with:current_password|string|min:8|confirmed',
'new_password_confirmation' => 'sometimes|required_with:new_password|string|min:8',
```

### Step 2: Custom Validation (All-or-Nothing Rule)

Additional validation ensures all-or-nothing behavior:

```php
// If ANY password field is provided
if (has_current_password || has_new_password || has_new_password_confirmation) {
    // Then ALL three must be provided
    if (!all_three_provided) {
        // Error: "To update password, you must provide current_password, new_password, 
        //         and new_password_confirmation together."
    }
}
```

### Step 3: Controller Logic

**Password validation happens FIRST, before any other updates:**

1. **Check if password update is requested:**
   ```php
   if (isset($validated['new_password'])) {
   ```

2. **Verify current password FIRST (before any other updates):**
   ```php
   if (!Hash::check($validated['current_password'], $user->password)) {
       DB::rollBack(); // Rollback transaction
       return error('Current password is incorrect');
       // No other fields are updated if password fails
   }
   ```

3. **Only proceed with updates if password validation passes**

4. **Update password later in the flow:**
   ```php
   $user->update([
       'password' => Hash::make($validated['new_password'])
   ]);
   ```

**Important:** If password validation fails, the entire transaction is rolled back and NOTHING gets updated (no profile info, no picture, nothing).

---

## Valid Request Examples

### ✅ Valid: Update Password Only
```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

### ✅ Valid: Update Password + Profile Info
```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

### ✅ Valid: Update Password + Picture + Profile Info
```json
{
  "full_name": "John Doe",
  "profile_picture": [file],
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

### ✅ Valid: Update Only Profile Info (No Password)
```json
{
  "full_name": "John Doe",
  "email": "john@example.com"
}
```

---

## Invalid Request Examples

### ❌ Invalid: Missing current_password
```json
{
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```
**Error:** "Current password is required when updating password"

### ❌ Invalid: Missing new_password
```json
{
  "current_password": "oldpassword123",
  "new_password_confirmation": "newpassword123"
}
```
**Error:** "New password is required when updating password"

### ❌ Invalid: Missing new_password_confirmation
```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123"
}
```
**Error:** "New password confirmation is required"

### ❌ Invalid: Only one field provided
```json
{
  "current_password": "oldpassword123"
}
```
**Error:** "To update password, you must provide current_password, new_password, and new_password_confirmation together."

### ❌ Invalid: Wrong current password
```json
{
  "current_password": "wrongpassword",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```
**Error:** "Current password is incorrect"

### ❌ Invalid: Passwords don't match
```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "differentpassword"
}
```
**Error:** "New password confirmation does not match"

---

## Validation Flow Diagram

```
Request Received
       |
       v
┌──────────────────────┐
│ Any password field?  │
│ (current/new/confirm)│
└──────────────────────┘
       |
       | YES
       v
┌──────────────────────┐
│ All 3 fields present?│
└──────────────────────┘
       |
       | NO                | YES
       v                   v
   ┌──────┐         ┌─────────────┐
   │ERROR │         │ Validate:   │
   └──────┘         │ - min 8     │
                    │ - confirmed │
                    └─────────────┘
                           |
                           v
                    ┌──────────────┐
                    │ Verify current│
                    │ password match│
                    └──────────────┘
                           |
                           | Valid
                           v
                    ┌──────────────┐
                    │ Update password│
                    └──────────────┘
```

---

## Summary Table

| Scenario | current_password | new_password | new_password_confirmation | Result |
|----------|-----------------|--------------|---------------------------|---------|
| No password update | ❌ | ❌ | ❌ | ✅ Valid |
| Partial (only current) | ✅ | ❌ | ❌ | ❌ Invalid |
| Partial (only new) | ❌ | ✅ | ❌ | ❌ Invalid |
| Partial (only confirm) | ❌ | ❌ | ✅ | ❌ Invalid |
| Partial (2 fields) | ✅ | ✅ | ❌ | ❌ Invalid |
| Complete (all 3) | ✅ | ✅ | ✅ | ✅ Valid* |

*Valid if current password is correct and new passwords match

---

## Key Points

1. **All-or-Nothing:** If you send ANY password field, you MUST send ALL THREE
2. **Current Password Required:** Always required to verify the user's identity
3. **Confirmation Required:** Prevents typos in new password
4. **Transaction Safety:** If password verification fails, entire transaction rolls back
5. **Optional Feature:** Password update is completely optional - you can update other fields without touching password

---

## Code Example

```bash
# Correct way to update password
curl -X PUT "https://api.example.com/api/auth/profile/complete" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: multipart/form-data" \
  -F "current_password=oldpassword123" \
  -F "new_password=newpassword123" \
  -F "new_password_confirmation=newpassword123"
```

