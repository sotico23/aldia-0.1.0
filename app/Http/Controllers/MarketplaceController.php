<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\PaymentConfig;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\StoreReaction;
use App\Models\StoreReview;
use App\Scopes\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class MarketplaceController extends Controller
{
    public function index(): InertiaResponse
    {
        $profiles = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('is_active', true)
            ->with('user')
            ->latest()
            ->paginate(12)
            ->through(fn (PublicProfile $profile): array => [
                ...$profile->only(['id', 'title', 'description', 'slug']),
                'user' => [
                    'name' => $profile->user?->name,
                    'ciudad' => $profile->user?->ciudad,
                    'region' => $profile->user?->region,
                    'profile_photo_url' => $profile->user?->profilePhotoUrl(),
                    'cover_photo_url' => $profile->user?->coverPhotoUrl(),
                ],
            ]);

        return Inertia::render('marketplace/index', [
            'profiles' => $profiles,
        ]);
    }

    public function show(string $slug, Request $request): View|Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('marketplace.show', $slug));
        }

        $publicProfile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('user')
            ->with(['categorias' => function ($query) {
                $query->withoutGlobalScope(OwnerScope::class)
                    ->where('activo', true)
                    ->where('mostrar_en_perfil', true)
                    ->orderBy('nombre');
            }])
            ->firstOrFail();

        $userReaction = null;
        $hasPurchased = false;
        $userReview = null;
        if (Auth::check()) {
            $userId = Auth::id();

            $userReaction = StoreReaction::where('user_id', $userId)
                ->where('public_profile_id', $publicProfile->id)
                ->first();

            $hasPurchased = Pedido::where('user_id', $userId)
                ->where('public_profile_id', $publicProfile->id)
                ->exists();

            $userReview = StoreReview::where('user_id', $userId)
                ->where('public_profile_id', $publicProfile->id)
                ->first();
        }

        $reviews = StoreReview::where('public_profile_id', $publicProfile->id)
            ->with('user')
            ->latest()
            ->get();

        $almacenes = Almacen::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $publicProfile->owner_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $paymentConfig = PaymentConfig::resolveForOwner($publicProfile->user_id);

        return view('marketplace.show', [
            'store' => $publicProfile,
            'almacenes' => $almacenes,
            'userReaction' => $userReaction,
            'reviews' => $reviews,
            'hasPurchased' => $hasPurchased,
            'userReview' => $userReview,
            'paymentConfig' => $paymentConfig ? [
                'is_active' => (bool) $paymentConfig->is_active,
                'paypal_active' => (bool) $paymentConfig->paypal_active,
                'mercadopago_active' => (bool) $paymentConfig->mercadopago_active,
            ] : null,
        ]);
    }

    public function categories($slug)
    {
        $publicProfile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $categorias = Categoria::withoutGlobalScope(OwnerScope::class)
            ->where('public_profile_id', $publicProfile->id)
            ->where('activo', true)
            ->where('mostrar_en_perfil', true)
            ->orderBy('nombre')
            ->get();

        return Inertia::render('marketplace/categories', [
            'store' => $publicProfile,
            'categorias' => $categorias,
        ]);
    }

    public function react(Request $request, string $slug): RedirectResponse
    {
        if (! Auth::check()) {
            return back()->with('error', 'Debes iniciar sesión para interactuar');
        }

        $publicProfile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $vote = [];
        if ($request->has('like')) {
            $vote['like'] = $request->boolean('like') ? StoreReaction::LIKE : StoreReaction::NEUTRAL;
        }
        if ($request->has('rating')) {
            $rating = max(0, min(5, $request->integer('rating')));
            $vote['rating'] = $rating ?: null;
        }

        StoreReaction::saveVote($publicProfile->id, Auth::id(), $vote);

        return back();
    }

    public function loadMore($slug, Request $request)
    {
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 24);

        $publicProfile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $productos = Producto::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $publicProfile->owner_id)
            ->where(function ($query) use ($publicProfile) {
                $query->where('public_profile_id', $publicProfile->id)
                    ->orWhereNull('public_profile_id');
            })
            ->with([
                'inventarios' => fn ($q) => $q->withoutGlobalScope(OwnerScope::class),
                'skus' => fn ($q) => $q->withoutGlobalScope(OwnerScope::class),
                'skus.valores' => fn ($q) => $q->withoutGlobalScope(OwnerScope::class),
                'skus.valores.varianteValor' => fn ($q) => $q->withoutGlobalScope(OwnerScope::class),
            ])
            ->orderBy('nombre')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $productos->items(),
            'meta' => [
                'current_page' => $productos->currentPage(),
                'last_page' => $productos->lastPage(),
                'per_page' => $productos->perPage(),
                'total' => $productos->total(),
            ],
        ]);
    }

    public function storeReview(Request $request, $slug)
    {
        if (! Auth::check()) {
            return back()->with('error', 'Debes iniciar sesión para opinar');
        }

        $publicProfile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $userId = Auth::id();

        $hasPurchased = Pedido::where('user_id', $userId)
            ->where('public_profile_id', $publicProfile->id)
            ->exists();

        if (! $hasPurchased) {
            return back()->with('error', 'Solo los clientes que han comprado pueden dejar una opinión');
        }

        $validated = $request->validate([
            'comentario' => 'required|string|max:1000',
        ]);

        StoreReview::updateOrCreate(
            ['user_id' => $userId, 'public_profile_id' => $publicProfile->id],
            ['comentario' => $validated['comentario']],
        );

        return back()->with('success', 'Opinión publicada correctamente');
    }

    public function category($slug, $categoriaId)
    {
        $publicProfile = PublicProfile::withoutGlobalScope(OwnerScope::class)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('user')
            ->firstOrFail();

        $categoria = Categoria::withoutGlobalScope(OwnerScope::class)
            ->where('id', $categoriaId)
            ->where('public_profile_id', $publicProfile->id)
            ->where('activo', true)
            ->firstOrFail();

        $productos = Producto::withoutGlobalScope(OwnerScope::class)
            ->where('categoria_id', $categoria->id)
            ->where('public_profile_id', $publicProfile->id)
            ->where('activo', true)
            ->where('mostrar_en_perfil', true)
            ->orderBy('nombre')
            ->paginate(12);

        $randomProductos = Producto::withoutGlobalScope(OwnerScope::class)
            ->where('public_profile_id', $publicProfile->id)
            ->where('categoria_id', '!=', $categoria->id)
            ->where('activo', true)
            ->where('mostrar_en_perfil', true)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        $paymentConfig = PaymentConfig::resolveForOwner($publicProfile->user_id);

        return Inertia::render('marketplace/category', [
            'store' => $publicProfile,
            'categoria' => $categoria,
            'productos' => $productos,
            'randomProductos' => $randomProductos,
            'paymentConfig' => $paymentConfig ? [
                'is_active' => (bool) $paymentConfig->is_active,
                'paypal_active' => (bool) $paymentConfig->paypal_active,
                'mercadopago_active' => (bool) $paymentConfig->mercadopago_active,
            ] : null,
        ]);
    }
}
