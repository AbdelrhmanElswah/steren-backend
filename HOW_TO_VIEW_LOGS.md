# How to View Laravel Logs

## Log File Location

Laravel logs are stored in: **`storage/logs/laravel.log`**

## Viewing Logs

### 1. View Last 50 Lines (Most Recent)
```bash
tail -50 storage/logs/laravel.log
```

### 2. View Last 100 Lines
```bash
tail -100 storage/logs/laravel.log
```

### 3. Follow Logs in Real-Time (Live Monitoring)
```bash
tail -f storage/logs/laravel.log
```
Press `Ctrl+C` to stop following.

### 4. Search for Specific Log Messages
```bash
# Search for "Profile complete" logs
grep -i "Profile complete" storage/logs/laravel.log

# Search for specific user ID
grep "user_id.*5" storage/logs/laravel.log

# Search with context (5 lines before and after)
grep -A 5 -B 5 "Profile complete" storage/logs/laravel.log
```

### 5. View Logs from Today Only
```bash
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log
```

### 6. View Logs with Line Numbers
```bash
grep -n "Profile complete" storage/logs/laravel.log
```

### 7. View Only Error Logs
```bash
grep "ERROR" storage/logs/laravel.log | tail -50
```

### 8. View Only Info Logs (Our Debug Logs)
```bash
grep "local.INFO" storage/logs/laravel.log | tail -50
```

## Log Levels

Make sure your `.env` file has the correct log level:

```env
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

- `debug` - Shows all logs (INFO, WARNING, ERROR, etc.)
- `info` - Shows INFO and above
- `warning` - Shows WARNING and above
- `error` - Shows only ERROR logs

## What to Look For

When testing `/api/auth/profile/complete`, look for these log entries:

1. **Request Received:**
   ```
   [timestamp] local.INFO: Profile complete update request received
   ```

2. **Field Detection:**
   - `validated_keys` - Shows which fields passed validation
   - `request_all` - Shows all data sent in the request
   - `request_has_full_name` - Shows if full_name was detected
   - `content_type` - Shows the Content-Type header

3. **Update Status:**
   ```
   [timestamp] local.INFO: Updating user profile data
   [timestamp] local.INFO: User profile updated - verification
   ```

4. **Errors:**
   ```
   [timestamp] local.ERROR: Failed to update user profile
   ```

## Using Laravel Telescope (If Installed)

If you have Laravel Telescope installed, you can also view logs through the web interface:

1. Visit: `http://your-domain/telescope/logs`
2. Filter by level, search terms, etc.

## Cloud Hosting

If you're using a cloud hosting service (like Laravel Forge, DigitalOcean, etc.):

1. **SSH into your server:**
   ```bash
   ssh user@your-server-ip
   ```

2. **Navigate to your project:**
   ```bash
   cd /path/to/your/project
   ```

3. **View logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Quick Debug Command

To see all profile-related logs from the last hour:

```bash
grep -E "(Profile complete|profile update|UserProfileController)" storage/logs/laravel.log | grep "$(date +%Y-%m-%d)" | tail -50
```

## Clear Logs (If Needed)

⚠️ **Warning:** This will delete all logs!

```bash
# Backup first
cp storage/logs/laravel.log storage/logs/laravel.log.backup

# Clear logs
echo "" > storage/logs/laravel.log
```

