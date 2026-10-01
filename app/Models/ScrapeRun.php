<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One products:scrape run for one store, shown in the admin panel.
 *
 * @property int $id
 * @property string $store
 * @property string $status
 * @property int|null $exit_code
 * @property string|null $error
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
#[Fillable(['store', 'status', 'exit_code', 'error', 'started_at', 'finished_at'])]
class ScrapeRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function durationInSeconds(): ?int
    {
        return $this->finished_at ? (int) $this->started_at->diffInSeconds($this->finished_at) : null;
    }
}
