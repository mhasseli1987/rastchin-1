<?php
namespace ENZO\AI;

use ENZO\Contracts\AI\AIProviderInterface;
use ENZO\Contracts\AI\AIRequest;
use ENZO\Contracts\AI\AIResult;
use ENZO\Contracts\HttpClientInterface;
use ENZO\Logging\Logger;

defined('ABSPATH') || exit;

/**
 * Local AI Proxy — OpenAI-compatible API server running inside WordPress.
 * Provides a local endpoint at /wp-json/enzo/v1/ai/local/* that mimics
 * the OpenAI /v1/chat/completions API by proxying to free providers.
 *
 * This is the "Self-Install" feature: users click a button in AI Services,
 * and the plugin auto-configures a local OpenAI-compatible endpoint.
 */
class LocalAiProxy implements AIProviderInterface
{
    public const ENDPOINT_CHAT = 'chat/completions';
    public const ENDPOINT_MODELS = 'models';

    private HttpClientInterface $http;
    private Logger $logger;
    private array $freeProviders = [
        // Free providers that don't require API keys (or have free tiers)
        [
            'name' => 'HuggingFace',
            'baseUrl' => 'https://api-inference.huggingface.co/models/',
            'models' => ['microsoft/DialoGPT-medium', 'facebook/blenderbot-400M-distill'],
            'requiresKey' => true,
            'format' => 'huggingface',
        ],
        [
            'name' => 'Cohere',
            'baseUrl' => 'https://api.cohere.ai/v1/',
            'models' => ['command', 'command-light'],
            'requiresKey' => true,
            'format' => 'cohere',
        ],
        [
            'name' => 'Together AI',
            'baseUrl' => 'https://api.together.xyz/v1/',
            'models' => ['mistralai/Mixtral-8x7B-Instruct-v0.1', 'meta-llama/Llama-2-70b-chat-hf'],
            'requiresKey' => true,
            'format' => 'openai',
        ],
        // Fallback to local Ollama if available
        [
            'name' => 'Ollama (Local)',
            'baseUrl' => 'http://localhost:11434/v1/',
            'models' => ['llama3', 'mistral', 'phi3'],
            'requiresKey' => false,
            'format' => 'openai',
        ],
    ];

    public function __construct(HttpClientInterface $http, Logger $logger)
    {
        $this->http = $http;
        $this->logger = $logger;
    }

    public function generate(AIRequest $request): AIResult
    {
        $config = $this->parseConfig($request->config);
        $operation = $request->operations[0] ?? 'generate';
        $userMsg = $request->messages[0]['content'] ?? '';
        
        if ($userMsg === '') {
            return AIResult::fail('empty_input', 'محصول: ورودی خالی است.');
        }

        $prompt = $this->buildPrompt($operation, $userMsg, $config);

        // Try each free provider until one works
        foreach ($this->freeProviders as $provider) {
            $result = $this->tryProvider($provider, $config, $prompt, $operation);
            if ($result->success) {
                $this->logger->info('Local AI Proxy success', [
                    'provider' => $provider['name'],
                    'operation' => $operation,
                ]);
                return $result;
            }
            $this->logger->debug('Local AI Proxy provider failed', [
                'provider' => $provider['name'],
                'error' => $result->errorMessage,
            ]);
        }

        return AIResult::fail('all_providers_failed', 'همه ارائه‌دهندگان AI رایگان در دسترس نیستند. لطفاً یک Provider با کلید API تنظیم کنید.');
    }

    public function listModels(array $config = []): array
    {
        $models = [];
        foreach ($this->freeProviders as $provider) {
            foreach ($provider['models'] as $model) {
                $models[] = $provider['name'] . '/' . $model;
            }
        }
        // Add common OpenAI model names for compatibility
        $models = array_merge($models, ['gpt-4o-mini', 'gpt-3.5-turbo', 'gpt-4o']);
        return array_unique($models);
    }

    public function testConnection(array $config = []): array
    {
        $testRequest = new AIRequest(
            operations: ['generate'],
            messages: [['role' => 'user', 'content' => 'test']],
            config: $config
        );
        $result = $this->generate($testRequest);
        return [
            'success' => $result->success,
            'message' => $result->success ? 'اتصال برقرار شد.' : ($result->errorMessage ?? 'نامشخص'),
        ];
    }

    private function tryProvider(array $provider, array $config, string $prompt, string $operation): AIResult
    {
        if ($provider['format'] === 'openai') {
            return $this->tryOpenAICompatible($provider, $config, $prompt, $operation);
        } elseif ($provider['format'] === 'huggingface') {
            return $this->tryHuggingFace($provider, $config, $prompt);
        } elseif ($provider['format'] === 'cohere') {
            return $this->tryCohere($provider, $config, $prompt);
        }
        return AIResult::fail('unknown_format', 'فرمت ناشناس');
    }

