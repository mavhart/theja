<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PointOfSale;
use Carbon\Carbon;

class AppointmentService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Appointment
    {
        $start = Carbon::parse((string) $data['start_at']);
        $duration = (int) ($data['duration_minutes'] ?? 30);
        $end = isset($data['end_at']) ? Carbon::parse((string) $data['end_at']) : $start->copy()->addMinutes($duration);

        $hasConflicts = $this->checkConflicts(
            PointOfSale::query()->findOrFail($data['pos_id']),
            $start,
            $end,
            isset($data['user_id']) ? (string) $data['user_id'] : null
        );

        if ($hasConflicts) {
            abort(422, 'Conflitto calendario: esiste già un appuntamento sovrapposto.');
        }

        return Appointment::create([
            ...$data,
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => $duration,
        ]);
    }

    public function reschedule(Appointment $apt, Carbon $newStart): Appointment
    {
        $duration = (int) ($apt->duration_minutes ?: 30);
        $newEnd = $newStart->copy()->addMinutes($duration);

        $hasConflicts = Appointment::query()
            ->where('id', '!=', $apt->id)
            ->where('pos_id', $apt->pos_id)
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($newStart, $newEnd, $apt) {
                $q->where(function ($qq) use ($newStart, $newEnd) {
                    $qq->where('start_at', '<', $newEnd)->where('end_at', '>', $newStart);
                });
            })
            ->where(function ($q) use ($apt) {
                $q->where('user_id', $apt->user_id)->orWhereNull('user_id');
            })
            ->exists();

        if ($hasConflicts) {
            abort(422, 'Conflitto calendario nel nuovo orario.');
        }

        $apt->start_at = $newStart;
        $apt->end_at = $newEnd;
        $apt->status = 'scheduled';
        $apt->save();

        return $apt->fresh(['patient', 'user', 'order', 'sale']);
    }

    public function cancel(Appointment $apt): Appointment
    {
        $apt->status = 'cancelled';
        $apt->save();

        return $apt->fresh(['patient', 'user', 'order', 'sale']);
    }

    public function complete(Appointment $apt): Appointment
    {
        $apt->status = 'completed';
        $apt->save();

        return $apt->fresh(['patient', 'user', 'order', 'sale']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarData(PointOfSale $pos, Carbon $from, Carbon $to): array
    {
        return Appointment::query()
            ->where('pos_id', $pos->id)
            ->whereBetween('start_at', [$from, $to])
            ->with(['patient', 'user'])
            ->orderBy('start_at')
            ->get()
            ->map(fn (Appointment $apt) => [
                'id' => $apt->id,
                'title' => $apt->title ?: ($apt->patient ? ($apt->patient->last_name.' '.$apt->patient->first_name) : 'Appuntamento'),
                'type' => $apt->type,
                'status' => $apt->status,
                'start_at' => $apt->start_at?->toIso8601String(),
                'end_at' => $apt->end_at?->toIso8601String(),
                'duration_minutes' => $apt->duration_minutes,
                'patient' => $apt->patient ? ['id' => $apt->patient->id, 'name' => trim($apt->patient->last_name.' '.$apt->patient->first_name)] : null,
                'user' => $apt->user ? ['id' => $apt->user->id, 'name' => $apt->user->name] : null,
            ])->values()->all();
    }

    public function checkConflicts(PointOfSale $pos, Carbon $start, Carbon $end, ?string $userId): bool
    {
        return Appointment::query()
            ->where('pos_id', $pos->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->exists();
    }
}

