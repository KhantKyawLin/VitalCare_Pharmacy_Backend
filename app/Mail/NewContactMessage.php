<?php

namespace App\Mail;

use App\Models\ContactUs;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    public $contact;

    /**
     * Create a new message instance.
     */
    public function __construct(ContactUs $contact)
    {
        $this->contact = $contact;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('New Contact Inquiry: ' . $this->contact->subject)
                    ->view('emails.new-contact-message');
    }
}
