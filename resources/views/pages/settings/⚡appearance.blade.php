<?php

declare(strict_types=1);

use App\Actions\Appearance\ChangePalette;
use App\Enums\Palette;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Transition;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Appearance settings')] class extends Component {
    public string $palette = 'ferrite';

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->palette = $user->resolvedPalette()->value;
    }

    public function updatedPalette(ChangePalette $changePalette): void
    {
        $validated = $this->validate(rules: [
            'palette' => ['required', 'string', Rule::enum(type: Palette::class)],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $changePalette->handle(
            user: $user,
            palette: Palette::from(value: $validated['palette']),
        );
    }

    #[Transition(type: 'backward')]
    public function goToSessions(): void
    {
        $this->redirect(url: route(name: 'sessions.edit'), navigate: true);
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Choose one of Vaulta\'s 15 themes. Saved per account and remembered for sign-in.')">
        <livewire:settings.palette-picker />
    </x-pages::settings.layout>
</section>
