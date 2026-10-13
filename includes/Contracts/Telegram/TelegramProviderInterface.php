<?php
namespace ENZO\Contracts\Telegram;

defined('ABSPATH') || exit;

interface TelegramProviderInterface
{
    public function getMe(): array;
    public function getChat(int $chatId): array;
    public function sendMessage(array $payload): array;
    public function sendPhoto(array $payload): array;
    public function sendMediaGroup(array $payload): array;
    public function editMessageText(array $payload): array;
    public function testConnection(): array;
    public function setPlatform(string $platform): void;
}