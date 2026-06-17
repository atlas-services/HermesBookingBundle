# Intégration Hermes Booking Bundle — projet Symfony hôte

## Installation minimale

```bash
composer require atlas-services/hermes-booking-bundle
```

1. Enregistrer le bundle dans `config/bundles.php` (Flex le fait en général).
2. Variables d’environnement :

```dotenv
HERMES_BOOKING_ENABLED=1
MAILER_DSN=...
MAILER_FROM=...
CONTACT_EMAIL=...   # optionnel, copie admin
```

3. Configuration (`config/packages/hermes_booking.yaml`) :

```yaml
hermes_booking:
    enabled: true
    timezone: 'Europe/Paris'
    admin_email: '%env(CONTACT_EMAIL)%'
    from_email: '%env(MAILER_FROM)%'
    section_resolver:
        entity: App\Entity\Section   # optionnel — libellés admin Hermes
```

4. Migrations Doctrine (tables `booking_*`).

5. **Front** : le projet hôte adapte son modèle (section, configs…) et inclut le formulaire du bundle :

```twig
{# Exemple Hermes CMS — templates/front/section/booking.html.twig #}
{% set bundleVars = booking_form_vars(bookingKey, presentation, presentationText, userText) %}
{% include '@HermesBooking/front/booking_form.html.twig' with bundleVars %}
```

`booking_form_vars(bookingKey, presentation, presentationText, userText)` ne connaît pas Section ni configs Hermes.
L’hôte fournit un `bookingKey` (ex. `s12`) et un tableau `presentation` optionnel (bgcolor, color, …).

6. **Admin** (optionnel) — dans la nav du tableau de bord, entre « Newsletter et livre d'or » et « User » :

```twig
{% include '@HermesBooking/admin/_dashboard_nav_item.html.twig' %}
```

7. **Liste sections admin** (optionnel) :

```twig
{% include '@HermesBooking/admin/_section_manage_button.html.twig' with { section: section } %}
```

## Fourni automatiquement par le bundle

- Routes front + admin (`AbstractBundle::configureRoutes`)
- Entités Doctrine + mapping ORM
- Templates Twig (`@HermesBooking/...`)
- Traductions `booking.*`
- Stimulus `booking-form` + CSS (Asset Mapper + `controllers.json`)
- Paramètre `hermes_booking.template_codes` pour filtrer les gabarits admin
- Injection du gabarit `booking` dans le paramètre `templates` si absent (Hermes `app:init-hermes`)
- Global Twig `hermes_booking_enabled`

## Surcharges

| Élément | Comment |
|--------|---------|
| Formulaire front | `@HermesBooking/front/booking_form.html.twig` |
| Variables Twig | `booking_form_vars(bookingKey, presentation, …)` |
| Admin | `@HermesBooking/admin/index.html.twig` |
| Styles | `assets/styles/booking.css` dans le bundle |
| Sections admin | `BookingSectionResolverInterface` (service custom) |

## Hermes CMS

Avec `section_resolver.entity: App\Entity\Section`, l’admin affiche le nom des pages.
Sans entité Section, repli sur les agendas déjà créés (`booking_calendar`).