    private function tryOpenAICompatible(array $provider, array $config, string $prompt, string $operation): AIResult
    {
        $baseUrl = rtrim($provider['baseUrl'], '/') . '/chat/completions';
        $apiKey = $config['apiKey'] ?? '';
        
        $payload = [
            'model' => $config['model'] ?? $provider['models'][0],
            'messages' => [
                ['role' => 'system', 'content' => $this->buildSystemPrompt($config)],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $config['temperature'] ?? 0.5,
            'max_tokens' => $config['maxTokens'] ?? 2000,
            'stream' => false,
        ];

        $headers = ['Content-Type' => 'application/json'];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        try {
            $resp = $this->http->request($baseUrl, [
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => $config['timeout'] ?? 30,
            ]);
            
            $body = json_decode($resp['body'], true);
            if (!is_array($body) || empty($body['choices'][0]['message']['content'])) {
                return AIResult::fail('invalid_response', 'پاسخ نامعتبر از ' . $provider['name']);
            }
            
            $content = trim($body['choices'][0]['message']['content']);
            $tokens = (int) ($body['usage']['total_tokens'] ?? 0);
            
            return AIResult::ok($content, (string) $payload['model'], $tokens, $resp);
        } catch (\Exception $e) {
            return AIResult::fail('request_failed', $e->getMessage());
        }
    }

    private function tryHuggingFace(array $provider, array $config, string $prompt): AIResult
    {
        $apiKey = $config['apiKey'] ?? '';
        if ($apiKey === '') {
            return AIResult::fail('no_key', 'HuggingFace نیاز به API Key دارد');
        }
        
        $model = $config['model'] ?? $provider['models'][0];
        $url = rtrim($provider['baseUrl'], '/') . '/' . urlencode($model);
        
        $payload = ['inputs' => $prompt, 'parameters' => ['max_new_tokens' => $config['maxTokens'] ?? 512]];
        
        try {
            $resp = $this->http->request($url, [
                'method' => 'POST',
                'headers' => ['Authorization' => 'Bearer ' . $apiKey, 'Content-Type' => 'application/json'],
                'body' => json_encode($payload),
                'timeout' => $config['timeout'] ?? 30,
            ]);
            
            $body = json_decode($resp['body'], true);
            if (!is_array($body) || !isset($body[0]['generated_text'])) {
                return AIResult::fail('invalid_response', 'پاسخ نامعتبر از HuggingFace');
            }
            
            $content = trim($body[0]['generated_text']);
            return AIResult::ok($content, $model, 0, $resp);
        } catch (\Exception $e) {
            return AIResult::fail('request_failed', $e->getMessage());
        }
    }

    private function tryCohere(array $provider, array $config, string $prompt): AIResult
    {
        $apiKey = $config['apiKey'] ?? '';
        if ($apiKey === '') {
            return AIResult::fail('no_key', 'Cohere نیاز به API Key دارد');
        }
        
        $model = $config['model'] ?? $provider['models'][0];
        $url = rtrim($provider['baseUrl'], '/') . 'chat';
        
        $payload = ['model' => $model, 'message' => $prompt, 'temperature' => $config['temperature'] ?? 0.5];
        
        try {
            $resp = $this->http->request($url, [
                'method' => 'POST',
                'headers' => ['Authorization' => 'Bearer ' . $apiKey, 'Content-Type' => 'application/json'],
                'body' => json_encode($payload),
                'timeout' => $config['timeout'] ?? 30,
            ]);
            
            $body = json_decode($resp['body'], true);
            if (!is_array($body) || !isset($body['text'])) {
                return AIResult::fail('invalid_response', 'پاسخ نامعتبر از Cohere');
            }
            
            $content = trim($body['text']);
            return AIResult::ok($content, $model, (int) ($body['meta']['tokens']['output_tokens'] ?? 0), $resp);
        } catch (\Exception $e) {
            return AIResult::fail('request_failed', $e->getMessage());
        }
    }

    private function parseConfig(array $config): array
    {
        return wp_parse_args($config, [
            'model' => 'llama3',
            'temperature' => 0.5,
            'maxTokens' => 2000,
            'timeout' => 30,
            'apiKey' => '',
        ]);
    }

    private function buildSystemPrompt(array $config): string
    {
        return 'شما دستیار تولید محتوای تلگرام هستید. متن خروجی باید آماده‌ی انتشار در یک کانال تلگرام فارسی باشد.';
    }

    private function buildPrompt(string $operation, string $input, array $config): string
    {
        $desc = match ($operation) {
            'rewrite' => 'متن زیر را بازنویسی کنید (همان معنا، بیان بهتر):',
            'summarize' => 'متن زیر را به‌صورت خلاصه و مفید بنویسید:',
            'title' => 'برای متن زیر یک تیتر جذاب بنویسید (فقط تیتر):',
            'caption' => 'برای تصویر/محتوای زیر یک کپشن (Caption) جذاب برای تلگرام بنویسید:',
            'translate' => 'متن زیر را به زبان فارسی ترجمه کنید:',
            'seo' => 'کپشن زیر را برای سئو (SEO) بهینه کنید: کلمات کلیدی مهم را حفظ کنید، طول مناسب برای تلگرام (زیر ۱۰۲۴ کاراکتر)، ساختار جذاب با ایموجی مرتبط، و بهینه برای جستجوی داخلی تلگرام:',
            'hashtags' => 'از متن زیر ۵ تا ۱۰ هشتگ (#) پرکاربرد و مرتبط برای تلگرام استخراج کنید. فقط هشتگ‌ها را با فاصله از هم برگردانند، بدون متن اضافه:',
            default => 'متن زیر را تولید یا پردازش کنید:',
        };
        return $desc . "\n\n" . $input;
    }
}