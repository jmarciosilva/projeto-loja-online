<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MediaInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(MediaService $media): View
    {
        return view('admin.media.index', ['media' => $media->paginate(), 'service' => $media]);
    }

    public function store(StoreMediaRequest $request, MediaService $media): RedirectResponse
    {
        $media->store($request->file('file'));

        return redirect()->route('admin.media.index')->with('status', 'Mídia enviada com sucesso.');
    }

    public function destroy(Media $media, MediaService $service): RedirectResponse
    {
        try {
            $service->delete($media);
        } catch (MediaInUseException $exception) {
            return redirect()->route('admin.media.index')
                ->with('error', 'Não é possível excluir esta mídia: em uso por '.implode(', ', $exception->usages).'.');
        }

        return redirect()->route('admin.media.index')->with('status', 'Mídia excluída com sucesso.');
    }
}
