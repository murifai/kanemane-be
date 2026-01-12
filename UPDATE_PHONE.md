# 📱 Update Your Phone Number for WhatsApp Bot

## Quick Update via Tinker

```bash
# Navigate to backend directory
cd backend

# Update your phone number (replace with YOUR WhatsApp number)
php artisan tinker --execute="
\$user = \App\Models\User::find(19);
\$user->phone = '6281234567890';  # Your WhatsApp number with country code
\$user->save();
echo 'Phone updated to: ' . \$user->phone . PHP_EOL;
"
```

## Phone Number Format

Your WhatsApp number should be in international format **without** the `+` or `@c.us`:

| Country | Format | Example |
|---------|--------|---------|
| Indonesia | `628XXXXXXXXX` | `6281234567890` |
| USA | `1XXXXXXXXXX` | `12025551234` |
| Japan | `81XXXXXXXXX` | `819012345678` |

## Verify Phone Number

```bash
php artisan tinker --execute="
\$user = \App\Models\User::find(19);
echo 'User: ' . \$user->name . PHP_EOL;
echo 'Phone: ' . (\$user->phone ?: 'NOT SET') . PHP_EOL;
echo 'Email: ' . \$user->email . PHP_EOL;
"
```

## Test WhatsApp Bot

Once phone is set:

1. **Start WAHA**:
```bash
cd /Users/murifai/Code/kanemane
docker-compose -f docker-compose.dev.yml up -d
```

2. **Start Laravel**:
```bash
cd backend
php artisan serve
```

3. **Send test message** from WhatsApp:
```
saldo
```

4. **Check logs**:
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# WAHA logs
docker logs -f kanemane-waha-dev
```

## Expected Response

When you send "saldo", you should receive:
```
💰 *Saldo Anda*

¥[your balance]
```

## Troubleshooting

### If you get "Nomor belum terdaftar"

Your phone number doesn't match. Check:
```bash
php artisan tinker --execute="
echo 'Your registered phone: ' . \App\Models\User::find(19)->phone . PHP_EOL;
echo 'Format should be: 6281234567890 (no + or spaces)' . PHP_EOL;
"
```

### If you get "Anda belum memiliki asset"

Create a personal asset:
```bash
php artisan tinker --execute="
\$user = \App\Models\User::find(19);
\$asset = \App\Models\Asset::create([
    'owner_type' => 'App\\\Models\\\User',
    'owner_id' => \$user->id,
    'name' => 'Personal Wallet',
    'type' => 'personal',
    'balance' => 100000,
    'currency' => 'JPY',
    'created_by' => \$user->id,
]);
echo 'Asset created: ' . \$asset->name . ' (¥' . number_format(\$asset->balance) . ')' . PHP_EOL;
"
```
