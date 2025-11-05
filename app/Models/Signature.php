<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Signature extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'signature_hash',
        'file_path',
        'ip_address',
        'user_agent',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    // Relations
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    // Helpers
    public function getSignatureUrl(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    public function verifyHash(string $signatureData): bool
    {
        $hash = hash('sha256', $signatureData);
        return hash_equals($this->signature_hash, $hash);
    }
}

