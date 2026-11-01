@props(['statusLogs'])
<section class="panel mt-6 min-w-0" aria-labelledby="status-history-title">
    <h2 id="status-history-title" class="text-lg font-semibold">Riwayat perubahan status</h2>
    <div class="mt-4 space-y-4">
        @forelse($statusLogs as $log)
            <article class="min-w-0 border-t border-slate-200 pt-4">
                <h3 class="break-words text-sm font-semibold">{{ $log->from_status->label() }} → {{ $log->to_status->label() }}</h3>
                <p class="mt-2 break-words text-xs leading-6 text-slate-500">{{ $log->changedBy?->name ?? 'Sistem' }} · {{ $log->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</p>
            </article>
        @empty
            <p class="muted">Belum ada perubahan status yang tercatat.</p>
        @endforelse
    </div>
    @if($statusLogs->hasPages())
        <div class="mt-5">{{ $statusLogs->links() }}</div>
    @endif
</section>
