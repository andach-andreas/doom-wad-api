<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommandRun extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLING = 'cancelling';
    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses that mean a run still occupies the queue. */
    public const ACTIVE_STATUSES = [self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_CANCELLING];

    /**
     * Whitelist of commands that may be run from the web.
     * Key = artisan signature name, value = metadata for the admin page.
     * 'argument' names a single required argument the form must supply.
     */
    public const COMMANDS = [
        'wads:download' => [
            'label' => 'Download WADs',
            'description' => 'Fetch any new zip files from the idgames mirror.',
        ],
        'wads:unzip' => [
            'label' => 'Unzip WADs',
            'description' => 'Extract any zips that have not been extracted yet.',
        ],
        'wads:analyse' => [
            'label' => 'Analyse WADs',
            'description' => 'Re-analyse every extracted WAD and update the database.',
        ],
        'wads:tas-check' => [
            'label' => 'TAS check demos',
            'description' => 'Run the TAS check against every downloaded demo.',
        ],
        'dsda:sync' => [
            'label' => 'Sync from DSDA',
            'description' => 'Pull DSDA demo records for one WAD (by folder name).',
            'argument' => 'wadName',
        ],
    ];

    protected $fillable = [
        'command',
        'arguments',
        'status',
        'exit_code',
        'pid',
        'queue_job_id',
        'output',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * Atomically move from one status to another. Returns false if the run was
     * no longer in $from (e.g. the worker and a Stop click raced each other).
     */
    public function transition(string $from, string $to, array $extra = []): bool
    {
        $updated = static::whereKey($this->getKey())
            ->where('status', $from)
            ->update(['status' => $to, 'updated_at' => now()] + $extra);

        $this->refresh();

        return $updated === 1;
    }

    public function label(): string
    {
        return self::COMMANDS[$this->command]['label'] ?? $this->command;
    }

    public function commandLine(): string
    {
        return trim($this->command . ' ' . implode(' ', $this->arguments ?? []));
    }

    public function duration(): ?string
    {
        if (!$this->started_at) {
            return null;
        }

        return $this->started_at->diffForHumans($this->finished_at ?? now(), true);
    }
}
