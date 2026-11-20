<?php

namespace App\Livewire;

use App\Enums\ActionStatus;
use App\Livewire\Concerns\IndonesianValidation;
use App\Livewire\Concerns\UpdatesActionItems;
use App\Models\ActionItem;
use App\Models\MeetingType;
use App\Models\Unit;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Papan RTL')]
class ActionBoard extends Component
{
    use IndonesianValidation, UpdatesActionItems, WithPagination;

    #[Url(except: 'tabel')]
    public string $view = 'tabel';

    /** aktif | terlambat | selesai | semua */
    #[Url(except: 'aktif')]
    public string $filter = 'aktif';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $pic = '';

    #[Url(except: '')]
    public string $unit = '';

    #[Url(except: '')]
    public string $type = '';

    public function updated($property): void
    {
        if (in_array($property, ['filter', 'search', 'pic', 'unit', 'type'], true)) {
            $this->resetPage();
        }
    }

    protected function baseQuery()
    {
        return ActionItem::query()
            ->visibleTo(auth()->user())
            ->with(['pic', 'meeting'])
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->pic, fn ($q) => $q->where('pic_id', $this->pic))
            ->when($this->unit, fn ($q) => $q->whereHas('meeting', fn ($m) => $m->where('unit_id', $this->unit)))
            ->when($this->type, fn ($q) => $q->whereHas('meeting', fn ($m) => $m->where('meeting_type_id', $this->type)));
    }

    public function render()
    {
        $query = $this->baseQuery();

        if ($this->view === 'kanban') {
            $all = (clone $query)
                ->where(fn ($q) => $q->open()->orWhere('completed_at', '>=', now()->subDays(30)))
                ->orderBy('due_date')->limit(400)->get();
            $columns = collect([ActionStatus::Belum, ActionStatus::Proses, ActionStatus::Tertunda, ActionStatus::Selesai])
                ->mapWithKeys(fn ($s) => [$s->value => ['status' => $s, 'items' => $all->where('status', $s)]]);
        } else {
            $items = $query
                ->when($this->filter === 'aktif', fn ($q) => $q->open())
                ->when($this->filter === 'terlambat', fn ($q) => $q->overdue())
                ->when($this->filter === 'selesai', fn ($q) => $q->where('status', ActionStatus::Selesai->value))
                ->orderByRaw("case when status in ('belum','proses','tertunda') then 0 else 1 end")
                ->orderBy('due_date')
                ->paginate(20);
        }

        $counts = ActionItem::query()->visibleTo(auth()->user());

        return view('livewire.action-board', [
            'items' => $items ?? null,
            'columns' => $columns ?? null,
            'counts' => [
                'aktif' => (clone $counts)->open()->count(),
                'terlambat' => (clone $counts)->overdue()->count(),
                'selesai' => (clone $counts)->where('status', ActionStatus::Selesai->value)->count(),
            ],
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(),
            'types' => MeetingType::orderBy('name')->get(),
            'updating' => $this->updatingItem(),
            'me' => auth()->user(),
        ]);
    }
}
