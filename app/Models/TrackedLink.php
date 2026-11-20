<?php

namespace App\Models;

use App\Enums\TrackedLinkDestination;
use App\Enums\TrafficChannel;
use App\Models\Concerns\RecordsAdminActivity;
use Database\Factories\TrackedLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short link for one place the tickets are shared, like an Instagram bio or a WhatsApp group. A tap is counted and
 * sent on with the place's tags, so the visit and any order are credited to it. Its address never changes once made,
 * because it may already be printed or posted.
 */
#[Fillable([
    'name',
    'slug',
    'source',
    'medium',
    'campaign',
    'destination',
    'created_by',
])]
class TrackedLink extends Model
{
    /** @use HasFactory<TrackedLinkFactory> */
    use HasFactory, RecordsAdminActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => TrafficChannel::class,
            'destination' => TrackedLinkDestination::class,
            'clicks' => 'integer',
            'last_clicked_at' => 'datetime',
        ];
    }

    public function activityLabel(): string
    {
        return "tracked link {$this->slug}";
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return ['name', 'slug', 'source', 'medium', 'campaign', 'destination'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The short address to share.
     */
    public function shortUrl(): string
    {
        return route('tracked-links.show', ['trackedLink' => $this->slug]);
    }

    /**
     * Where a tap lands: the chosen page, tagged with where the link was shared and which link it was.
     */
    public function targetUrl(): string
    {
        return $this->destination->url(array_filter([
            'utm_source' => $this->source->value,
            'utm_medium' => $this->medium,
            'utm_campaign' => $this->campaign,
            'utm_content' => $this->slug,
        ]));
    }

    /**
     * Count a tap. It skips the model's events and timestamps: a tap isn't an edit, so it stays out of the admin log.
     */
    public function recordClick(): void
    {
        static::query()->whereKey($this->getKey())->toBase()->increment('clicks', 1, ['last_clicked_at' => now()]);
    }
}
