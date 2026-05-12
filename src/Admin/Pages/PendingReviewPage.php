<?php

namespace SeoCopilot\Admin\Pages;

use SeoCopilot\Plugin;
use SeoCopilot\Runs\SegmentRepository;

class PendingReviewPage
{
    public static function render(): void
    {
        $c = Plugin::instance()->container();
        $repo = $c->get(SegmentRepository::class);
        $pending_count   = $repo->pending_count();
        $pending_batches = $repo->pending_batches();

        seocp_partial('layout/page', [
            'title'   => __('Pending Review', 'seo-copilot'),
            'content' => function () use ($pending_count, $pending_batches) {
                seocp_partial('pages/pending-review', [
                    'pending_count'   => $pending_count,
                    'pending_batches' => $pending_batches,
                ]);
            },
        ]);
    }
}
