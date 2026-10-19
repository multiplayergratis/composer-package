# multiplayer.gratis PHP API Client

PHP-Client für die REST-API von [multiplayer.gratis](https://multiplayer.gratis) – kostenloses Gameserver-Hosting. Bestelle, verlängere und steuere Server automatisiert aus deinem eigenen Code, Bot oder Shop.

## Installation

```bash
composer require multiplayer-gratis/api-client
```

Voraussetzungen: PHP 8.1+, `ext-curl`, `ext-json`.

## Authentifizierung

Erstelle einen API-Schlüssel im Dashboard unter **API**. Ein Schlüssel hat dieselben Rechte wie dein Konto, inklusive Bestellungen mit deinen Coins. Behandle ihn wie ein Passwort – am besten aus einer Umgebungsvariable laden statt ihn im Code zu hinterlegen.

```php
use MultiplayerGratis\Client;

$client = new Client(getenv('MULTIPLAYER_GRATIS_API_KEY'));
```

## Verwendung

Die API funktioniert wie eine Reselling-API: Du fragst den Katalog ab, berechnest den Preis, bestellst und verwaltest Server. Bezahlt wird mit dem Coin-Guthaben deines Kontos.

### Konto

```php
$account = $client->getAccount();
$transactions = $client->getTransactions();
```

### Katalog

```php
use MultiplayerGratis\ServerType;

$types = $client->getTypes();
$packages = $client->getPackages();
$package = $client->getPackage('plus');
$allEggs = $client->getEggs();
$games = $client->getEggs(ServerType::Gameserver);
$runtimes = $client->getEggs('runtime');
```

Server-Arten gibst du als `ServerType` oder als String an. `gameserver` und `gameservers` funktionieren beide, der Client wandelt sie passend für Pfad (`/eggs/gameservers`) und Bestellung (`"type": "gameserver"`) um. Webspaces sind aktuell nicht bestellbar.

### Bestellen

```php
$quote = $client->quote('plus', code: 'HERBST20');

$server = $client->orderServer(
    name: 'Survival',
    type: 'gameserver',
    package: 'plus',
    egg: 3,
    code: 'HERBST20',
    reference: 'discord:123456789',
);

$client->renewServer(123);
$upgrades = $client->getUpgrades(123);
$client->upgradeServer(123, 'pro');
```

### Server verwalten

```php
$servers = $client->getServers();
$gameservers = $client->getServersByType(ServerType::Gameserver);
$online = $client->getServersByStatus('online');
$mine = $client->getServersByReference('discord:123456789');
$server = $client->getServer(123);
$client->updateServer(123, name: 'Survival 2', reference: 'kunde-77');
$client->deleteServer(123, confirm: 'Survival 2');
```

`deleteServer()` löscht den Server endgültig und ohne Erstattung. Als `confirm` muss der aktuelle Servername übergeben werden.

### Steuerung

```php
use MultiplayerGratis\PowerSignal;

$client->startServer(123);
$client->stopServer(123);
$client->restartServer(123);
$client->killServer(123);
$client->sendPowerSignal(123, PowerSignal::Restart);

$resources = $client->getServerResources(123);
```

### Backups

```php
$backups = $client->getBackups(123);
$client->createBackup(123, name: 'Vor dem Update');
$client->restoreBackup(123, $uuid);
$download = $client->getBackupDownload(123, $uuid);
$client->deleteBackup(123, $uuid);
```

### Übersicht

Alle Methoden geben die JSON-Antwort der API als assoziatives Array zurück.

| Methode | Endpunkt |
| --- | --- |
| `getAccount()` | `GET /account` |
| `getTransactions()` | `GET /account/transactions` (die letzten 100) |
| `getTypes()` | `GET /types` |
| `getPackages()` | `GET /packages` |
| `getPackage($id)` | `GET /packages/{id}` |
| `getEggs(?$type)` | `GET /eggs` bzw. `GET /eggs/{type}` |
| `quote($package, ?$code)` | `POST /orders/quote` |
| `orderServer($name, $type, $package, $egg, ?$code, ?$reference)` | `POST /servers` |
| `renewServer($id)` | `POST /servers/{id}/renew` |
| `getUpgrades($id)` | `GET /servers/{id}/upgrade` |
| `upgradeServer($id, $package)` | `POST /servers/{id}/upgrade` |
| `getServers()` | `GET /servers` |
| `getServersByType($type)` | `GET /servers/type/{type}` |
| `getServersByStatus($status)` | `GET /servers/status/{status}` |
| `getServersByReference($reference)` | `GET /servers/reference/{reference}` |
| `getServer($id)` | `GET /servers/{id}` |
| `updateServer($id, ?$name, ?$reference)` | `PATCH /servers/{id}` |
| `deleteServer($id, $confirm)` | `DELETE /servers/{id}` |
| `sendPowerSignal($id, $signal)` | `POST /servers/{id}/power` |
| `getServerResources($id)` | `GET /servers/{id}/resources` |
| `getBackups($id)` | `GET /servers/{id}/backups` |
| `createBackup($id, ?$name)` | `POST /servers/{id}/backups` |
| `restoreBackup($id, $uuid)` | `POST /servers/{id}/backups/{uuid}/restore` |
| `getBackupDownload($id, $uuid)` | `GET /servers/{id}/backups/{uuid}/download` |
| `deleteBackup($id, $uuid)` | `DELETE /servers/{id}/backups/{uuid}` |

Tägliche Coins können nicht über die API abgeholt werden, nur im Dashboard. Ob die heutige Belohnung schon abgeholt wurde, steht in `getAccount()` unter `daily_reward`.

## Fehlerbehandlung

Fehler der API (`{ "error": "…" }`) werden als Exceptions geworfen. Alle erben von `MultiplayerGratis\Exception\ApiException`.

| Status | Exception | Bedeutung |
| --- | --- | --- |
| 401 | `AuthenticationException` | Schlüssel fehlt oder ist ungültig |
| 402 | `InsufficientCoinsException` | Nicht genug Coins |
| 403 | `ForbiddenException` | Nicht über die API erlaubt |
| 404 | `NotFoundException` | Server, Paket oder Backup nicht gefunden |
| 409 | `ConflictException` | Server-Limit erreicht, Server abgelaufen oder nicht steuerbar, Backup-Limit voll |
| 422 | `ValidationException` | Ungültige Eingabe, z. B. unbekanntes Paket oder ungültiger Rabattcode |
| 423 | `LockedException` | Vom Team gesperrt |
| 429 | `RateLimitException` | Mehr als 60 Anfragen pro Minute |
| 502 / 503 | `ServiceUnavailableException` | Panel nicht erreichbar oder Shop vorübergehend geschlossen |
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
