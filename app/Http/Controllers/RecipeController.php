<?php

namespace App\Http\Controllers;

use App\Services\IngredientProductMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    /**
     * Match recipe ingredients to the cheapest store products.
     */
    public function match(Request $request, IngredientProductMatcher $matcher): JsonResponse
    {
        $validated = $request->validate([
            'ingredients' => ['required', 'array', 'max:30'],
            'ingredients.*' => ['string', 'max:100'],
        ]);

        $matches = collect($matcher->matchMany($validated['ingredients']))
            ->map(fn (array $match): array => [
                'ingredient' => $match['ingredient'],
                'product' => $match['product']?->only(['id', 'title', 'store', 'current_price', 'image_url']),
            ]);

        return response()->json(['matches' => $matches]);
    }
}
