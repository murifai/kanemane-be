# 🚀 WAHA Integration Quick Reference

Quick reference guide for developers working with WAHA integration.

---

## 🔧 Environment Setup

### Local Development (.env)
```env
WAHA_URL=http://localhost:3000
WAHA_SESSION=kanemane-dev
WAHA_WEBHOOK_SECRET=local-dev-secret-key
```

### Production (.env)
```env
WAHA_URL=http://waha:3000  # or https://waha.yourdomain.com
WAHA_SESSION=kanemane-production
WAHA_WEBHOOK_SECRET=your-super-secret-production-key
```

---

## 📞 Phone Number Format

### Input Formats (Accepted)
```php
+6281234567890
081234567890
6281234567890
+62 812-3456-7890
```

### Output Format (WAHA Chat ID)
```php
6281234567890@c.us
```

### Conversion Example
```php
// In WhatsAppService
private function formatChatId(string $phone): string
{
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    return $cleaned . '@c.us';
}
```

---

## 📨 Sending Messages

### Text Message
```php
$whatsappService->sendMessage('+6281234567890', 'Hello World!');
```

**WAHA API Call**:
```bash
curl -X POST http://localhost:3000/api/sendText \
  -H "Content-Type: application/json" \
  -d '{
    "chatId": "6281234567890@c.us",
    "text": "Hello World!",
    "session": "kanemane-dev"
  }'
```

### Interactive Buttons
```php
$whatsappService->sendButtons('+6281234567890', 'Choose an option:', [
    [
        'reply' => [
            'id' => 'option_1',
            'title' => 'Option 1'
        ]
    ],
    [
        'reply' => [
            'id' => 'option_2',
            'title' => 'Option 2'
        ]
    ]
]);
```

**WAHA API Call**:
```bash
curl -X POST http://localhost:3000/api/sendButtons \
  -H "Content-Type: application/json" \
  -d '{
    "chatId": "6281234567890@c.us",
    "text": "Choose an option:",
    "buttons": [
      {"id": "option_1", "text": "Option 1"},
      {"id": "option_2", "text": "Option 2"}
    ],
    "session": "kanemane-dev"
  }'
```

---

## 📥 Receiving Messages (Webhook)

### Webhook Endpoint
```
POST /api/webhook/whatsapp
```

### WAHA Webhook Payload (Text Message)
```json
{
  "event": "message",
  "session": "kanemane-dev",
  "payload": {
    "id": "true_6281234567890@c.us_3EB0C7A94E97D4B2C77E",
    "from": "6281234567890@c.us",
    "body": "makan 850",
    "hasMedia": false,
    "timestamp": 1642012345
  }
}
```

### WAHA Webhook Payload (Button Reply)
```json
{
  "event": "message",
  "session": "kanemane-dev",
  "payload": {
    "id": "true_6281234567890@c.us_3EB0C7A94E97D4B2C77E",
    "from": "6281234567890@c.us",
    "selectedButtonId": "confirm_receipt",
    "body": "✅ Ya, simpan",
    "timestamp": 1642012345
  }
}
```

### WAHA Webhook Payload (Media/Image)
```json
{
  "event": "message",
  "session": "kanemane-dev",
  "payload": {
    "id": "true_6281234567890@c.us_3EB0C7A94E97D4B2C77E",
    "from": "6281234567890@c.us",
    "hasMedia": true,
    "mediaUrl": "http://localhost:3000/api/files/true_6281234567890@c.us_3EB0C7A94E97D4B2C77E",
    "mimetype": "image/jpeg",
    "timestamp": 1642012345
  }
}
```

---

## 🖼️ Media Download

### Code Example
```php
private function downloadMedia(string $messageId, string $session): ?string
{
    $response = Http::get("{$this->baseUrl}/api/files/{$messageId}", [
        'session' => $session
    ]);

    if (!$response->successful()) {
        return null;
    }

    // Save to temp file
    $tempPath = storage_path('app/temp_' . $messageId . '.jpg');
    file_put_contents($tempPath, $response->body());

    return $tempPath;
}
```

### WAHA API Call
```bash
curl -o receipt.jpg "http://localhost:3000/api/files/MESSAGE_ID?session=kanemane-dev"
```

---

## 🔐 Webhook Security (Optional)

### Enable Signature Verification

**In .env**:
```env
WAHA_WEBHOOK_SECRET=your-secret-key-here
```

**In WebhookController**:
```php
if (config('services.whatsapp.webhook_secret')) {
    $signature = $request->header('X-Webhook-Signature');
    $expectedSignature = hash_hmac('sha256', $request->getContent(), config('services.whatsapp.webhook_secret'));

    if (!hash_equals($expectedSignature, $signature ?? '')) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }
}
```

### Configure WAHA to Send Signature

**In docker-compose.yml**:
```yaml
environment:
  - WHATSAPP_HOOK_HMAC_KEY=your-secret-key-here
```

---

## 🧪 Testing Commands

### Check WAHA Health
```bash
curl http://localhost:3000/api/health
```

**Expected**:
```json
{"status": "ok"}
```

### Check Session Status
```bash
curl http://localhost:3000/api/sessions/kanemane-dev
```

**Expected**:
```json
{
  "name": "kanemane-dev",
  "status": "WORKING",
  "me": {
    "id": "6281234567890@c.us",
    "pushName": "Your Name"
  }
}
```

