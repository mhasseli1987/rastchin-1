<?php
namespace ENZO\Services;

use ENZO\Contracts\Telegram\TelegramProviderInterface;
use ENZO\Contracts\AI\AIProviderInterface;
use ENZO\Contracts\AI\AIRequest;
use ENZO\Contracts\AI\AIResult;
use ENZO\Contracts\Log\LoggerInterface;
use ENZO\Infrastructure\WpDb\PostRepository;

defined('ABSPATH') || exit;

class ContentService
{
    private TelegramProviderInterface $telegram;
    private AIProviderInterface $aiProvider;
    private LoggerInterface $logger;
    private PostRepository $posts;

    public function __construct(
        TelegramProviderInterface $telegram,
        AIProviderInterface $aiProvider,
        LoggerInterface $logger,
        PostRepository $posts
    ) {
        $this->telegram = $telegram;
        $this->aiProvider = $aiProvider;
        $this->logger = $logger;
        $this->posts = $posts;
    }

    /**
     * Generate AI content (POST /ai/generate).
     * Uses the AI provider configured and returns the result as draft.
     */
    public function generate(array $context = []): AIResult
    {
        $request = new AIRequest(
            operations: [$context['operation'] ?? 'generate'],
            messages: [['role' => 'user', 'content' => $context['input'] ?? '']],
            config: $context['config'] ?? [],
            context: $context
        );
        $result = $this->aiProvider->generate($request);
        if ($result->success && $result->content !== '') {
            // Store draft automatically after generation. storeDraft returns a
            // result row (not mutating by reference — context is passed by value
            // everywhere, so the old context['stored_post_id'] write was lost).
            $context['stored_post_id'] = (int) $this->storeDraft($result->content, $context);
        } else {
            $context['stored_post_id'] = 0;
        }
        $result->context = $context;
        return $result;
    }

    /**
     * Approve human-approved content and mark as approved.
     */
    public function approve(int $postId): bool
    {
        return $this->posts->update($postId, ['status' => 'approved']);
    }

    /**
     * Publish now — send Telegram/Bale message with optional media group.
     */
    public function publishNow(int $postId): array
    {
        $post = $this->posts->find($postId);
        if (!$post) {
            return ['success' => false, 'error' => 'پست یافت نشد.'];
        }

        $text = trim((string) $post['body']);
        $channelId = (int) $post['channel_id'];
        // Telegram/Bale channel IDs are negative; only 0 means unset.
        if ($channelId === 0 || $text === '') {
            return ['success' => false, 'error' => 'متن یا کانال تنظیم نشده است.'];
        }

        if ((string) $post['status'] === 'published') {
            return ['success' => false, 'error' => 'این پست قبلاً منتشر شده است.'];
        }

        try {
            $imageIds = $post['image_ids'] ?? [];
            if (is_string($imageIds)) {
                $imageIds = json_decode($imageIds, true) ?: [];
            }
            $sent = null;

            if (!empty($imageIds) && count($imageIds) > 1) {
                // Media Group (album) - multiple images
                $media = [];
                foreach ($imageIds as $index => $imgId) {
                    $url = wp_get_attachment_url((int) $imgId);
                    if ($url) {
                        $media[] = [
                            'type' => 'photo',
                            'media' => $url,
                            'caption' => ($index === 0) ? $text : '',
                            'parse_mode' => 'HTML',
                        ];
                    }
                }
                if (!empty($media)) {
                    $sent = $this->telegram->sendMediaGroup([
                        'chat_id' => $channelId,
                        'media' => $media,
                    ]);
                }
            } elseif (!empty($imageIds)) {
                // Single image
                $url = wp_get_attachment_url((int) $imageIds[0]);
                if ($url) {
                    $sent = $this->telegram->sendPhoto([
                        'chat_id' => $channelId,
                        'photo' => $url,
                        'caption' => $text,
                        'parse_mode' => 'HTML',
                    ]);
                }
            }

            if ($sent === null) {
                // Text only
                $sent = $this->telegram->sendMessage(['chat_id' => $channelId, 'text' => $text, 'parse_mode' => 'HTML']);
            }

            $this->posts->update($postId, [
                'status'       => 'published',
                'published_at' => current_time('mysql', 1),
                'last_error_code' => null,
            ]);
            $this->logger->info('Post published', ['post_id' => $postId, 'scope' => 'publish']);

            $messageId = (int) (($sent[0]['message_id'] ?? $sent['message_id'] ?? 0));
            $username = $sent[0]['chat']['username'] ?? ($sent['chat']['username'] ?? null);
            $link = $username ? 'https://t.me/' . $username . '/' . $messageId : null;
            return ['success' => true, 'message_id' => $messageId, 'link' => $link];
        } catch (\Exception $e) {
            $this->posts->update($postId, ['last_error_code' => 'publish_failed']);
            $this->logger->error('Publish failed', [
                'post_id' => $postId,
                'scope'   => 'publish',
                'error'   => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Store generated draft in post meta + log.
     */
    private function storeDraft(string $content, array $context): int
    {
        $postId = $this->posts->insert([
            'title'      => (string) ($context['title'] ?? ''),
            'body'       => $content,
            'channel_id' => (int) ($context['channel_id'] ?? 0),
            'image_ids'  => $context['image_ids'] ?? null,
            'status'     => 'draft',
        ]);

        $this->logger->info('Draft stored', ['post_id' => $postId, 'scope' => 'content']);
        return $postId;
    }
}