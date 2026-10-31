<?php

namespace App\Services\Asana;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Accès en lecture à l'API Asana avec un jeton d'accès personnel.
 * Les listes sont paginées automatiquement ; en cas de limite de débit (429),
 * la requête est rejouée après le délai demandé par Asana.
 */
class AsanaClient
{
    public const BASE_URL = 'https://app.asana.com/api/1.0';

    public function __construct(private string $token, private int $maxRetries = 5) {}

    /** Un objet (ex. « users/me »). */
    public function get(string $path, array $query = []): array
    {
        return $this->request($path, $query)['data'] ?? [];
    }

    /**
     * Tous les éléments d'une liste, page après page.
     *
     * @return \Generator<int, array>
     */
    public function each(string $path, array $query = []): \Generator
    {
        $query['limit'] = 100;
        do {
            $body = $this->request($path, $query);
            foreach ($body['data'] ?? [] as $item) {
                yield $item;
            }
            $query['offset'] = $body['next_page']['offset'] ?? null;
        } while ($query['offset']);
    }

    /** @return list<array> */
    public function all(string $path, array $query = []): array
    {
        return iterator_to_array($this->each($path, $query), false);
    }

    /** Télécharge un fichier joint (lien temporaire signé : on n'envoie pas le jeton). */
    public function download(string $url): string
    {
        return Http::timeout(300)->retry(3, 2000, throw: true)->get($url)->throw()->body();
    }

    private function request(string $path, array $query): array
    {
        for ($attempt = 1; ; $attempt++) {
            $response = $this->http()->get(ltrim($path, '/'), array_filter($query, fn ($v) => $v !== null));

            if ($response->status() === 429 && $attempt <= $this->maxRetries) {
                sleep(max(1, (int) $response->header('Retry-After')));

                continue;
            }
            if ($response->serverError() && $attempt <= $this->maxRetries) {
                sleep($attempt * 2);

                continue;
            }
            if ($response->status() === 401) {
                throw new RuntimeException('Jeton Asana refusé : vérifiez le jeton d\'accès personnel.');
            }

            try {
                return $response->throw()->json() ?? [];
            } catch (RequestException $e) {
                $message = $response->json('errors.0.message') ?? $e->getMessage();
                throw new RuntimeException("Erreur Asana sur {$path} : {$message}", previous: $e);
            }
        }
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->timeout(60)
            ->baseUrl(self::BASE_URL);
    }
}
