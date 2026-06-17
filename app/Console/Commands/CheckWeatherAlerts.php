<?php

namespace App\Console\Commands;

use App\Mail\WeatherAlertTriggered;
use App\Models\AlertNotification;
use App\Models\AlertSubscription;
use App\Models\PushSubscription;
use App\Services\WeatherDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class CheckWeatherAlerts extends Command
{
    protected $signature = 'caribweather:check-alerts {--dry-run : Evaluate alerts without creating notifications}';

    protected $description = 'Evaluate active CaribWeather alert subscriptions against current weather data.';

    public function handle(WeatherDataService $weather): int
    {
        $checked = 0;
        $triggered = 0;

        AlertSubscription::query()
            ->with('user')
            ->where('enabled', true)
            ->chunkById(50, function ($alerts) use ($weather, &$checked, &$triggered) {
                foreach ($alerts as $alert) {
                    $checked++;

                    if ($this->shouldSkipForCooldown($alert) || $this->shouldSkipForQuietHours($alert)) {
                        continue;
                    }

                    $location = $alert->latitude && $alert->longitude
                        ? sprintf('%.6F, %.6F', $alert->latitude, $alert->longitude)
                        : $alert->location;

                    $conditions = $weather->current($location);
                    $result = $this->evaluate($alert, $conditions);

                    if (! $result['triggered']) {
                        continue;
                    }

                    $triggered++;
                    if (! $this->option('dry-run')) {
                        $this->notify($alert, $result);
                    }
                }
            });

        $this->info("Checked {$checked} alert subscriptions; triggered {$triggered}.");

        return self::SUCCESS;
    }

    private function evaluate(AlertSubscription $alert, array $weather): array
    {
        $type = Str::lower($alert->type);
        $threshold = $this->thresholdValue($alert);

        [$value, $unit, $defaultThreshold] = match (true) {
            Str::contains($type, ['heavy rain', 'flood', 'lightning']) => [(float) data_get($weather, 'current.rainChance', 0), '% rain chance', 70],
            Str::contains($type, 'wind') => [(float) data_get($weather, 'current.windKph', 0), 'km/h wind', 45],
            Str::contains($type, 'heat') => [(float) data_get($weather, 'current.tempC', 0), 'C', 32],
            Str::contains($type, 'uv') => [(float) data_get($weather, 'current.uvIndex', 0), 'UV index', 8],
            Str::contains($type, ['air quality', 'aqi']) => [(float) data_get($weather, 'current.aqi', 0), 'AQI', 100],
            default => [0.0, 'value', 999999],
        };

        $threshold ??= $defaultThreshold;
        $summary = Str::lower((string) data_get($weather, 'current.summary', ''));
        $stormTriggered = Str::contains($type, ['hurricane', 'tropical storm']) && Str::contains($summary, ['hurricane', 'storm']);
        $triggered = $stormTriggered || $value >= $threshold;

        return [
            'triggered' => $triggered,
            'value' => $value,
            'unit' => $unit,
            'threshold' => $threshold,
            'message' => sprintf(
                '%s alert for %s: current %s is %s, threshold is %s.',
                $alert->type,
                $alert->location,
                $unit,
                rtrim(rtrim(number_format($value, 1), '0'), '.'),
                rtrim(rtrim(number_format($threshold, 1), '0'), '.'),
            ),
        ];
    }

    private function notify(AlertSubscription $alert, array $result): void
    {
        $channels = $alert->channels ?: ['in_app'];

        AlertNotification::create([
            'alert_subscription_id' => $alert->id,
            'user_id' => $alert->user_id,
            'client_id' => $alert->client_id,
            'type' => $alert->type,
            'location' => $alert->location,
            'condition_value' => $result['value'].' '.$result['unit'],
            'threshold' => (string) $result['threshold'],
            'message' => $result['message'],
            'channels' => $channels,
            'delivered_at' => now(),
        ]);

        $alert->forceFill(['last_triggered_at' => now()])->save();

        if (in_array('email', $channels, true) && $alert->user?->email) {
            Mail::to($alert->user->email)->send(new WeatherAlertTriggered($alert, $result['message']));
        }

        if (in_array('push', $channels, true)) {
            $this->sendPushNotifications($alert, $result['message']);
        }
    }

    private function sendPushNotifications(AlertSubscription $alert, string $message): void
    {
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (! $publicKey || ! $privateKey) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->when($alert->user_id, fn ($query) => $query->where('user_id', $alert->user_id))
            ->when(! $alert->user_id, fn ($query) => $query->where('client_id', $alert->client_id))
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                json_encode([
                    'title' => 'CaribWeather Alert: '.$alert->type,
                    'body' => $message,
                    'url' => '/#alerts',
                ]),
            );
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }

    private function thresholdValue(AlertSubscription $alert): ?float
    {
        if (! $alert->threshold) {
            return null;
        }

        preg_match('/-?\d+(?:\.\d+)?/', $alert->threshold, $matches);

        return isset($matches[0]) ? (float) $matches[0] : null;
    }

    private function shouldSkipForCooldown(AlertSubscription $alert): bool
    {
        return $alert->last_triggered_at && Carbon::parse($alert->last_triggered_at)->greaterThan(now()->subHour());
    }

    private function shouldSkipForQuietHours(AlertSubscription $alert): bool
    {
        if (! $alert->quiet_hours || Str::contains(Str::lower($alert->type), ['hurricane', 'tropical storm'])) {
            return false;
        }

        $parts = preg_split('/\s*-\s*/', $alert->quiet_hours);
        if (count($parts) !== 2) {
            return false;
        }

        try {
            $now = now();
            $start = Carbon::parse($parts[0]);
            $end = Carbon::parse($parts[1]);
            $current = Carbon::parse($now->format('g:i A'));

            return $start->lessThanOrEqualTo($end)
                ? $current->betweenIncluded($start, $end)
                : $current->greaterThanOrEqualTo($start) || $current->lessThanOrEqualTo($end);
        } catch (\Throwable) {
            return false;
        }
    }
}
