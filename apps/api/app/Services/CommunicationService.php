<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\LacSupplySchedule;
use App\Models\Patient;
use App\Models\PointOfSale;
use App\Models\Prescription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CommunicationService
{
    /**
     * @param array<string, mixed> $variables
     */
    public function send(string $type, Patient $patient, string $trigger, array $variables): CommunicationLog
    {
        $template = $this->resolveTemplate($patient->organization_id, $patient->pos_id, $type, $trigger);
        $body = $template ? $this->renderTemplate($template, $variables) : (string) ($variables['body'] ?? '');
        $subject = $template?->subject ?? ($variables['subject'] ?? null);

        $log = CommunicationLog::create([
            'organization_id' => $patient->organization_id,
            'pos_id' => $patient->pos_id,
            'patient_id' => $patient->id,
            'type' => $type,
            'trigger' => $trigger,
            'subject' => $subject,
            'body' => $body,
            'status' => 'pending',
            'provider' => $type === 'email' ? (string) config('mail.default') : (string) env('SMS_PROVIDER', 'log'),
        ]);

        try {
            $ok = false;
            if ($type === 'email') {
                $to = $patient->email ?: null;
                $ok = $to ? $this->sendEmail($to, (string) ($subject ?? 'Comunicazione Theja'), $body) : false;
            } else {
                $to = $patient->mobile ?: $patient->phone;
                $ok = $to ? $this->sendSms($to, $body) : false;
            }

            $log->status = $ok ? 'sent' : 'failed';
            $log->sent_at = $ok ? now() : null;
            if (! $ok) {
                $log->error_message = 'Invio non riuscito o destinatario mancante.';
            }
            $log->save();
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->save();
        }

        return $log->fresh();
    }

    public function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('[CommunicationService] sendEmail failed', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function sendSms(string $to, string $body): bool
    {
        Log::info('[SMS stub] sendSms', [
            'provider' => env('SMS_PROVIDER', 'log'),
            'from' => env('SMS_FROM', 'Theja'),
            'to' => $to,
            'body' => $body,
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function renderTemplate(CommunicationTemplate $template, array $variables): string
    {
        $rendered = $template->body;
        foreach ($variables as $key => $value) {
            $rendered = str_replace('{'.$key.'}', (string) $value, $rendered);
        }

        return $rendered;
    }

    public function scheduleReminders(): void
    {
        $from = now()->addHours(23);
        $to = now()->addHours(25);

        Appointment::query()
            ->with('patient')
            ->whereNull('reminder_sent_at')
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereBetween('start_at', [$from, $to])
            ->chunkById(100, function ($rows) {
                foreach ($rows as $apt) {
                    if (! $apt->patient) {
                        continue;
                    }

                    $this->send('sms', $apt->patient, 'appointment_reminder', [
                        'paziente_nome' => trim(($apt->patient->first_name ?? '').' '.($apt->patient->last_name ?? '')),
                        'data_appuntamento' => $apt->start_at?->format('d/m/Y H:i'),
                        'tipo' => $apt->type,
                    ]);

                    $apt->reminder_sent_at = now();
                    $apt->save();
                }
            });
    }

    public function scheduleLacReminders(): void
    {
        LacSupplySchedule::query()
            ->with('patient')
            ->expiringSoon(7)
            ->whereNull('reminder_sent_at')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    if (! $row->patient) {
                        continue;
                    }

                    $this->send('sms', $row->patient, 'lac_reminder', [
                        'paziente_nome' => trim(($row->patient->first_name ?? '').' '.($row->patient->last_name ?? '')),
                        'data_scadenza' => $row->estimated_end_date?->format('d/m/Y'),
                    ]);

                    $row->reminder_sent_at = now();
                    $row->save();
                }
            });
    }

    public function schedulePrescriptionReminders(): void
    {
        Prescription::query()
            ->with('patient')
            ->whereDate('next_recall_at', '<=', now()->addDays(7)->toDateString())
            ->chunkById(100, function ($rows) {
                foreach ($rows as $rx) {
                    if (! $rx->patient) {
                        continue;
                    }

                    $this->send('sms', $rx->patient, 'prescription_reminder', [
                        'paziente_nome' => trim(($rx->patient->first_name ?? '').' '.($rx->patient->last_name ?? '')),
                        'data_richiamo' => $rx->next_recall_at?->format('d/m/Y'),
                    ]);
                }
            });
    }

    public function scheduleBirthdays(): void
    {
        $month = now()->format('m');
        $day = now()->format('d');

        Patient::query()
            ->whereRaw("to_char(date_of_birth, 'MM') = ?", [$month])
            ->whereRaw("to_char(date_of_birth, 'DD') = ?", [$day])
            ->chunkById(100, function ($rows) {
                foreach ($rows as $patient) {
                    $this->send('sms', $patient, 'birthday', [
                        'paziente_nome' => trim(($patient->first_name ?? '').' '.($patient->last_name ?? '')),
                    ]);
                }
            });
    }

    private function resolveTemplate(string $organizationId, ?string $posId, string $type, string $trigger): ?CommunicationTemplate
    {
        return CommunicationTemplate::query()
            ->where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->where(function ($q) use ($posId) {
                $q->where('pos_id', $posId)->orWhereNull('pos_id');
            })
            ->orderByRaw('CASE WHEN pos_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }
}

