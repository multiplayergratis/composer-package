# multiplayer.gratis PHP API Client

PHP-Client für die REST-API von [multiplayer.gratis](https://multiplayer.gratis) – kostenloses Gameserver-Hosting.

## Installation

```bash
composer require multiplayer-gratis/api-client
```

Voraussetzungen: PHP 8.1+, `ext-curl`, `ext-json`.

## Authentifizierung

Erstelle einen API-Schlüssel im Dashboard unter **API**. Behandle ihn wie ein Passwort – am besten aus einer Umgebungsvariable laden statt ihn im Code zu hinterlegen.

```php
use MultiplayerGratis\Client;

$client = new Client(getenv('MULTIPLAYER_GRATIS_API_KEY'));
```

## Verwendung

```php
// Konto mit Coin-Guthaben
$account = $client->getAccount();

// Alle Server
$servers = $client->getServers();

// Details & Status eines Servers
$server = $client->getServer(123);

// Auslastung: CPU, RAM, Speicher, Laufzeit
$resources = $client->getServerResources(123);

// Power-Aktionen
$client->startServer(123);
$client->stopServer(123);
$client->restartServer(123);
$client->killServer(123);   // sofort beenden

// oder generisch
use MultiplayerGratis\PowerSignal;
$client->sendPowerSignal(123, PowerSignal::Restart);
```

Alle Methoden geben die JSON-Antwort der API als assoziatives Array zurück.

| Methode | Endpunkt |
| --- | --- |
| `getAccount()` | `GET /account` |
| `getServers()` | `GET /servers` |
| `getServer($id)` | `GET /servers/{id}` |
| `getServerResources($id)` | `GET /servers/{id}/resources` |
| `sendPowerSignal($id, $signal)` | `POST /servers/{id}/power` |

## Fehlerbehandlung

Fehler der API (`{ "error": "…" }`) werden als Exceptions geworfen. Alle erben von `MultiplayerGratis\Exception\ApiException`.

| Status | Exception | Bedeutung |
| --- | --- | --- |
| 401 | `AuthenticationException` | Schlüssel fehlt oder ist ungültig |
| 404 | `NotFoundException` | Server nicht gefunden |
| 409 | `ConflictException` | Server abgelaufen oder nicht steuerbar |
| 423 | `LockedException` | Vom Team gesperrt |
| 429 | `RateLimitException` | Mehr als 60 Anfragen pro Minute |
| – | `TransportException` | API nicht erreichbar (Netzwerkfehler) |

```php
use MultiplayerGratis\Exception\ApiException;
use MultiplayerGratis\Exception\RateLimitException;

try {
    $client->restartServer(123);
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter() ?? 60);
} catch (ApiException $e) {
    echo "Fehler {$e->getStatusCode()}: {$e->getMessage()}";
}
```

## Eigener HTTP-Transport

Standardmäßig wird cURL genutzt. Timeouts lassen sich anpassen oder ein eigener Transport über `HttpClientInterface` übergeben (z. B. für Tests):

```php
use MultiplayerGratis\Client;
use MultiplayerGratis\Http\CurlHttpClient;

$client = new Client($apiKey, http: new CurlHttpClient(timeout: 30, connectTimeout: 10));
```

## Tests

```bash
composer install
composer test
```

## Lizenz

MIT – siehe [LICENSE](LICENSE).
