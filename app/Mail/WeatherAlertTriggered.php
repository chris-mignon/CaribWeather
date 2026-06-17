<?php

namespace App\Mail;

use App\Models\AlertSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeatherAlertTriggered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AlertSubscription $alert,
        public string $alertMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'CaribWeather Alert: '.$this->alert->type);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weather-alert-triggered',
            with: [
                'alert' => $this->alert,
                'alertMessage' => $this->alertMessage,
            ],
        );
    }
}
