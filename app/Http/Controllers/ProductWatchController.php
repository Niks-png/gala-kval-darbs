<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductWatchController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->user()->watchedProducts()->syncWithoutDetaching([$product->id]);

        return back()->with('watch_status', __('Tu sekosi šī produkta cenai. Paziņosim, kad tā kritīsies.'));
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $request->user()->watchedProducts()->detach($product->id);

        return back()->with('watch_status', __('Tu vairs nesekosi šī produkta cenai.'));
    }
}
