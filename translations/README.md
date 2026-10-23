# Translations Directory / Directorio de Traducciones

> [!WARNING]
> **DO NOT EDIT TARGET LANGUAGE FILES MANUALLY.**
> **NO EDITE MANUALMENTE LOS ARCHIVOS DE IDIOMAS DESTINO.**

---

### English

This directory contains the translation files used by Symfony in **ICU MessageFormat** (`*+intl-icu.<locale>.yaml`).

#### Help Translate / Ayuda a traducir
To contribute translations for Pollaris, please use Translatewiki.net:
👉 **[Translate Pollaris on Translatewiki](https://translatewiki.net/w/i.php?title=Special%3ATranslate&group=pollaris)**

#### Developer Workflow:
1. **Source of Truth for New Strings:**
   - Always define new strings **only in English**:
     - `messages+intl-icu.en_GB.yaml`
     - `validators+intl-icu.en_GB.yaml`
   - Do **not** manually add or translate these strings into other language files (`.es.yaml`, `.fr_FR.yaml`, `.de.yaml`, etc.).
2. **Fallback:**
   - When a string is not yet translated into a target language, Symfony will automatically fallback to the English string (`en_GB`).
3. **Export to Translatewiki:**
   - To send new English strings to Translatewiki for community localization, run:
     ```bash
     php bin/console app:translatewiki:export
     ```
   - This converts Symfony ICU YAML to MediaWiki JSON in the `translatewiki/` directory.
4. **Import from Translatewiki:**
   - When localization updates arrive from Translatewiki (`translatewiki/*.json`), compile them into Symfony YAML by running:
     ```bash
     php bin/console app:translatewiki:import
     ```
   - Any manual changes made directly to non-English YAML files will be **overwritten** by this command.

---

### Español

Este directorio contiene los archivos de traducción que utiliza Symfony bajo el formato **ICU MessageFormat** (`*+intl-icu.<locale>.yaml`).

#### Colaborar con las traducciones:
Para ayudar a traducir Pollaris a otros idiomas, por favor utiliza Translatewiki.net:
👉 **[Traducir Pollaris en Translatewiki](https://translatewiki.net/w/i.php?title=Special%3ATranslate&group=pollaris)**

#### Flujo de trabajo para desarrolladores:
1. **Fuente de verdad para nuevas cadenas:**
   - Agregue nuevas cadenas **únicamente en inglés**:
     - `messages+intl-icu.en_GB.yaml`
     - `validators+intl-icu.en_GB.yaml`
   - **No** agregue ni traduzca manualmente estas cadenas en otros idiomas (`.es.yaml`, `.fr_FR.yaml`, `.de.yaml`, etc.).
2. **Fallback automático:**
   - Mientras una cadena no esté traducida en un idioma, Symfony mostrará automáticamente la versión de fallback en inglés (`en_GB`).
3. **Exportar a Translatewiki:**
   - Para enviar las cadenas en inglés a Translatewiki para que la comunidad las traduzca:
     ```bash
     php bin/console app:translatewiki:export
     ```
   - Esto convierte los YAML (ICU) a JSON (MediaWiki) en el directorio `translatewiki/`.
4. **Importar desde Translatewiki:**
   - Cuando lleguen traducciones desde Translatewiki (`translatewiki/*.json`), compílelas a YAML ejecutando:
     ```bash
     php bin/console app:translatewiki:import
     ```
   - Cualquier cambio manual realizado directamente en los archivos YAML de otros idiomas será **sobrescrito** al ejecutar este comando.
