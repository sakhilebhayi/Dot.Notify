<?php

namespace App\Livewire\Notify;

use App\Support\HeaderSearch as Index;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The header search box (R-52): the shared dot-ui pill with a live dropdown of the current company's own results. The
 * questions it asks live in App\Support\HeaderSearch (team-scoped, no message bodies); Enter opens the full results page.
 */
class HeaderSearch extends Component
{
    public string $query = '';

    public function mount(): void
    {
        // On the full results page the box shows what was searched.
        if (request()->routeIs('search')) {
            $this->query = Index::clean(is_string(request()->query('q')) ? request()->query('q') : '');
        }
    }

    /** @return array{query: string, ready: bool, total: int, groups: array<int, array<string, mixed>>} */
    #[Computed]
    public function results(): array
    {
        return Index::run(auth()->user(), $this->query, 4);
    }

    public function render()
    {
        return view('livewire.notify.header-search');
    }
}
