<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Privacy Policy — Vaulta')] class extends Component {
    #[Computed]
    public function content(): HtmlString
    {
        $path = base_path(path: 'md/privacy.md');
        $raw = File::exists(path: $path)
            ? File::get(path: $path)
            : '# Privacy Policy\n\nYour data belongs to you.';

        return new HtmlString(html: Str::markdown(string: $raw));
    }
};
?>

<div class="mx-auto flex min-h-svh max-w-4xl flex-col justify-between px-4 py-6 sm:px-10 sm:py-8">
    <x-guest-header class="border-b border-zinc-200/80 pb-6 dark:border-zinc-800" />

    <main class="my-10">
        <article class="vaulta-prose rounded-2xl border border-zinc-200 bg-zinc-50/75 p-6 shadow-xs sm:p-10 dark:border-zinc-700 dark:bg-zinc-900/75">
            {{ $this->content }}
        </article>
    </main>

    <x-guest-footer />
</div>