### Test Webhook Manually
```bash
curl -X POST http://localhost:8000/api/webhook/whatsapp \
  -H "Content-Type: application/json" \
  -d '{
    "event": "message",
    "session": "kanemane-dev",
    "payload": {
      "from": "6281234567890@c.us",
      "body": "test saldo",
      "hasMedia": false
    }
  }'
```

---

## 📊 Common Commands

### View Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

### View WAHA Logs
```bash
# Local
docker logs -f kanemane-waha-dev

# Production
docker logs -f kanemane-waha-prod
```

### Clear Laravel Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Restart Queue Worker
```bash
php artisan queue:restart
```

---

## 🐛 Common Issues & Solutions

### Issue: Messages Not Received

**Check**:
1. WAHA is running: `docker ps | grep waha`
2. Session is active: `curl http://localhost:3000/api/sessions/kanemane-dev`
3. Webhook URL is correct in docker-compose.yml
4. Laravel is running: `curl http://localhost:8000/api/health`

**Solution**:
```bash
# Restart WAHA
docker restart kanemane-waha-dev

# Check logs
docker logs kanemane-waha-dev
tail -f backend/storage/logs/laravel.log
```

---

### Issue: QR Code Not Loading

**Solution**:
```bash
# Recreate session
curl -X DELETE http://localhost:3000/api/sessions/kanemane-dev
curl -X POST http://localhost:3000/api/sessions -d '{"name":"kanemane-dev"}'

# Get new QR
open http://localhost:3000/api/screenshot?session=kanemane-dev
```

---

### Issue: "Invalid signature" Error

**Check**:
1. `WAHA_WEBHOOK_SECRET` in Laravel .env matches `WHATSAPP_HOOK_HMAC_KEY` in docker-compose.yml
2. Both are set or both are unset (don't mix)

**Solution**:
```bash
# Option 1: Disable signature verification
# Remove WAHA_WEBHOOK_SECRET from .env
# Remove WHATSAPP_HOOK_HMAC_KEY from docker-compose.yml

# Option 2: Set matching secrets
# .env: WAHA_WEBHOOK_SECRET=my-secret
# docker-compose.yml: WHATSAPP_HOOK_HMAC_KEY=my-secret
```

---

### Issue: Media Download Failed

**Check**:
1. Message ID is correct
2. Session name matches
3. WAHA has access to media

**Solution**:
```bash
# Test media download manually
curl -o test.jpg "http://localhost:3000/api/files/MESSAGE_ID?session=kanemane-dev"

# Check file permissions
ls -la storage/app/
chmod 755 storage/app/
```

---

## 📚 API Reference

### WAHA Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/health` | GET | Health check |
| `/api/sessions` | GET | List sessions |
| `/api/sessions` | POST | Create session |
| `/api/sessions/{session}` | GET | Get session info |
| `/api/sendText` | POST | Send text message |
| `/api/sendButtons` | POST | Send interactive buttons |
| `/api/files/{messageId}` | GET | Download media |
| `/api/screenshot?session={session}` | GET | Get QR code |

### Full Documentation
- [WAHA API Docs](https://waha.devlike.pro/docs/overview/quick-start/)
- [WAHA GitHub](https://github.com/devlikeapro/waha)

---

## 🔄 Workflow Examples

### User Sends Text Expense
```
User WhatsApp → "makan 850"
  ↓
WAHA receives message
  ↓
WAHA webhook → POST /api/webhook/whatsapp
  ↓
WebhookController receives payload
  ↓
WhatsAppService->handleIncomingMessage()
  ↓
WhatsAppService->handleTextMessage()
  ↓
GeminiService->parseText() → {category: "food", amount: 850}
  ↓
Create Expense record
Update Asset balance
  ↓
WhatsAppService->sendMessage() → Confirmation
```

### User Sends Receipt Photo
```
User WhatsApp → [Image]
  ↓
WAHA receives media message
  ↓
WAHA webhook → POST /api/webhook/whatsapp
  ↓
WebhookController receives payload
  ↓
WhatsAppService->handleIncomingMessage()
  ↓
WhatsAppService->handleMediaMessage()
  ↓
downloadMedia() → temp file
  ↓
GeminiService->scanReceipt() → {merchant, amount, category, date}
  ↓
Cache scan result (5 minutes)
  ↓
WhatsAppService->sendButtons() → Confirm/Cancel
```

### User Clicks Button
```
User WhatsApp → Clicks "✅ Ya, simpan"
  ↓
WAHA receives button reply
  ↓
WAHA webhook → POST /api/webhook/whatsapp
  ↓
WebhookController receives payload
  ↓
WhatsAppService->handleIncomingMessage()
  ↓
WhatsAppService->handleButtonReply('confirm_receipt')
  ↓
Get cached scan result
Create Expense record
Update Asset balance
Clear cache
  ↓
WhatsAppService->sendMessage() → Success confirmation
```

---

## 💡 Tips & Best Practices

### Performance
- Keep webhook handlers fast (<500ms)
- Use Laravel queues for heavy processing
- Cache frequently accessed data
- Clean up temp files after media processing

### Security
- Always use HTTPS in production
- Set strong webhook secrets
- Validate phone numbers before processing
- Rate limit webhook endpoint
- Never log sensitive data

### Reliability
- Implement retry logic for failed API calls
- Monitor WAHA uptime and session status
- Set up error alerting
- Keep session data backed up
- Test failover scenarios

### Development
- Use different sessions for dev/staging/prod
- Test with multiple users
- Monitor logs during development
- Use Docker for consistent environments
- Version control docker-compose files

---

**Last Updated**: 2026-01-12
**Author**: Kanemane Development Team
