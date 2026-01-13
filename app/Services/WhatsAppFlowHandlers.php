    /**
     * Handle export period selection
     */
    private function handleExportPeriodSelection(string $text, User $user, string $from): bool
    {
        $choice = trim($text);
        
        if (!in_array($choice, ['1', '2', '3', '4'])) {
            $this->sendMessage($from, "❌ Pilihan tidak valid. Ketik angka 1-4 atau 'batal'");
            return true;
        }
        
        $periods = [
            '1' => ['name' => 'Bulan ini', 'months' => 1],
            '2' => ['name' => '3 bulan terakhir', 'months' => 3],
            '3' => ['name' => '6 bulan terakhir', 'months' => 6],
            '4' => ['name' => 'Tahun ini', 'months' => 12],
        ];
        
        $period = $periods[$choice];
        ConversationState::clear($from);
        
        $this->sendMessage($from, "⏳ Membuat laporan {$period['name']}...");
        
        try {
            $reportService = app(\App\Services\ReportService::class);
            $startDate = now()->subMonths($period['months'])->startOfMonth();
            $endDate = now()->endOfMonth();
            
            $filename = "Laporan_{$period['name']}_" . now()->format('Y-m-d') . ".xlsx";
            $filepath = "exports/{$user->id}/" . uniqid() . ".xlsx";
            
            $excel = $reportService->generateReport($user, $startDate, $endDate);
            \Maatwebsite\Excel\Facades\Excel::store($excel, $filepath, 'local');
            
            $token = \Illuminate\Support\Str::uuid();
            $export = \App\Models\Export::create([
                'user_id' => $user->id,
                'token' => $token,
                'filename' => $filename,
                'filepath' => $filepath,
                'period' => $period['name'],
                'expires_at' => now()->addHours(24),
            ]);
            
            $downloadUrl = config('app.url') . "/api/exports/{$token}";
            
            $message = "✅ *Laporan {$period['name']} siap!*\n\n";
            $message .= "📥 Download: {$downloadUrl}\n\n";
            $message .= "Link berlaku 24 jam";
            
            $this->sendMessage($from, $message);
        } catch (\Exception $e) {
            \Log::error('Export failed', ['error' => $e->getMessage()]);
            $this->sendMessage($from, "❌ Gagal membuat laporan.");
        }
        
        return true;
    }

    private function handleAssetCreationFlow(string $text, User $user, string $from, string $step, array $data): bool
    {
        $choice = trim($text);
        
        if ($step === 'asset_creation_type') {
            $types = ['1' => 'tabungan', '2' => 'e-money', '3' => 'investasi', '4' => 'cash'];
            if (!isset($types[$choice])) {
                $this->sendMessage($from, "❌ Pilihan tidak valid. Ketik angka 1-4");
                return true;
            }
            $data['type'] = $types[$choice];
            ConversationState::set($from, 'asset_creation_country', $data);
            $this->sendMessage($from, "Negara?\n\n1. Jepang (JPY)\n2. Indonesia (IDR)");
            return true;
        }
        
        if ($step === 'asset_creation_country') {
            $countries = ['1' => ['country' => 'JP', 'currency' => 'JPY'], '2' => ['country' => 'ID', 'currency' => 'IDR']];
            if (!isset($countries[$choice])) {
                $this->sendMessage($from, "❌ Pilihan tidak valid.");
                return true;
            }
            $data = array_merge($data, $countries[$choice]);
            ConversationState::set($from, 'asset_creation_name', $data);
            $this->sendMessage($from, "Nama aset?");
            return true;
        }
        
        if ($step === 'asset_creation_name') {
            $data['name'] = $text;
            ConversationState::set($from, 'asset_creation_balance', $data);
            $this->sendMessage($from, "Saldo awal?");
            return true;
        }
        
        if ($step === 'asset_creation_balance') {
            $balance = preg_replace('/[^0-9]/', '', $text);
            if (empty($balance)) {
                $this->sendMessage($from, "❌ Saldo tidak valid.");
                return true;
            }
            
            \App\Models\Asset::create([
                'owner_id' => $user->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'country' => $data['country'],
                'currency' => $data['currency'],
                'balance' => $balance,
            ]);
            
            ConversationState::clear($from);
            $currencySymbol = $data['currency'] === 'JPY' ? '¥' : 'Rp';
            $this->sendMessage($from, "✅ Aset *{$data['name']}* berhasil ditambahkan dengan saldo {$currencySymbol}" . number_format($balance, 0, ',', '.'));
            return true;
        }
        
        return false;
    }

    private function handleAssetEditFlow(string $text, User $user, string $from, string $step, array $data): bool
    {
        if ($step === 'asset_edit_choice') {
            $choice = trim($text);
            if ($choice === '3') {
                ConversationState::clear($from);
                $this->sendMessage($from, "✅ Dibatalkan.");
                return true;
            }
            if (!in_array($choice, ['1', '2'])) {
                $this->sendMessage($from, "❌ Pilihan tidak valid.");
                return true;
            }
            if ($choice === '1') {
                ConversationState::set($from, 'asset_edit_name', $data);
                $this->sendMessage($from, "Nama baru?");
            } else {
                ConversationState::set($from, 'asset_edit_balance', $data);
                $this->sendMessage($from, "Saldo baru?");
            }
            return true;
        }
        
        if ($step === 'asset_edit_name') {
            $asset = \App\Models\Asset::find($data['asset_id']);
            if ($asset && $asset->owner_id === $user->id) {
                $asset->update(['name' => $text]);
                ConversationState::clear($from);
                $this->sendMessage($from, "✅ Nama aset diubah menjadi *{$text}*");
            }
            return true;
        }
        
        if ($step === 'asset_edit_balance') {
            $balance = preg_replace('/[^0-9]/', '', $text);
            $asset = \App\Models\Asset::find($data['asset_id']);
            if ($asset && $asset->owner_id === $user->id) {
                $asset->update(['balance' => $balance]);
                ConversationState::clear($from);
                $currencySymbol = $asset->currency === 'JPY' ? '¥' : 'Rp';
                $this->sendMessage($from, "✅ Saldo diubah menjadi {$currencySymbol}" . number_format($balance, 0, ',', '.'));
            }
            return true;
        }
        
        return false;
    }

    private function handleAssetDeleteConfirm(string $text, User $user, string $from, array $data): bool
    {
        if (!in_array(strtolower(trim($text)), ['ya', 'yes', 'y'])) {
            ConversationState::clear($from);
            $this->sendMessage($from, "✅ Dibatalkan.");
            return true;
        }
        
        $asset = \App\Models\Asset::find($data['asset_id']);
        if ($asset && $asset->owner_id === $user->id) {
            $name = $asset->name;
            $asset->delete();
            ConversationState::clear($from);
            $this->sendMessage($from, "✅ Aset *{$name}* berhasil dihapus");
        }
        return true;
    }

    private function handlePrimaryWalletFlow(string $text, User $user, string $from, string $step, array $data): bool
    {
        ConversationState::clear($from);
        $this->sendMessage($from, "Fitur ini sedang dalam pengembangan.");
        return true;
    }
