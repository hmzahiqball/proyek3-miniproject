<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Models\Area;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function index(): View
    {
        return view('coordinator.areas.index', [
            'areas' => Area::query()->withCount('reports')->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('coordinator.areas.form', ['area' => new Area]);
    }

    public function store(StoreAreaRequest $request): RedirectResponse
    {
        Area::create($request->validated());

        return to_route('koordinator.areas.index')->with('status', 'Wilayah berhasil ditambahkan.');
    }

    public function edit(Area $area): View
    {
        return view('coordinator.areas.form', compact('area'));
    }

    public function update(UpdateAreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        return to_route('koordinator.areas.index')->with('status', 'Wilayah berhasil diperbarui.');
    }

    public function destroy(Area $area): RedirectResponse
    {
        try {
            DB::transaction(function () use ($area): void {
                $lockedArea = Area::query()->lockForUpdate()->findOrFail($area->id);

                if ($lockedArea->reports()->exists()) {
                    throw ValidationException::withMessages([
                        'area' => 'Wilayah sudah dipakai laporan dan tidak dapat dihapus. Nonaktifkan wilayah jika tidak digunakan lagi.',
                    ]);
                }

                $lockedArea->delete();
            });
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1451) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'area' => 'Wilayah sudah dipakai laporan dan tidak dapat dihapus. Nonaktifkan wilayah jika tidak digunakan lagi.',
            ]);
        }

        return to_route('koordinator.areas.index')->with('status', 'Wilayah berhasil dihapus.');
    }
}
