<div class="mx-auto flex min-h-screen max-w-md items-center px-4">
    <form wire:submit.prevent="submit" class="w-full rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur">
        <h1 class="text-3xl font-semibold">Iniciar sesión</h1>
        <p class="mt-2 text-sm text-slate-300">Acceso administrativo a CodeRED Platform.</p>

        <div class="mt-6 space-y-4">
            <div>
                <label class="block text-sm">Correo</label>
                <input wire:model="email" type="email" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950/40 px-4 py-3 text-white" />
                @error('email') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm">Contraseña</label>
                <input wire:model="password" type="password" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950/40 px-4 py-3 text-white" />
                @error('password') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input wire:model="remember" type="checkbox" class="rounded border-white/10 bg-slate-950/40">
                Recordarme
            </label>
        </div>
        <button class="mt-6 w-full rounded-xl bg-white px-4 py-3 font-medium text-slate-950">Entrar</button>
    </form>
</div>
