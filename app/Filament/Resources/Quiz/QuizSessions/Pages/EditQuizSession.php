<?php

namespace App\Filament\Resources\Quiz\QuizSessions\Pages;

use App\Filament\Resources\Quiz\QuizSessions\QuizSessionResource;
use App\Models\QuizAttempt;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditQuizSession extends EditRecord
{
    protected static string $resource = QuizSessionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $attempts = QuizAttempt::query()
            ->where('quiz_session_id', $this->getRecord()->getKey())
            ->count();

        if ($attempts > 0) {
            Notification::make()
                ->warning()
                ->title('Sesi sudah dikerjakan')
                ->body("Sudah ada {$attempts} peserta pada sesi ini. Mengubah daftar soal tidak mengubah pengerjaan yang sudah berjalan, tapi membuat perbandingan nilai antar peserta jadi tidak setara.")
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
