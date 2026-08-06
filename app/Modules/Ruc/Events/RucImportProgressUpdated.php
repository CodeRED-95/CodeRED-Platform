<?php

namespace App\Modules\Ruc\Events;

use App\Modules\Ruc\Models\RucImport;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RucImportProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels, InteractsWithBroadcasting;

    public function __construct(public RucImport $import)
    {
        $this->broadcastAs('import.progress');
    }

    public function broadcastOn(): Channel
    {
        return new Channel("ruc-import-progress.{$this->import->id}");
    }

    public function broadcastWith(): array
    {
        return [
            'import_id' => $this->import->id,
            'status' => $this->import->status,
            'progress_percentage' => $this->import->getProgressPercentage(),
            'lines_processed' => $this->import->processed_lines,
            'total_lines' => $this->import->total_lines,
            'records_inserted' => $this->import->inserted_records,
            'errors' => $this->import->invalid_rows,
            'duplicates' => $this->import->duplicate_records,
            'speed' => $this->import->lines_per_second,
            'eta_seconds' => $this->import->estimated_time_left,
            'memory_mb' => $this->import->memory_peak_mb,
            'status_message' => $this->import->status_message,
            'last_heartbeat' => $this->import->last_heartbeat_at,
        ];
    }
}
