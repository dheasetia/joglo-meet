<?php

namespace App\Livewire;

use App\Enums\ActionStatus;
use App\Livewire\Concerns\IndonesianValidation;
use App\Livewire\Concerns\UpdatesActionItems;
use App\Models\ActionItem;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tugas Saya')]
class MyTasks extends Component
{
    use IndonesianValidation, UpdatesActionItems;

    public bool $showDone = false;

    public function render()
    {
        $base = ActionItem::query()->where('pic_id', auth()->id())->with(['meeting', 'pic']);

        return view('livewire.my-tasks', [
            'open' => (clone $base)->open()->orderBy('due_date')->get(),
            'done' => $this->showDone
                ? (clone $base)->whereIn('status', [ActionStatus::Selesai->value, ActionStatus::Batal->value])->latest('updated_at')->limit(30)->get()
                : collect(),
            'doneCount' => (clone $base)->where('status', ActionStatus::Selesai->value)->count(),
            'updating' => $this->updatingItem(),
        ]);
    }
}
