# CLAUDE.md

Ce fichier fournit des instructions a Claude Code (claude.ai/code) pour travailler avec le code de ce depot.

## Presentation du projet

Dashboard Symfony 7.3 d'agregation meteo (PHP 8.2+). Compare les previsions horaires et journalieres de 6 APIs meteo pour un lieu configurable. Pas de base de donnees — consommateur d'API sans etat avec cache Symfony.

## Commandes

```bash
composer test          # Lancer tous les tests (PHPUnit)
bin/phpunit tests/Service/ApiSources/MetNoServiceTest.php   # Lancer un fichier de test unique
composer cs            # Verifier le style de code (dry-run)
composer cs-fix        # Corriger le style de code
composer stan          # Analyse statique PHPStan (niveau 5)
symfony server:start   # Demarrer le serveur de dev local
```

Les hooks pre-commit (Husky) executent automatiquement `composer stan`, `composer cs` et `composer test`.

## Architecture

### Pattern Provider/Aggregateur

Le pattern central repose sur l'injection de services tagges. Chaque API meteo est une classe de service dans `src/Service/ApiSources/` qui implemente jusqu'a 3 interfaces :

- `WeatherProviderInterface` (tag : `app.weather_provider`) — conditions actuelles → `WeatherData`
- `ForecastProviderInterface` (tag : `app.forecast_provider`) — previsions multi-jours → `ForecastData[]`
- `HourlyForecastProviderInterface` (tag : `app.hourly_forecast`) — previsions horaires → `HourlyForecastData[]`

Les tags sont auto-configures via `#[AutoconfigureTag]` sur chaque interface. Les classes aggregatrices (`WeatherAggregator`, `ForecastAggregator`, `HourlyForecastAggregator`) recoivent tous les providers via `!tagged_iterator` et les parcourent pour collecter les resultats.

### Ajouter un nouveau fournisseur meteo

1. Creer un service dans `src/Service/ApiSources/` implementant les interfaces souhaitees
2. Accepter `HttpClientInterface`, `CacheItemPoolInterface`, `LoggerInterface` et `string $meteo_cache` dans le constructeur
3. Chaque methode recoit un `LocationCoordinatesInterface` et retourne le DTO approprie
4. Le provider est automatiquement decouvert — aucune modification de config necessaire (autoconfigure gere le tagging)
5. Ajouter un test correspondant dans `tests/Service/ApiSources/`

### DTOs

Tous les DTOs utilisent la promotion de proprietes dans le constructeur et se trouvent dans `src/Dto/` :

- **`WeatherData`** — provider, temperature, description, humidity, wind, sourceName, logoUrl, sourceUrl, icon, enabled
- **`ForecastData`** — provider, date (DateTimeImmutable), tmin, tmax, icon, emoji
- **`HourlyForecastData`** — provider, time (DateTimeImmutable), temperature, description, emoji
- **`LocationCoordinates`** — name, latitude, longitude, timezone (implemente `LocationCoordinatesInterface`)

### Cache

PSR-6 `CacheItemPoolInterface`. Meteo actuelle : TTL 600s, previsions : TTL 1800s. Controle par la variable d'environnement `METEO_CACHE` (1 = active). Chaque provider gere son propre cache en interne.

### Flux de requete

```
WeatherController (routes : /, /location/{location})
  → LocationCoordinates (depuis la config env ou GeocodeService)
  → 3 Aggregateurs appellent tous les providers tagges
  → DTOs collectes et transmis a meteo.html.twig
  → Chart.js affiche les graphiques de temperature horaire
```

## Style de code

PHP-CS-Fixer avec les regles `@Symfony`. Surcharges importantes : `declare_strict_types: true`, `yoda_style: false`, virgules finales dans les tableaux/parametres multilignes. Tous les fichiers doivent avoir `declare(strict_types=1)`.

## Environnement

Les cles API (`OPENWEATHER_API_KEY`, `WEATHERAPI_KEY`) et la config de localisation (`METEO_NAME`, `METEO_LATITUDE`, `METEO_LONGITUDE`, `METEO_TIMEZONE`) sont dans `.env.local`. La langue est le francais (templates, traductions).