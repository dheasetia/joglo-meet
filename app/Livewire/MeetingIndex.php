<?php

namespace App\Livewire;

use App\Enums\MeetingStatus;
use App\Exports\MeetingListExport;
use App\Models\Meeting;
use App\Models\MeetingType;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('Daftar Rapat')]
class MeetingIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'type', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'type', 'from', 'to']);
        $this->resetPage();
    }

    /** Query rapat sesuai filter & hak akses (dipakai daftar dan ekspor Excel). */
    protected function filteredQuery(): Builder
    {
        return Meeting::query()
            ->visibleTo(auth()->user())
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)
                    ->orWhere('location', 'like', $term)
                    ->orWhereHas('agendas', fn ($a) => $a->where('title', 'like', $term)->orWhere('discussion', 'like', $term))
                    ->orWhereHas('decisions', fn ($d) => $d->where('content', 'like', $term)));
            })
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->type, fn ($q) => $q->where('meeting_type_id', $this->type))
            ->when($this->from, fn ($q) => $q->whereDate('date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('date', '<=', $this->to));
    }

    public function exportExcel(): StreamedResponse
    {
        $filters = array_filter([
            $this->search ? "Pencarian “{$this->search}”" : null,
            $this->status ? 'Status: '.MeetingStatus::from($this->status)->label() : null,
            $this->type ? 'Jenis: '.MeetingType::whereKey($this->type)->value('name') : null,
            $this->from ? 'Dari '.Carbon::parse($this->from)->translatedFormat('d M Y') : null,
            $this->to ? 'Sampai '.Carbon::parse($this->to)->translatedFormat('d M Y') : null,
        ]);

        return MeetingListExport::build($this->filteredQuery(), $filters)
            ->download('daftar-rapat-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render()
    {
        $user = auth()->user();

        $meetings = $this->filteredQuery()
            ->with(['type', 'unit', 'chair', 'participants'])
            ->withCount(['decisions', 'actionItems'])
            ->orderByDesc('date')->orderByDesc('start_time')
            ->paginate(12);

        return view('livewire.meeting-index', [
            'meetings' => $meetings,
            'types' => MeetingType::where('is_active', true)->orderBy('name')->get(),
            'statuses' => MeetingStatus::cases(),
            'canCreate' => $user->canCreateMeeting(),
        ]);
    }
}
