<?php
/**
 * Integration Test: REST API
 * Tests authentication, permissions, validation, and endpoint behavior.
 */
require_once __DIR__ . '/bootstrap.php';

class TestRestApi extends IntegrationTestCase
{
    protected int $adminUserId = 0; // overridden in setUp()

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->adminUserId = $this->factory->user->create([
            'role' => 'administrator',
        ]);

        // Queue run / publish flows need a configured bot token.
        (new \ENZO\Security\SecretStore())->set('telegram_bot_token', '123456:TEST-TOKEN');
    }

    protected function tearDown(): void
    {
        (new \ENZO\Security\SecretStore())->delete('telegram_bot_token');
        wp_set_current_user(0);
        parent::tearDown();
    }

    // --------------------------------------------------------------- Auth & Permissions

    public function test_rest_endpoints_require_admin_capability(): void
    {
        // Test as non-admin user
        $subscriberId = $this->factory->user->create(['role' => 'subscriber']);

        $endpoints = [
            ['POST', '/enzo/v1/telegram/connect', ['token' => '123:ABC']],
            ['GET', '/enzo/v1/channels', []],
            ['POST', '/enzo/v1/ai/providers', ['name' => 'test', 'base_url' => 'https://api.test']],
            ['POST', '/enzo/v1/ai/generate', ['prompt' => 'test']],
            ['POST', '/enzo/v1/posts', ['title' => 'Test', 'body' => 'Test']],
            ['GET', '/enzo/v1/queue', []],
            ['GET', '/enzo/v1/logs', []],
            ['GET', '/enzo/v1/dashboard', []],
            ['GET', '/enzo/v1/settings', []],
            ['POST', '/enzo/v1/license/activate', ['key' => 'test']],
        ];

        foreach ($endpoints as [$method, $endpoint, $body]) {
            $response = $this->restRequest($method, $endpoint, $body, $subscriberId);
            // Permission failure shape: ['code' => 'rest_forbidden', 'data' => ['status' => 403]].
            $this->assertArrayNotHasKey('success', $response, "Endpoint $method $endpoint should fail for subscriber");
            $this->assertEquals(
                403,
                $response['data']['status'] ?? 0,
                "Endpoint $method $endpoint should return 403"
            );
        }
    }

    public function test_rest_endpoints_work_for_admin(): void
    {
        // Test that admin can access endpoints (they may return errors for invalid data, but not 403)
        $endpoints = [
            ['GET', '/enzo/v1/channels'],
            ['GET', '/enzo/v1/dashboard'],
            ['GET', '/enzo/v1/settings'],
            ['GET', '/enzo/v1/logs'],
            ['GET', '/enzo/v1/queue'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->restRequest($method, $endpoint, [], $this->adminUserId);
            $this->assertNotEquals(403, $response['code'] ?? 200, "Admin should not get 403 on $method $endpoint");
        }
    }

    // --------------------------------------------------------------- Telegram Endpoints

    public function test_telegram_connect_validates_token_format(): void
    {
        // Invalid format
        $response = $this->restRequest('POST', '/enzo/v1/telegram/connect', ['token' => 'invalid']);
        $this->assertEquals(false, $response['success']);
        $this->assertStringContainsString('نامعتبر', $response['message'] ?? '');

        // Valid format but invalid token (401 from Telegram) — getMe is a GET call.
        $this->mockHttp->setResponse('GET', '*bot*/getMe', [
            'code' => 401,
            'body' => json_encode(['ok' => false, 'description' => 'Unauthorized']),
        ]);

        $response = $this->restRequest('POST', '/enzo/v1/telegram/connect', ['token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw']);
        $this->assertEquals(false, $response['success']);
    }

    public function test_telegram_connect_stores_token_encrypted(): void
    {
        $this->mockTelegramResponse('getMe', [
            'ok' => true,
            'result' => [
                'id' => 123456789,
                'is_bot' => true,
                'first_name' => 'Test Bot',
                'username' => 'testbot',
            ],
        ]);

        $response = $this->restRequest('POST', '/enzo/v1/telegram/connect', ['token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw']);
        $this->assertEquals(true, $response['success']);

        // Verify token was encrypted and stored
        $store = $this->container->make(\ENZO\Contracts\SecretStoreInterface::class);
        $this->assertTrue($store->has('telegram_bot_token'));

        // Verify it's encrypted (not plaintext)
        $encrypted = get_option('enzo_secret_telegram_bot_token');
        $this->assertNotEquals('123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw', $encrypted);
    }

    public function test_telegram_disconnect_removes_token(): void
    {
        // First connect
        $this->mockTelegramResponse('getMe', ['ok' => true, 'result' => ['id' => 123, 'username' => 'test']]);
        $this->restRequest('POST', '/enzo/v1/telegram/connect', ['token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw']);

        // Then disconnect
        $response = $this->restRequest('POST', '/enzo/v1/telegram/disconnect', [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);

        // Verify token removed
        $store = $this->container->make(\ENZO\Contracts\SecretStoreInterface::class);
        $this->assertFalse($store->has('telegram_bot_token'));
    }

    public function test_telegram_test_requires_connection(): void
    {
        // Remove the token stored in setUp() — endpoint must refuse without it.
        (new \ENZO\Security\SecretStore())->delete('telegram_bot_token');

        $response = $this->restRequest('POST', '/enzo/v1/telegram/test', [], $this->adminUserId);
        $this->assertEquals(false, $response['success']);
        $this->assertEquals('not_connected', $response['code'] ?? '');
    }

    public function test_list_channels_returns_channels(): void
    {
        $this->createTestChannel(['chat_id' => '-100111', 'title' => 'Channel 1']);
        $this->createTestChannel(['chat_id' => '-100222', 'title' => 'Channel 2']);

        $response = $this->restRequest('GET', '/enzo/v1/channels', [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);
        $this->assertCount(2, $response['channels'] ?? []);
    }

    // --------------------------------------------------------------- AI Provider Endpoints

    public function test_ai_add_provider_validates_base_url(): void
    {
        $response = $this->restRequest('POST', '/enzo/v1/ai/providers', [
            'name'   => 'Test',
            'config' => ['baseUrl' => 'not-a-url'],
        ], $this->adminUserId);

        $this->assertEquals(false, $response['success']);
        // Real message: "base_url باید با http یا https باشد." — assert on the key name.
        $this->assertStringContainsString('base_url', $response['message'] ?? '');
    }

    public function test_ai_add_provider_stores_encrypted_key(): void
    {
        $response = $this->restRequest('POST', '/enzo/v1/ai/providers', [
            'name'   => 'OpenAI',
            'secret' => 'sk-test123',
            'config' => ['baseUrl' => 'https://api.openai.com/v1/chat/completions'],
        ], $this->adminUserId);

        $this->assertEquals(true, $response['success']);
        $this->assertArrayHasKey('id', $response);

        // Verify API key was stored via SecretStore (never plaintext in DB).
        $store = $this->container->make(\ENZO\Contracts\SecretStoreInterface::class);
        $providers = $this->getAiProvidersFromDb();
        $this->assertCount(1, $providers);
        $ref = (string) $providers[0]['secret_ref'];
        $this->assertNotEquals('sk-test123', $ref);
        $this->assertEquals('sk-test123', $store->get($ref));
    }

    public function test_ai_list_providers(): void
    {
        $this->createAiProvider(['name' => 'Provider 1']);
        $this->createAiProvider(['name' => 'Provider 2']);

        $response = $this->restRequest('GET', '/enzo/v1/ai/providers', [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);
        $this->assertCount(2, $response['providers'] ?? []);
    }

    public function test_ai_test_provider(): void
    {
        $id = $this->createAiProvider(['name' => 'Test']);

        $this->mockHttp->setResponse('GET', '*models', [
            'code' => 200,
            'body' => json_encode(['data' => [['id' => 'test-model']]]),
        ]);

        $response = $this->restRequest('POST', "/enzo/v1/ai/providers/$id/test", [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);
    }

    public function test_ai_generate_validates_input(): void
    {
        // Missing provider
        $response = $this->restRequest('POST', '/enzo/v1/ai/generate', [
            'operation' => 'generate',
            'prompt' => 'Test',
        ], $this->adminUserId);
        $this->assertEquals(false, $response['success']);

        // Invalid operation
        $response = $this->restRequest('POST', '/enzo/v1/ai/generate', [
            'provider_id' => 1,
            'operation' => 'invalid_op',
            'prompt' => 'Test',
        ], $this->adminUserId);
        $this->assertEquals(false, $response['success']);
    }

    // --------------------------------------------------------------- Posts Endpoints

    public function test_create_post_requires_fields(): void
    {
        $response = $this->restRequest('POST', '/enzo/v1/posts', [], $this->adminUserId);
        $this->assertEquals(false, $response['success']);

        $response = $this->restRequest('POST', '/enzo/v1/posts', ['title' => 'Only Title'], $this->adminUserId);
        $this->assertEquals(false, $response['success']);
    }

    public function test_create_post_returns_id(): void
    {
        $response = $this->restRequest('POST', '/enzo/v1/posts', [
            'title' => 'Test Post',
            'body' => 'Test content',
            'channel_id' => 1,
        ], $this->adminUserId);

        $this->assertEquals(true, $response['success']);
        $this->assertArrayHasKey('id', $response);
        $this->assertIsInt($response['id']);
    }

    public function test_approve_post_changes_status(): void
    {
        $postId = $this->createTestPost(['status' => 'draft']);

        $response = $this->restRequest('POST', "/enzo/v1/posts/$postId/approve", [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);

        $repo = $this->container->make(\ENZO\Infrastructure\WpDb\PostRepository::class);
        $post = $repo->find($postId);
        $this->assertEquals('approved', $post['status']);
    }

    public function test_schedule_post_sets_scheduled_at(): void
    {
        $postId = $this->createTestPost(['status' => 'approved']);
        $future = gmdate('Y-m-d H:i:s', time() + 3600);

        $response = $this->restRequest('POST', "/enzo/v1/posts/$postId/schedule", [
            'scheduled_at' => $future,
        ], $this->adminUserId);

        $this->assertEquals(true, $response['success']);

        $repo = $this->container->make(\ENZO\Infrastructure\WpDb\PostRepository::class);
        $post = $repo->find($postId);
        $this->assertEquals('scheduled', $post['status']);
        $this->assertEquals($future, $post['scheduled_at']);
    }

    // --------------------------------------------------------------- Queue Endpoints

    public function test_list_queue_returns_items(): void
    {
        $this->createQueueItem(['job_type' => 'publish_post', 'status' => 'pending']);
        $this->createQueueItem(['job_type' => 'publish_post', 'status' => 'running']);

        $response = $this->restRequest('GET', '/enzo/v1/queue', [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);
        // Real handler returns the list under 'jobs'.
        $this->assertCount(2, $response['jobs'] ?? []);
    }

    public function test_run_queue_item(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        // Mock Telegram sendMessage
        $this->mockTelegramResponse('sendMessage', ['message_id' => 456]);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'status' => 'pending',
            'payload' => json_encode(['post_id' => $postId]),
        ]);

        $response = $this->restRequest('POST', "/enzo/v1/queue/$queueId/run", [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);

        // Verify post marked as published
        $repo = $this->container->make(\ENZO\Infrastructure\WpDb\PostRepository::class);
        $post = $repo->find($postId);
        $this->assertEquals('published', $post['status']);
    }

    public function test_cancel_queue_item(): void
    {
        $queueId = $this->createQueueItem(['job_type' => 'publish_post', 'status' => 'pending']);

        $response = $this->restRequest('POST', "/enzo/v1/queue/$queueId/cancel", [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);

        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('cancelled', $queue['status']);
    }

    public function test_retry_failed_queue_item(): void
    {
        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 5,
        ]);

        $response = $this->restRequest('POST', "/enzo/v1/queue/$queueId/retry", [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);

        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('pending', $queue['status']);
        $this->assertEquals(1, $queue['attempts']); // reset to 1
    }

    // --------------------------------------------------------------- Logs Endpoints

    public function test_list_logs(): void
    {
        $this->createLogEntry('info', 'Test message 1');
        $this->createLogEntry('error', 'Test message 2');

        $response = $this->restRequest('GET', '/enzo/v1/logs', [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);
        $this->assertCount(2, $response['logs'] ?? []);
    }

    public function test_clear_logs(): void
    {
        $this->createLogEntry('info', 'Test');
        $this->createLogEntry('error', 'Test 2');

        $response = $this->restRequest('DELETE', '/enzo/v1/logs', [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);

        $response = $this->restRequest('GET', '/enzo/v1/logs', [], $this->adminUserId);
        $this->assertCount(0, $response['logs'] ?? []);
    }

    // --------------------------------------------------------------- Dashboard & Settings

    public function test_dashboard_returns_counts(): void
    {
        $this->createTestChannel();
        $this->createTestPost(['status' => 'published']);
        $this->createQueueItem(['status' => 'pending']);

        $response = $this->restRequest('GET', '/enzo/v1/dashboard', [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);
        $this->assertArrayHasKey('counts', $response);
        $this->assertArrayHasKey('channels', $response['counts']);
        $this->assertArrayHasKey('queue', $response['counts']);
    }

    public function test_settings_get_returns_all(): void
    {
        $response = $this->restRequest('GET', '/enzo/v1/settings', [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);
        // Real handler returns values under 'values'.
        $this->assertArrayHasKey('values', $response);
        $this->assertArrayHasKey('enzo_default_tone', $response['values']);
    }

    public function test_settings_post_updates(): void
    {
        $response = $this->restRequest('POST', '/enzo/v1/settings', [
            'enzo_log_retention_days' => 10,
        ], $this->adminUserId);

        $this->assertEquals(true, $response['success']);

        $service = $this->container->make(\ENZO\Settings\SettingsService::class);
        $this->assertEquals(10, $service->get('enzo_log_retention_days'));
    }

    // --------------------------------------------------------------- License Endpoints

    public function test_license_activate_validates_key(): void
    {
        $response = $this->restRequest('POST', '/enzo/v1/license/activate', ['key' => ''], $this->adminUserId);
        $this->assertEquals(false, $response['success']);
    }

    public function test_license_deactivate(): void
    {
        // First set a license
        update_option('enzo_license', [
            'key' => 'test',
            'activated' => true,
            'expires' => date('Y-m-d H:i:s', time() + 86400),
        ]);

        $response = $this->restRequest('POST', '/enzo/v1/license/deactivate', [], $this->adminUserId);
        $this->assertEquals(true, $response['success']);

        $this->assertFalse(get_option('enzo_license', false));
    }

    // --------------------------------------------------------------- Helpers

    /** Matches the real enzo_providers schema (type, driver, name, is_default, config_json, secret_ref, created_at). */
    private function createAiProvider(array $overrides = []): int
    {
        $defaults = [
            'type'        => 'ai',
            'driver'      => 'openai_compatible',
            'name'        => 'Test Provider',
            'is_default'  => 0,
            'config_json' => wp_json_encode(['baseUrl' => 'https://api.test/v1', 'model' => 'gpt-4o-mini']),
            'secret_ref'  => null,
            'created_at'  => current_time('mysql', 1),
        ];
        $data = array_merge($defaults, $overrides);
        if (isset($data['config_json']) && is_array($data['config_json'])) {
            $data['config_json'] = wp_json_encode($data['config_json']);
        }
        $data = array_filter($data, static fn($v) => $v !== null);
        $this->wpdb->insert($this->wpdb->prefix . 'enzo_providers', $data);
        return (int) $this->wpdb->insert_id;
    }

    private function getAiProvidersFromDb(): array
    {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}enzo_providers",
            ARRAY_A
        ) ?: [];
    }

    protected function createQueueItem(array $overrides = []): int
    {
        // Matches the real enzo_queue schema (no priority column).
        $defaults = [
            'job_type'     => 'publish_post',
            'status'       => 'pending',
            'attempts'     => 0,
            'max_attempts' => 5,
            'payload'      => '{}',
            'next_run_at'  => current_time('mysql', 1),
            'created_at'   => current_time('mysql', 1),
            'updated_at'   => current_time('mysql', 1),
        ];
        $data = array_merge($defaults, $overrides);
        $this->wpdb->insert($this->wpdb->prefix . 'enzo_queue', $data);
        return (int) $this->wpdb->insert_id;
    }

    protected function getQueueItem(int $id): ?array
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}enzo_queue WHERE id = %d", $id),
            ARRAY_A
        ) ?: null;
    }

    // --------------------------------------------------------------- New v1.1 Endpoints

    public function test_list_posts_returns_and_filters(): void
    {
        $draft = $this->createTestPost(['status' => 'draft']);
        $pub = $this->createTestPost(['status' => 'published']);

        $all = $this->restRequest('GET', '/enzo/v1/posts', [], $this->adminUserId);
        $this->assertEquals(true, $all['success']);
        // wpdb returns ids as strings — normalize before strict assertions.
        $ids = array_map('intval', array_column($all['posts'], 'id'));
        $this->assertContains($draft, $ids);
        $this->assertContains($pub, $ids);

        $req = new WP_REST_Request('GET', '/enzo/v1/posts');
        $req->set_query_params(['status' => 'draft']);
        wp_set_current_user($this->adminUserId);
        $drafts = rest_do_request($req)->get_data();
        wp_set_current_user(0);
        $drafts = is_array($drafts) ? $drafts : [];
        $this->assertEquals(true, $drafts['success'] ?? null);
        foreach ($drafts['posts'] as $row) {
            $this->assertEquals('draft', $row['status']);
        }
        $draftIds = array_map('intval', array_column($drafts['posts'], 'id'));
        $this->assertContains($draft, $draftIds);
        $this->assertNotContains($pub, $draftIds);
    }

    public function test_update_post_edits_fields(): void
    {
        $postId = $this->createTestPost(['status' => 'draft']);

        $response = $this->restRequest('POST', "/enzo/v1/posts/$postId", [
            'title' => 'Updated Title',
            'body'  => 'Updated body text',
        ], $this->adminUserId);
        $this->assertEquals(true, $response['success']);

        $repo = $this->container->make(\ENZO\Infrastructure\WpDb\PostRepository::class);
        $post = $repo->find($postId);
        $this->assertEquals('Updated Title', $post['title']);
        $this->assertEquals('Updated body text', $post['body']);
    }

    public function test_update_post_rejects_published(): void
    {
        $postId = $this->createTestPost(['status' => 'published']);

        $response = $this->restRequest('POST', "/enzo/v1/posts/$postId", [
            'body' => 'should not apply',
        ], $this->adminUserId);
        $this->assertEquals(false, $response['success']);
    }

    public function test_update_post_requires_existing_post(): void
    {
        $response = $this->restRequest('POST', '/enzo/v1/posts/999999', [
            'body' => 'x',
        ], $this->adminUserId);
        $this->assertEquals(false, $response['success']);
    }

    public function test_add_channel_registers_and_rejects_duplicate(): void
    {
        $response = $this->restRequest('POST', '/enzo/v1/channels', [
            'chat_id'  => -100555444333,
            'title'    => 'کانال تست',
            'username' => '@test_channel',
        ], $this->adminUserId);
        $this->assertEquals(true, $response['success']);

        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}enzo_channels WHERE chat_id = -100555444333",
            ARRAY_A
        );
        $this->assertNotNull($row);
        $this->assertEquals('connected', $row['status']);
        $this->assertEquals('channel', $row['chat_type']);

        $dup = $this->restRequest('POST', '/enzo/v1/channels', [
            'chat_id' => -100555444333,
            'title'   => 'دوباره',
        ], $this->adminUserId);
        $this->assertEquals(false, $dup['success']);
    }

    public function test_add_channel_requires_chat_id_and_title(): void
    {
        $noChat = $this->restRequest('POST', '/enzo/v1/channels', ['title' => 'بدون شناسه'], $this->adminUserId);
        $this->assertEquals(false, $noChat['success']);

        $noTitle = $this->restRequest('POST', '/enzo/v1/channels', ['chat_id' => -100111], $this->adminUserId);
        $this->assertEquals(false, $noTitle['success']);
    }

    public function test_telegram_status_reflects_secret_presence(): void
    {
        $secrets = $this->container->make(\ENZO\Contracts\SecretStoreInterface::class);
        $secrets->delete('telegram_bot_token');

        $off = $this->restRequest('GET', '/enzo/v1/telegram/status', [], $this->adminUserId);
        $this->assertEquals(true, $off['success']);
        $this->assertFalse((bool) $off['connected']);

        $secrets->set('telegram_bot_token', '123456789:TESTTOKENTESTTOKENTESTTOKEN1');
        $on = $this->restRequest('GET', '/enzo/v1/telegram/status', [], $this->adminUserId);
        $this->assertTrue((bool) $on['connected']);
        $secrets->delete('telegram_bot_token');
    }

    private function createLogEntry(string $level, string $message): int
    {
        $this->wpdb->insert(
            $this->wpdb->prefix . 'enzo_logs',
            [
                'scope'       => 'test',
                'level'       => $level,
                'message'     => $message,
                'context'     => '{}',
                'request_id'  => null,
                'created_at'  => current_time('mysql', 1),
            ]
        );
        return (int) $this->wpdb->insert_id;
    }
}