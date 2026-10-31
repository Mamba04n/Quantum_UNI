<?php

namespace App\Livewire\Web;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class BlogManager extends Component
{
    use WithFileUploads;

    public $viewMode = 'list'; // list | form
    public $search = '';

    // Model fields
    public $post_id, $titulo = '', $slug = '', $resumen = '', $categoria = '', $maquina = '', $modelo = '', $pieza = '', $numero_parte = '', $contenido = '';
    public $photo, $image_url = '';

    public function render()
    {
        $posts = collect();

        if (Schema::hasTable('tech_blog_posts')) {
            $posts = DB::table('tech_blog_posts')
                ->where(function ($query) {
                    $query->where('titulo', 'like', '%' . $this->search . '%')
                        ->orWhere('maquina', 'like', '%' . $this->search . '%')
                        ->orWhere('numero_parte', 'like', '%' . $this->search . '%');
                })
                ->orderByDesc('created_at')
                ->get();
        }

        return view('livewire.web.blog-manager', [
            'posts' => $posts
        ]);
    }

    public function create()
    {
        abort_unless($this->canManageBlog(), 403);
        abort_unless(Schema::hasTable('tech_blog_posts'), 503, 'La tabla blog_tecnico aun no existe. Ejecuta php artisan migrate --force.');

        $this->resetForm();
        $this->viewMode = 'form';
    }

    public function edit($id)
    {
        abort_unless($this->canManageBlog(), 403);
        abort_unless(Schema::hasTable('tech_blog_posts'), 503, 'La tabla blog_tecnico aun no existe. Ejecuta php artisan migrate --force.');

        $post = DB::table('tech_blog_posts')->where('id', $id)->first();
        if (!$post) return;

        $this->post_id = $post->id;
        $this->titulo = $post->titulo;
        $this->slug = $post->slug;
        $this->resumen = $post->resumen;
        $this->categoria = $post->categoria;
        $this->maquina = $post->maquina;
        $this->modelo = $post->modelo;
        $this->pieza = $post->pieza;
        $this->numero_parte = $post->numero_parte;
        $this->contenido = $post->contenido;
        $this->image_url = str_starts_with((string)$post->imagen, 'http') ? $post->imagen : '';
        $this->photo = null;

        $this->viewMode = 'form';
    }

    public function save()
    {
        abort_unless($this->canManageBlog(), 403);
        abort_unless(Schema::hasTable('tech_blog_posts'), 503, 'La tabla blog_tecnico aun no existe. Ejecuta php artisan migrate --force.');

        if (!$this->slug && $this->titulo) {
            $this->slug = Str::slug($this->titulo);
        }

        $this->validate([
            'titulo' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tech_blog_posts,slug,' . $this->post_id,
            'contenido' => 'required',
            'photo' => 'nullable|image|max:4096',
            'image_url' => ['nullable', 'string', 'max:5000', function ($attribute, $value, $fail) {
                if (! str_starts_with((string) $value, 'data:image') && ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('La imagen debe ser una URL valida o una imagen embebida.');
                }
            }],
        ]);

        DB::transaction(function () {
            $data = [
                'titulo' => $this->titulo,
                'slug' => $this->slug,
                'resumen' => $this->resumen,
                'categoria' => $this->categoria,
                'maquina' => $this->maquina,
                'modelo' => $this->modelo,
                'pieza' => $this->pieza,
                'numero_parte' => $this->numero_parte,
                'contenido' => $this->contenido,
                'activo' => 1,
                'updated_at' => now(),
            ];

            if (!$this->post_id) {
                $data['created_at'] = now();
            }

            if ($this->photo) {
                $filename = 'blog_' . time() . '.' . $this->photo->getClientOriginalExtension();
                $this->photo->storeAs('', $filename, 'blog_uploads');
                $data['imagen'] = $filename;
            } elseif ($this->image_url) {
                if (str_starts_with($this->image_url, 'data:image')) {
                    abort_unless(preg_match('/^data:image\/(png|jpe?g|webp);base64,/i', $this->image_url) === 1, 422);

                    $filename = 'blog_b64_' . time() . '.jpg';
                    $imageData = explode(',', $this->image_url);
                    $decoded = base64_decode(end($imageData), true);
                    abort_unless($decoded !== false, 422);

                    Storage::disk('blog_uploads')->put($filename, $decoded);
                    $data['imagen'] = $filename;
                } else {
                    $data['imagen'] = $this->image_url;
                }
            }

            if ($this->post_id) {
                DB::table('tech_blog_posts')->where('id', $this->post_id)->update($data);
            } else {
                DB::table('tech_blog_posts')->insert($data);
            }
        });

        session()->flash('message', 'Artículo guardado.');
        $this->viewMode = 'list';
    }

    public function resetForm()
    {
        $this->post_id = null;
        $this->titulo = '';
        $this->slug = '';
        $this->resumen = '';
        $this->categoria = '';
        $this->maquina = '';
        $this->modelo = '';
        $this->pieza = '';
        $this->numero_parte = '';
        $this->contenido = '';
        $this->image_url = '';
        $this->photo = null;
    }

    public function showList()
    {
        $this->viewMode = 'list';
    }

    private function canManageBlog(): bool
    {
        $user = auth()->user();

        return $user?->can('full_access')
            || $user?->can('blog.manage')
            || $user?->can('technical_blog.manage');
    }
}
