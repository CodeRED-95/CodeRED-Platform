<div>
    <h1>Import Run #{{ $importRun->id }}</h1>

    <p>Status: {{ $importRun->status }}</p>
    <p>Progress: {{ $importRun->progress }}%</p>

    @if($importRun->status === 'ready_for_review')
        // TODO: Show preview of changes
        <button>Confirm Import</button>
    @endif
</div>
