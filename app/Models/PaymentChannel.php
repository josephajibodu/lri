<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\RecordsAdminActivity;
use Database\Factories\PaymentChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One way to pay in one currency, the provider that handles it, and how the checkout shows it.
 * Admins switch channels on and off and pick which ones to feature without a deploy.
 */
#[Fillable([
    'method',
    'currency',
    'provider',
    'label',
    'description',
    'is_enabled',
    'is_featured',
    'sort_order',
])]
class PaymentChannel extends Model
{
    /** @use HasFactory<PaymentChannelFactory> */
    use HasFactory, RecordsAdminActivity;

    public function activityLabel(): string
    {
        return "way to pay \"{$this->display_label}\"";
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return ['method', 'currency', 'provider', 'label', 'description', 'is_enabled', 'is_featured', 'sort_order'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'is_enabled' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Limit the query to switched-on channels for a currency, featured ones first, in the order admins set.
     *
     * @param  Builder<PaymentChannel>  $query
     */
    public function scopeOfferedIn(Builder $query, string $currency): void
    {
        $query->where('is_enabled', true)
            ->where('currency', strtoupper($currency))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The name buyers see: the admin's own label, or the method's name.
     *
     * @return Attribute<string, never>
     */
    protected function displayLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->label ?: $this->method->label());
    }
}
