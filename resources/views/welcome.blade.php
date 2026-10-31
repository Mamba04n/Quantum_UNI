<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Quantum Soluciones</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-50 text-slate-900 antialiased">
        <main class="flex min-h-screen items-center justify-center px-6 py-12">
            <section class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Quantum Soluciones</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Portal ERP y tienda</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                    Accede al panel administrativo o vuelve al sitio comercial para consultar productos, solicitudes y seguimiento de pedidos.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-black">
                            Ir al panel
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-black">
                            Iniciar sesion
                        </a>
                    @endauth

                    <a href="{{ route('storefront.home') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                        Ver sitio comercial
                    </a>
                </div>
            </section>
        </main>
    </body>
</html>
