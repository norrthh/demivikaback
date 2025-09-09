<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SupabaseService
{
    protected $url;
    protected $key;

    public function __construct()
    {
        $this->url = config('services.supabase.url');
        $this->key = config('services.supabase.key');
    }

    // Получить все записи из таблицы
    public function select(string $table, array $params = [])
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type' => 'application/json',
        ])->get("{$this->url}/rest/v1/{$table}", $params);

        return $response->json();
    }

    // Вставить запись
    public function insert(string $table, array $data)
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ])->post("{$this->url}/rest/v1/{$table}", $data);

        return $response->json();
    }

    // Обновить запись
    public function update(string $table, array $data, string $column, $value)
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ])->patch("{$this->url}/rest/v1/{$table}?{$column}=eq.{$value}", $data);

        return $response->json();
    }

    // Удалить запись
    public function delete(string $table, string $column, $value)
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
        ])->delete("{$this->url}/rest/v1/{$table}?{$column}=eq.{$value}");

        return $response->successful();
    }
}
