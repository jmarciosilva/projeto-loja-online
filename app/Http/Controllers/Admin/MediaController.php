<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MediaInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(MediaService $media): View
    {
        return view('admin.media.index', ['media' => $media->paginate(), 'service' => $media]);
    }

    public function store(StoreMediaRequest $request, MediaService $media): RedirectResponse|JsonResponse
    {
        $item = $media->store($request->file('file'));

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $item->id,
                'name' => $item->original_name,
                'mime_type' => $item->mime_type,
                'url' => $media->url($item),
            ], 201);
        }

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
