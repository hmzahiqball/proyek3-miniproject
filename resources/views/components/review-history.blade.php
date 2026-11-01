@props(['reviews'])
<section class="panel mt-6 min-w-0" aria-labelledby="reviews-title">
    <h2 id="reviews-title" class="text-lg font-semibold">Catatan peninjauan</h2>
    <div class="mt-4 space-y-5">
        @forelse($reviews as $review)
            <article class="min-w-0 border-t border-slate-200 pt-4">
                <h3 class="text-sm font-semibold">{{ $review->decision->label() }}</h3>
                <p class="mt-2 break-words text-xs leading-6 text-slate-500">{{ $review->reviewer->name }} · {{ $review->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                <p class="mt-3 whitespace-pre-line break-words text-sm leading-7">{{ $review->note }}</p>
            </article>
        @empty
            <p class="muted">Belum ada catatan peninjauan.</p>
        @endforelse
    </div>
    @if($reviews->hasPages())
        <div class="mt-5">{{ $reviews->links() }}</div>
    @endif
</section>
