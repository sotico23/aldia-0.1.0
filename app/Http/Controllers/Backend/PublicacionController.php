<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\ComentarioPublicacion;
use App\Models\Publicacion;
use App\Models\Reaction;
use App\Notifications\NuevaReaccion;
use App\Notifications\NuevoComentario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PublicacionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'image' => 'nullable|image|max:5120',
        ]);

        $publicacion = new Publicacion;
        $publicacion->user_id = $request->user()->id;
        $publicacion->content = $validated['content'];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('publicaciones', 'public');
            $publicacion->image_path = $path;
        }

        $publicacion->save();

        return back()->with('success', 'Publicación creada exitosamente');
    }

    public function react(Request $request, Publicacion $publicacion): RedirectResponse
    {
        $type = $request->input('type', 'like'); // 'like' or 'heart'
        $user = Auth::user();

        $reaction = Reaction::where('user_id', $user->id)
            ->where('reactable_type', Publicacion::class)
            ->where('reactable_id', $publicacion->id)
            ->where('type', $type)
            ->first();

        if ($reaction) {
            $reaction->delete();
            $publicacion->decrement($type === 'like' ? 'likes_count' : 'hearts_count');
        } else {
            Reaction::create([
                'user_id' => $user->id,
                'reactable_type' => Publicacion::class,
                'reactable_id' => $publicacion->id,
                'type' => $type,
            ]);
            $publicacion->increment($type === 'like' ? 'likes_count' : 'hearts_count');

            // Notificar al dueño de la publicación
            if ($publicacion->user_id !== $user->id) {
                NotificationHelper::send($publicacion->user, new NuevaReaccion($user, $publicacion, $type));
            }
        }

        return back();
    }

    public function comment(Request $request, Publicacion $publicacion): RedirectResponse
    {
        $request->validate([
            'content' => 'nullable|string|max:1000',
            'image' => 'nullable|image|max:5120',
            'parent_id' => 'nullable|exists:comentario_publicacions,id',
        ]);

        if (! $request->filled('content') && ! $request->hasFile('image')) {
            return back()->withErrors(['content' => 'Debes enviar un mensaje o una imagen.']);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('comentarios', 'public');
        }

        $comentario = $publicacion->comentarios()->create([
            'user_id' => Auth::id(),
            'owner_id' => Auth::user()->getOwnerId(),
            'content' => $request->content ?? '',
            'image_path' => $imagePath,
            'parent_id' => $request->parent_id,
        ]);

        $publicacion->increment('comments_count');

        // Notificar
        $user = Auth::user();
        if ($request->parent_id) {
            $parent = ComentarioPublicacion::find($request->parent_id);
            if ($parent && $parent->user_id !== $user->id) {
                NotificationHelper::send($parent->user, new NuevoComentario($user, $comentario, true));
            }
            // También notificar al dueño del post, si es diferente al que comenta y al padre
            if ($publicacion->user_id !== $user->id && (! $parent || $publicacion->user_id !== $parent->user_id)) {
                NotificationHelper::send($publicacion->user, new NuevoComentario($user, $comentario, false));
            }
        } elseif ($publicacion->user_id !== $user->id) {
            NotificationHelper::send($publicacion->user, new NuevoComentario($user, $comentario, false));
        }

        return back();
    }

    public function reactComment(Request $request, ComentarioPublicacion $comentario): RedirectResponse
    {
        $type = $request->input('type', 'like');
        $user = Auth::user();

        $reaction = Reaction::where('user_id', $user->id)
            ->where('reactable_type', ComentarioPublicacion::class)
            ->where('reactable_id', $comentario->id)
            ->where('type', $type)
            ->first();

        if ($reaction) {
            $reaction->delete();
            $comentario->decrement($type === 'like' ? 'likes_count' : 'hearts_count');
        } else {
            Reaction::create([
                'user_id' => $user->id,
                'reactable_type' => ComentarioPublicacion::class,
                'reactable_id' => $comentario->id,
                'type' => $type,
            ]);
            $comentario->increment($type === 'like' ? 'likes_count' : 'hearts_count');

            // Notificar
            if ($comentario->user_id !== $user->id) {
                NotificationHelper::send($comentario->user, new NuevaReaccion($user, $comentario, $type));
            }
        }

        return back();
    }

    public function share(Publicacion $publicacion): RedirectResponse
    {
        $user = Auth::user();

        Publicacion::create([
            'user_id' => $user->id,
            'content' => '♻️ Compartido de '.$publicacion->user->name.":\n\n".$publicacion->content,
            'image_path' => $publicacion->image_path,
        ]);

        return back();
    }

    public function update(Request $request, Publicacion $publicacion): RedirectResponse
    {
        if ($publicacion->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string',
            'image' => 'nullable|image|max:5120',
        ]);

        $publicacion->content = $validated['content'];

        if ($request->hasFile('image')) {
            // Eliminar imagen anterior si existe
            if ($publicacion->image_path) {
                Storage::disk('public')->delete($publicacion->image_path);
            }

            $path = $request->file('image')->store('publicaciones', 'public');
            $publicacion->image_path = $path;
        }

        $publicacion->save();

        return back()->with('success', 'Publicación actualizada exitosamente');
    }

    public function destroy(Publicacion $publicacion): RedirectResponse
    {
        if ($publicacion->user_id !== Auth::id()) {
            abort(403);
        }

        $publicacion->delete();

        return back()->with('success', 'Publicación eliminada exitosamente');
    }
}
