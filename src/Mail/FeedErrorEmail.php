<?php

namespace Daalder\Feeds\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class FeedErrorEmail extends Mailable
{
    use Queueable, SerializesModels;

    private $missingFeeds;
    private $outdatedFeeds;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Collection $missingFeeds, Collection $outdatedFeeds)
    {
        $this->missingFeeds = $missingFeeds;
        $this->outdatedFeeds = $outdatedFeeds;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data = [
            'missingFeeds' => $this->missingFeeds,
            'outdatedFeeds' => $this->outdatedFeeds,
        ];

        return $this->markdown('daalder-feeds::emails.feed-error', $data)
            ->to(
                config('daalder-feeds.validate-feeds.email-addresses'),
                config('daalder-feeds.validate-feeds.receiver-names'),
            )
            ->subject('FEEDS ALERT - Feeds missing or outdated');
    }
}
