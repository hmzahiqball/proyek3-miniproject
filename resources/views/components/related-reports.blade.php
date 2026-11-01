@props(['report', 'reports', 'detailRoute'])
        <section class="panel mt-6 min-w-0" aria-labelledby="related-title">
            <h2 id="related-title" class="text-lg font-semibold">Laporan terkait di wilayah yang sama</h2>
            <p class="muted mt-2 break-words">Laporan lain di {{ $report->area->name }}, dari seluruh Pelapor.</p>
            <div class="mt-4 space-y-4">
                @forelse($reports as $related)
                    <article class="border-t border-slate-200 pt-4">
                        <h3 class="break-words text-sm font-semibold"><a href="{{ route($detailRoute, $related) }}" class="text-forest-900 hover:underline">{{ $related->title }}</a></h3>
                        <p class="mt-2 text-xs leading-6 text-slate-500">#{{ $related->id }} · {{ $related->status->label() }} · Pengamatan {{ $related->occurred_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                    </article>
                @empty
                    <p class="muted">Belum ada laporan lain di wilayah ini.</p>
                @endforelse
            </div>
            @if($reports->hasPages())
                <div class="mt-5">{{ $reports->links() }}</div>
            @endif
        </section>
