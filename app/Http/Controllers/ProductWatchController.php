<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductWatchController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $request->user()->watchedProducts()->syncWithoutDetaching([$product->id]);

        return $this->respond($request, true, __('Tu sekosi šī produkta cenai. Paziņosim, kad tā kritīsies.'));
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $request->user()->watchedProducts()->detach($product->id);

        return $this->respond($request, false, __('Tu vairs nesekosi šī produkta cenai.'));
    }

    private function respond(Request $request, bool $watching, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['watching' => $watching, 'message' => $message]);
        }

        return back()->with('watch_status', $message);
    }
}
