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
class CatalogManager extends Component
{
    use WithFileUploads;

    public $viewMode = 'list'; // list | form
    public $search = '';

    // Model fields
    public $catalog_id, $nombre = '', $descripcion = '', $archivo = '', $categoria = '', $destacado = 0;
    public $file;

    public function render()
    {
        $catalogos = collect();

        if (Schema::hasTable('catalogs')) {
            $catalogos = DB::table('catalogs')
                ->where(function ($query) {
                    $query->where('nombre', 'like', '%' . $this->search . '%')
                        ->orWhere('categoria', 'like', '%' . $this->search . '%');
                })
                ->orderByDesc('created_at')
                ->get();
        }

        return view('livewire.web.catalog-manager', [
            'catalogos' => $catalogos
        ]);
    }

    public function create()
    {
        abort_unless($this->canManageCatalog(), 403);
        abort_unless(Schema::hasTable('catalogs'), 503, 'La tabla catalogos aun no existe. Ejecuta php artisan migrate --force.');

        $this->resetForm();
        $this->viewMode = 'form';
    }

    public function edit($id)
    {
        abort_unless($this->canManageCatalog(), 403);
        abort_unless(Schema::hasTable('catalogs'), 503, 'La tabla catalogos aun no existe. Ejecuta php artisan migrate --force.');

        $cat = DB::table('catalogs')->where('id', $id)->first();
        if (!$cat) return;

        $this->catalog_id = $cat->id;
        $this->nombre = $cat->nombre;
        $this->descripcion = $cat->descripcion ?? '';
        $this->archivo = $cat->archivo;
        $this->categoria = $cat->categoria;
        $this->destacado = $cat->destacado;
        $this->file = null;

        $this->viewMode = 'form';
    }

    public function save()
    {
        abort_unless($this->canManageCatalog(), 403);
        abort_unless(Schema::hasTable('catalogs'), 503, 'La tabla catalogos aun no existe. Ejecuta php artisan migrate --force.');

        $this->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria' => 'nullable|string|max:100',
            'file' => $this->catalog_id ? 'nullable|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:10240' : 'required|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:10240',
        ]);

        DB::transaction(function () {
            $data = [
                'nombre' => $this->nombre,
                'titulo' => $this->nombre, // Alias para compatibilidad legada
                'descripcion' => $this->descripcion ?: null,
                'categoria' => $this->categoria,
                'destacado' => (int)$this->destacado,
                'activo' => 1,
                'requiere_login' => 0,
                'updated_at' => now(),
            ];

            if (!$this->catalog_id) {
                $data['created_at'] = now();
                $data['fecha_subida'] = now(); // Alias para compatibilidad legada
            }

            if ($this->file) {
                $cleanName = Str::slug($this->nombre);
                $filename = 'cat_' . time() . '_' . $cleanName . '.' . $this->file->getClientOriginalExtension();
                $this->file->storeAs('storefront/catalogs', $filename, 'public');
                $data['archivo'] = $filename;
            }

            if ($this->catalog_id) {
                DB::table('catalogs')->where('id', $this->catalog_id)->update($data);
            } else {
                DB::table('catalogs')->insert($data);
            }
        });

        session()->flash('message', 'Catálogo guardado.');
        $this->viewMode = 'list';
    }

    public function resetForm()
    {
        $this->catalog_id = null;
        $this->nombre = '';
        $this->descripcion = '';
        $this->archivo = '';
        $this->categoria = '';
        $this->destacado = 0;
        $this->file = null;
    }

    public function showList()
    {
        $this->viewMode = 'list';
    }

    private function canManageCatalog(): bool
    {
        $user = auth()->user();

        return $user?->can('full_access') || $user?->can('catalog.manage');
    }
}
